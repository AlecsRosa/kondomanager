<script setup lang="ts">
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useEventStyling } from '@/composables/useEventStyling';
import { useCurrencyFormatter } from '@/composables/useCurrencyFormatter'; 
import { format, differenceInDays } from 'date-fns';
import { it } from 'date-fns/locale';
import { Building2, Wallet, Users, Banknote, CalendarDays, FileText, AlertCircle, ArrowRight, CheckCircle, AlertTriangle, CreditCard, Info, Clock, XCircle, ClockArrowUp, Coins } from 'lucide-vue-next'; 
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3'; 

const props = defineProps<{
    isOpen: boolean;
    evento: any;
}>();

const emit = defineEmits(['close']);
const { getEventStyle } = useEventStyling();
const { euro } = useCurrencyFormatter(); 
const isProcessing = ref(false); 

// --- COMPUTED LOGICHE ---

const isAdmin = computed(() => props.evento?.meta?.type === 'emissione_rata');
const isCondomino = computed(() => props.evento?.meta?.type === 'scadenza_rata_condomino');

// Valori Monetari
const importoOriginale = computed(() => Number(props.evento?.meta?.totale_rata || props.evento?.meta?.importo_originale || 0));
const importoRestante = computed(() => Number(props.evento?.meta?.importo_restante || 0));
const importoPagato = computed(() => Number(props.evento?.meta?.importo_pagato || 0));

// Stati di Base
const isPaid = computed(() => props.evento?.meta?.status === 'paid'); 
const isReported = computed(() => props.evento?.meta?.status === 'reported');
const isRejected = computed(() => props.evento?.meta?.status === 'rejected');

// Stati Waterfall (Credito)
const isGeneratingCredit = computed(() => isCondomino.value && importoRestante.value < 0); // Rata 1
const isFullyCoveredByCredit = computed(() => props.evento?.meta?.is_covered_by_credit === true); // Rata 2
const isPartiallyCoveredByCredit = computed(() => {
    // Rata 4
    return isCondomino.value && 
           !isGeneratingCredit.value && 
           !isFullyCoveredByCredit.value && 
           !isPaid.value &&
           importoRestante.value > 0 &&
           importoRestante.value < importoOriginale.value &&
           importoPagato.value == 0; 
});

// Stati Pagamento Parziale (Cash)
const isPartialCash = computed(() => props.evento?.meta?.status === 'partial'); 

// Logica Scadenza
const daysDiff = computed(() => { 
    if (!props.evento?.start_time) return 0; 
    return differenceInDays(new Date(props.evento.start_time), new Date()); 
});

const isExpired = computed(() => 
    daysDiff.value < 0 && 
    !isGeneratingCredit.value && 
    !isFullyCoveredByCredit.value && 
    !isPaid.value && 
    !isReported.value && 
    importoRestante.value > 0.01
);

// Logica Emissione
const isEmitted = computed(() => props.evento?.meta?.is_emitted === true);

const formatDate = (dateStr: string) => { if(!dateStr) return ''; return format(new Date(dateStr), "d MMMM yyyy", { locale: it }); };

const reportPayment = () => {
    isProcessing.value = true;
    router.post(route('user.eventi.report_payment', props.evento.id), {}, {
        preserveScroll: true,
        onSuccess: () => { isProcessing.value = false; emit('close'); },
        onError: () => isProcessing.value = false
    });
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="emit('close')">
        <DialogContent class="sm:max-w-3xl p-0 overflow-hidden rounded-xl border-none shadow-2xl bg-white dark:bg-slate-950 block gap-0">
            <div class="flex flex-col md:flex-row h-full min-h-[400px]">
                
                <div class="md:w-[35%] bg-slate-50 dark:bg-slate-900/50 p-6 flex flex-col gap-6 border-r border-slate-100 dark:border-slate-800">
                    
                    <div>
                        <div class="flex flex-row flex-wrap items-center gap-2 mb-6">
                            <Badge variant="outline" :class="[getEventStyle(evento).color, 'border-current bg-white dark:bg-slate-900 shadow-sm px-2 py-0.5 whitespace-nowrap']">
                                <component :is="getEventStyle(evento).icon" class="w-3.5 h-3.5 mr-1.5" />
                                {{ getEventStyle(evento).label }}
                            </Badge>

                            <Badge v-if="isExpired && !isRejected" variant="destructive" class="bg-red-100 text-red-700 border-red-200 px-2 py-0.5 whitespace-nowrap">
                                <AlertTriangle class="w-3.5 h-3.5 mr-1" /> Scaduto
                            </Badge>

                            <Badge v-if="isFullyCoveredByCredit" class="bg-emerald-100 text-emerald-700 border-emerald-200 px-2 py-0.5 whitespace-nowrap hover:bg-emerald-100">
                                <Coins class="w-3.5 h-3.5 mr-1" /> Coperta
                            </Badge>
                        </div>
                        
                        <div class="mb-0">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Data Riferimento</span>
                            <div class="flex items-center gap-2" :class="isExpired ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-200'">
                                <CalendarDays class="w-5 h-5" :class="isExpired ? 'text-red-400' : 'text-slate-400'" />
                                <span class="text-lg font-medium capitalize">{{ formatDate(evento.start_time) }}</span>
                            </div>
                            <span v-if="isExpired" class="text-xs text-red-500 font-medium mt-1 block">Ritardo di {{ Math.abs(daysDiff) }} giorni</span>
                        </div>
                    </div>

                    <div v-if="importoOriginale !== 0">
                         <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">
                             {{ isAdmin ? 'Totale Emissione' : (isGeneratingCredit ? 'Importo a Credito' : 'Importo Rata') }}
                         </span>
                        <span class="text-4xl font-bold tracking-tight block tabular-nums" :class="isGeneratingCredit ? 'text-blue-600 dark:text-blue-400' : 'text-slate-900 dark:text-white'">
                            {{ euro(Math.abs(importoOriginale)) }}
                        </span>

                        <div v-if="evento.meta?.dettaglio_quote && evento.meta.dettaglio_quote.length > 0" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                            <div class="flex flex-col gap-2 mb-3">
                                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Dettaglio Unità</p>
                                <div class="flex items-center gap-3 text-[10px] font-medium text-slate-500">
                                    <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-emerald-500"></div> Credito</div>
                                    <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-orange-400"></div> Da Pagare</div>
                                </div>
                            </div>
                            
                            <ul class="space-y-2">
                                <li v-for="(item, idx) in evento.meta.dettaglio_quote" :key="idx" class="flex justify-between items-center text-xs">
                                    <div class="flex items-center gap-2 overflow-hidden">
                                        <div class="w-1.5 h-1.5 rounded-full shrink-0" :class="item.importo < 0 ? 'bg-emerald-500' : 'bg-orange-400'"></div>
                                        <span class="text-slate-600 dark:text-slate-400 truncate">{{ item.descrizione }}</span>
                                    </div>
                                    <span class="font-mono font-medium" :class="item.importo < 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-700 dark:text-slate-200'">
                                        {{ euro(item.importo) }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div> 
                        <div v-if="isCondomino" class="flex flex-col gap-3">
                            
                            <div v-if="isReported" class="text-xs text-amber-600 bg-amber-50 border border-amber-100 p-3 rounded-lg text-center">
                                <span class="font-semibold block mb-1 flex items-center justify-center gap-1"><Clock class="w-3 h-3"/> In attesa di verifica</span>
                                Hai segnalato il pagamento.
                            </div>

                            <div v-else-if="isGeneratingCredit" class="text-xs text-blue-600 bg-blue-50 border border-blue-100 p-3 rounded-lg text-center">
                                <span class="font-semibold block mb-1 flex items-center justify-center gap-1"><Info class="w-3 h-3"/> A Credito</span>
                                Questo importo verrà scalato automaticamente dalle rate successive.
                            </div>

                            <div v-else-if="isFullyCoveredByCredit" class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 p-3 rounded-lg text-center">
                                <span class="font-semibold block mb-1 flex items-center justify-center gap-1"><Coins class="w-3 h-3"/> Coperta dal Credito</span>
                                Non devi pagare nulla per questa rata.
                            </div>

                            <div v-else-if="isPaid" class="text-xs text-emerald-600 bg-emerald-50 border border-emerald-100 p-3 rounded-lg text-center">
                                <span class="font-semibold block mb-1 flex items-center justify-center gap-1"><CheckCircle class="w-3 h-3"/> Pagata</span>
                                Registrato il {{ evento.updated_at ? formatDate(evento.updated_at) : 'data odierna' }}.
                            </div>

                        </div>
                    </div>
                </div>

                <div class="md:w-[65%] p-6 flex flex-col relative"> 
                    <h2 class="text-2xl font-bold pr-10 mb-6 leading-tight flex items-start gap-2" :class="isExpired ? 'text-red-600 dark:text-red-500' : 'text-slate-900 dark:text-white'">
                        <AlertTriangle v-if="isExpired" class="w-7 h-7 shrink-0" />
                        {{ evento.title }}
                    </h2>

                    <div v-if="isRejected" class="mb-6 p-4 rounded-lg bg-red-50 border border-red-100">
                        <div class="flex items-start gap-3">
                            <XCircle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                            <div>
                                <h4 class="font-bold text-red-700 text-sm">Pagamento Rifiutato</h4>
                                <div class="bg-white p-2.5 rounded text-xs text-red-800 font-medium border border-red-200/50 italic mt-2">
                                    "{{ evento.meta?.rejection_reason }}"
                                </div>
                                <p class="text-xs text-red-500 mt-2">Verifica i dati e riprova.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 mb-6">
                        <div v-if="evento.meta?.condominio_nome" class="group"><span class="text-xs text-slate-500 mb-1 flex items-center gap-1.5"><Building2 class="w-3.5 h-3.5" /> Condominio</span><p class="font-medium text-slate-900 truncate">{{ evento.meta.condominio_nome }}</p></div>
                        <div v-if="evento.meta?.gestione" class="group"><span class="text-xs text-slate-500 mb-1 flex items-center gap-1.5"><Wallet class="w-3.5 h-3.5" /> Gestione</span><p class="font-medium text-slate-900 truncate">{{ evento.meta.gestione }}</p></div>
                        <div v-if="evento.meta?.numero_rata" class="group"><span class="text-xs text-slate-500 mb-1 flex items-center gap-1.5"><Banknote class="w-3.5 h-3.5" /> Rata</span><p class="font-medium text-slate-900">Numero {{ evento.meta.numero_rata }}</p></div>
                    </div>

                    <div v-if="isCondomino">
                        
                        <div v-if="isPartiallyCoveredByCredit" class="mb-6">
                            
                            <div class="flex items-center justify-between p-3 rounded-lg bg-indigo-50 border border-indigo-200 mb-4">
                                <div class="flex flex-col">
                                    <span class="text-indigo-700 flex items-center gap-2 font-semibold text-sm">
                                        <Coins class="w-4 h-4" /> Parzialmente Coperta
                                    </span>
                                    <span class="text-xs text-indigo-600/80 mt-1">
                                        Il credito ha coperto {{ euro(importoOriginale - importoRestante) }}.
                                    </span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-3 rounded-lg bg-amber-50 border border-amber-200 mb-4">
                                <span class="text-amber-700 flex items-center gap-2 font-semibold text-sm"><AlertCircle class="w-4 h-4" /> Resta da Versare</span>
                                <span class="font-bold text-lg text-amber-700">{{ euro(importoRestante) }}</span>
                            </div>
                            
                            <div v-if="!isEmitted">
                                <div class="p-4 rounded-lg bg-slate-100 border border-slate-200 mb-4">
                                    <h4 class="font-bold text-slate-700 text-sm mb-1">Attendi l'emissione</h4>
                                    <p class="text-xs text-slate-600">Non puoi ancora saldare la parte rimanente perché la rata non è stata emessa.</p>
                                </div>
                                <Button class="w-full h-11 bg-slate-300 text-slate-600 cursor-not-allowed rounded-lg" disabled>
                                    Segnalazione pagamento non ancora disponibile
                                </Button>
                            </div>

                            <div v-else>
                                <Button class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm font-semibold rounded-lg" :disabled="isProcessing" @click="reportPayment">
                                    {{ isProcessing ? 'Invio...' : 'Segnala Saldo Rimanente' }}
                                </Button>
                            </div>
                        </div>

                        <div v-else-if="isFullyCoveredByCredit" class="mb-6 flex items-center justify-between p-3 rounded-lg bg-emerald-50 border border-emerald-200">
                            <div class="flex flex-col">
                                <span class="text-emerald-700 flex items-center gap-2 font-semibold text-sm">
                                    <CheckCircle class="w-4 h-4" /> Coperta da Credito
                                </span>
                                <span class="text-xs text-emerald-600/80 mt-1">
                                    Questa rata è stata saldata col credito pregresso.
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase text-emerald-600/70 font-bold block">Da versare</span>
                                <span class="font-bold text-lg text-emerald-700">€ 0,00</span>
                            </div>
                        </div>

                        <div v-else-if="isGeneratingCredit" class="mb-6 flex items-center justify-between p-3 rounded-lg bg-blue-50 border border-blue-200">
                            <div class="flex flex-col">
                                <span class="text-blue-700 flex items-center gap-2 font-semibold text-sm">
                                    <Wallet class="w-4 h-4" /> Credito Residuo
                                </span>
                                <span class="text-xs text-blue-600/80 mt-1">
                                    Eccedenza derivante dal saldo esercizio precedente.
                                </span>
                            </div>
                            <span class="font-bold text-lg text-blue-700">{{ euro(Math.abs(importoRestante)) }}</span>
                        </div>

                        <div v-else-if="!isPaid && !isReported && !isRejected" class="mb-6 space-y-4">
                            
                            <div class="flex items-center justify-between p-3 rounded-lg bg-amber-50 border border-amber-200">
                                <span class="text-amber-700 flex items-center gap-2 font-semibold text-sm"><AlertCircle class="w-4 h-4" /> Totale da Versare</span>
                                <span class="font-bold text-lg text-amber-700">{{ euro(importoRestante) }}</span>
                            </div>
                            
                            <div v-if="!isEmitted">
                                <div class="p-4 rounded-lg bg-slate-100 border border-slate-200 mb-4 mt-4">
                                    <h4 class="font-bold text-slate-700 text-sm mb-1">Attendi l'emissione</h4>
                                    <p class="text-xs text-slate-600">Questa rata non è ancora stata emessa ufficialmente.</p>
                                </div>
                                <Button class="w-full h-11 bg-slate-300 text-slate-600 cursor-not-allowed rounded-lg" disabled>
                                    Segnalazione pagamento non ancora disponibile
                                </Button>
                            </div>

                            <div v-else>
                                <Button class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm font-semibold rounded-lg" :disabled="isProcessing" @click="reportPayment">
                                    {{ isProcessing ? 'Invio...' : 'Ho effettuato il pagamento' }}
                                </Button>
                            </div>
                        </div>

                        <div v-if="isRejected" class="mb-6">
                             <Button variant="destructive" class="w-full h-11 shadow-sm font-semibold rounded-lg" :disabled="isProcessing" @click="reportPayment">
                                {{ isProcessing ? 'Invio...' : 'Riprova Segnalazione' }}
                            </Button>
                        </div>

                    </div>

                    <div v-if="isAdmin && evento.meta?.action_url" class="mb-6">
                        <Button as-child class="w-full h-12 text-white font-semibold shadow-lg rounded-lg" :class="isExpired ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'">
                            <a :href="evento.meta.action_url" class="flex items-center justify-center gap-2">{{ isExpired ? 'Emetti Subito' : "Vai all'Emissione" }}<ArrowRight class="w-4 h-4" /></a>
                        </Button>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800">
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-line">{{ evento.description }}</p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>