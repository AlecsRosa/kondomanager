<script setup lang="ts">
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useEventStyling } from '@/composables/useEventStyling';
import { useCurrencyFormatter } from '@/composables/useCurrencyFormatter'; 
import { format, differenceInDays } from 'date-fns';
import { it } from 'date-fns/locale';
import { Building2, Wallet, Users, Banknote, CalendarDays, FileText, AlertCircle, ArrowRight, CheckCircle, AlertTriangle, CreditCard, Info, Clock, XCircle, ClockArrowUp } from 'lucide-vue-next'; 
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

const isAdmin = computed(() => props.evento?.meta?.type === 'emissione_rata');
const isCondomino = computed(() => props.evento?.meta?.type === 'scadenza_rata_condomino');

// Stati
const isPaid = computed(() => props.evento?.meta?.status === 'paid');
const isReported = computed(() => props.evento?.meta?.status === 'reported');
const isPartial = computed(() => props.evento?.meta?.status === 'partial'); 
const isCredit = computed(() => isCondomino.value && (props.evento?.meta?.importo_restante < 0));
const daysDiff = computed(() => { if (!props.evento?.start_time) return 0; return differenceInDays(new Date(props.evento.start_time), new Date()); });
const isRejected = computed(() => props.evento?.meta?.status === 'rejected');

// Logica Scadenza
const isExpired = computed(() => 
    daysDiff.value < 0 && 
    !isCredit.value && 
    !isPaid.value && 
    !isReported.value &&
    !isPartial.value 
);

const formatDate = (dateStr: string) => { if(!dateStr) return ''; return format(new Date(dateStr), "d MMMM yyyy", { locale: it }); };

const reportPayment = () => {
    isProcessing.value = true;
    router.post(route('user.eventi.report_payment', props.evento.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            isProcessing.value = false;
            emit('close'); 
        },
        onError: () => isProcessing.value = false
    });
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="emit('close')">
        <DialogContent class="sm:max-w-3xl p-0 overflow-hidden rounded-xl border-none shadow-2xl bg-white dark:bg-slate-950 block gap-0">
            <div class="flex flex-col md:flex-row h-full min-h-[400px]">
                
                <div class="md:w-[35%] bg-slate-50 dark:bg-slate-900/50 p-6 flex flex-col justify-between border-r border-slate-100 dark:border-slate-800">
                    
                    <div>
                        <div class="flex flex-row flex-wrap items-center gap-2 mb-6">
                            <Badge variant="outline" :class="[getEventStyle(evento).color, 'border-current bg-white dark:bg-slate-900 shadow-sm px-2 py-0.5 whitespace-nowrap']">
                                <component :is="getEventStyle(evento).icon" class="w-3.5 h-3.5 mr-1.5" />
                                {{ getEventStyle(evento).label }}
                            </Badge>

                            <Badge v-if="isExpired && !getEventStyle(evento).label.toLowerCase().includes('scaduto') && !isRejected" variant="destructive" class="bg-red-100 text-red-700 border-red-200 px-2 py-0.5 whitespace-nowrap">
                                <AlertTriangle class="w-3.5 h-3.5 mr-1" /> Scaduto
                            </Badge>
                        </div>
                        
                        <div class="mb-8">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Data Riferimento</span>
                            <div class="flex items-center gap-2" :class="isExpired && !isRejected ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-200'">
                                <CalendarDays class="w-5 h-5" :class="isExpired && !isRejected ? 'text-red-400' : 'text-slate-400'" />
                                <span class="text-lg font-medium capitalize">{{ formatDate(evento.start_time) }}</span>
                            </div>
                            <span v-if="isExpired && !isRejected" class="text-xs text-red-500 font-medium mt-1 block">Ritardo di {{ Math.abs(daysDiff) }} giorni</span>
                        </div>

                        <div v-if="evento.meta?.totale_rata || evento.meta?.importo_originale">
                             <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">{{ isAdmin ? 'Totale Emissione' : (isCredit ? 'Importo a Credito' : 'Importo Rata') }}</span>
                            <span class="text-4xl font-bold tracking-tight block tabular-nums" :class="isCredit ? 'text-blue-600 dark:text-blue-400' : 'text-slate-900 dark:text-white'">
                                {{ euro(Math.abs(evento.meta.totale_rata || evento.meta.importo_originale)) }}
                            </span>

                            <div v-if="evento.meta?.dettaglio_quote && evento.meta.dettaglio_quote.length > 0" class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                                
                                <div class="flex flex-col gap-2 mb-3">
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Dettaglio Unità</p>
                                    
                                    <div class="flex items-center gap-3 text-[10px] font-medium text-slate-500">
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-2 h-2 rounded-full bg-emerald-500"></div> Credito
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <div class="w-2 h-2 rounded-full bg-orange-400"></div> Da Pagare
                                        </div>
                                    </div>
                                </div>
                                
                                <ul class="space-y-2">
                                    <li v-for="(item, idx) in evento.meta.dettaglio_quote" :key="idx" class="flex justify-between items-center text-xs">
                                        <div class="flex items-center gap-2 overflow-hidden">
                                            <div class="w-1.5 h-1.5 rounded-full shrink-0" 
                                                :class="item.importo < 0 ? 'bg-emerald-500' : 'bg-orange-400'">
                                            </div>
                                            <span class="text-slate-600 dark:text-slate-400 truncate">{{ item.descrizione }}</span>
                                        </div>
                                        
                                        <span 
                                            class="font-mono font-medium" 
                                            :class="item.importo < 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-700 dark:text-slate-200'"
                                        >
                                            {{ euro(item.importo) }}
                                        </span>
                                    </li>
                                </ul>

                                <div v-if="isCredit && evento.meta.dettaglio_quote.some((q: any) => q.importo > 0)" 
                                     class="mt-3 p-2.5 bg-emerald-50/50 dark:bg-emerald-900/10 border border-emerald-100 dark:border-emerald-800/30 rounded-md text-xs text-emerald-800 dark:text-emerald-400 flex gap-2 items-start shadow-sm">
                                    <CheckCircle class="w-4 h-4 shrink-0 mt-0.5 text-emerald-600" />
                                    <span class="leading-tight">
                                        <strong>Coperto:</strong> Il tuo credito residuo salda automaticamente la quota di 
                                        <span class="font-mono font-bold">{{ euro(evento.meta.dettaglio_quote.find((q: any) => q.importo > 0)?.importo || 0) }}</span>.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8"> 
                        <div v-if="isCondomino" class="flex flex-col gap-3">
                            <div v-if="isReported" class="text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 p-3 rounded-lg border border-amber-100 dark:border-amber-800 text-center leading-relaxed">
                                <span class="font-semibold block mb-1 flex items-center justify-center gap-1"><Clock class="w-3 h-3"/> In attesa di verifica</span>
                                Hai segnalato il pagamento. L'amministratore verificherà l'incasso a breve.
                            </div>

                            <div v-else-if="isCredit" class="text-xs text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg border border-blue-100 dark:border-blue-800 text-center leading-relaxed">
                                <span class="font-semibold block mb-1">Nessun pagamento necessario.</span>
                                Questo importo a credito verrà scalato automaticamente dalle rate successive.
                            </div>

                            <div v-else-if="isPaid" class="text-xs text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/20 p-3 rounded-lg border border-emerald-100 dark:border-emerald-800 text-center leading-relaxed">
                                <span class="font-semibold block mb-1 flex items-center justify-center gap-1">
                                   Pagamento Confermato
                                </span>
                                Il pagamento dell'intera rata è stato registrato con successo.
                            </div>

                            <div v-else-if="isPartial" class="text-xs text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-900/20 p-3 rounded-lg border border-orange-100 dark:border-orange-800 text-center leading-relaxed">
                                <span class="font-semibold block mb-1">Pagamento Parziale</span>
                                Hai versato una parte dell'importo. Completa il saldo entro la scadenza.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="md:w-[65%] p-6 flex flex-col relative"> 
                    <h2 class="text-2xl font-bold pr-10 mb-6 leading-tight flex items-start gap-2" :class="isExpired && !isRejected ? 'text-red-600 dark:text-red-500' : 'text-slate-900 dark:text-white'">
                        <AlertTriangle v-if="isExpired && !isRejected" class="w-7 h-7 shrink-0" />
                        {{ evento.title }}
                    </h2>

                    <div v-if="isRejected" class="mb-6 p-4 rounded-lg bg-red-50 border border-red-100 dark:bg-red-900/10 dark:border-red-800">
                        <div class="flex items-start gap-3">
                            <XCircle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                            <div>
                                <h4 class="font-bold text-red-700 text-sm">Segnalazione Rifiutata</h4>
                                <p class="text-xs text-red-600 mt-1 mb-2">
                                    L'amministratore non ha potuto verificare il pagamento.
                                </p>
                                <div class="bg-white/60 dark:bg-black/20 p-2.5 rounded text-xs text-red-800 font-medium border border-red-200/50 italic">
                                    "{{ evento.meta?.rejection_reason }}"
                                </div>
                                <p class="text-xs text-red-500 mt-2">
                                    Verifica i dati o l'estratto conto e clicca "Riprova Segnalazione".
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 mb-6">
                        <div v-if="evento.meta?.condominio_nome" class="group"><span class="text-xs text-slate-500 mb-1 flex items-center gap-1.5"><Building2 class="w-3.5 h-3.5" /> Condominio</span><p class="font-medium text-slate-900 dark:text-white truncate">{{ evento.meta.condominio_nome }}</p></div>
                        <div v-if="evento.meta?.gestione" class="group"><span class="text-xs text-slate-500 mb-1 flex items-center gap-1.5"><Wallet class="w-3.5 h-3.5" /> Gestione</span><p class="font-medium text-slate-900 dark:text-white truncate">{{ evento.meta.gestione }}</p></div>
                        <div v-if="evento.meta?.piano_nome" class="group"><span class="text-xs text-slate-500 mb-1 flex items-center gap-1.5"><FileText class="w-3.5 h-3.5" /> Piano Rate</span><p class="font-medium text-slate-900 dark:text-white truncate">{{ evento.meta.piano_nome }}</p></div>
                        <div v-if="evento.meta?.numero_rata" class="group"><span class="text-xs text-slate-500 mb-1 flex items-center gap-1.5"><Banknote class="w-3.5 h-3.5" /> Rata</span><p class="font-medium text-slate-900 dark:text-white">Numero {{ evento.meta.numero_rata }}</p></div>
                    </div>

                    <div v-if="isCondomino && isReported" class="mb-6 flex items-center justify-between p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50">
                        <span class="text-amber-700 dark:text-amber-500 flex items-center gap-2 font-semibold text-sm"><Clock class="w-4 h-4" /> Verifica in corso</span>
                        <span class="font-bold text-lg text-amber-700 dark:text-amber-500">{{ euro(evento.meta.importo_restante) }}</span>
                    </div>

                    <div v-if="isCondomino && isPartial" class="mb-6 space-y-4">
                        <div class="flex items-center justify-between p-3 rounded-lg bg-orange-50 dark:bg-orange-950/30 border border-orange-200 dark:border-orange-800/50">
                            <div class="flex flex-col">
                                <span class="text-orange-700 dark:text-orange-500 flex items-center gap-2 font-semibold text-sm">
                                    <ClockArrowUp class="w-4 h-4" /> Pagato parzialmente
                                </span>
                                <span class="text-xs text-orange-600/80 mt-1">
                                    Versati: <strong>{{ euro(evento.meta.importo_pagato) }}</strong> su {{ euro(evento.meta.importo_originale) }}
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] uppercase text-orange-600/70 font-bold block">Resta da versare</span>
                                <span class="font-bold text-lg text-orange-700 dark:text-orange-500">{{ euro(evento.meta.importo_restante) }}</span>
                            </div>
                        </div>
                        
                        <Button 
                            class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm font-semibold transition-all rounded-lg"
                            :disabled="isProcessing"
                            @click="reportPayment"
                        >
                            {{ isProcessing ? 'Invio in corso...' : 'Segnala Saldo Rata' }}
                        </Button>
                    </div>

                    <div v-if="isCondomino && !isCredit && !isRejected && !isPaid && !isReported && !isPartial" class="mb-6 space-y-4">
                        <div class="flex items-center justify-between p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/50">
                            <span class="text-amber-700 dark:text-amber-500 flex items-center gap-2 font-semibold text-sm"><AlertCircle class="w-4 h-4" /> Resta da Pagare</span>
                            <span class="font-bold text-lg text-amber-700 dark:text-amber-500">{{ euro(evento.meta.importo_restante) }}</span>
                        </div>
                        
                        <Button 
                            class="w-full h-11 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm font-semibold transition-all rounded-lg"
                            :disabled="isProcessing"
                            @click="reportPayment"
                        >
                            {{ isProcessing ? 'Invio in corso...' : 'Ho già effettuato il pagamento' }}
                        </Button>
                    </div>

                    <div v-if="isCondomino && isRejected" class="mb-6">
                         <Button 
                            variant="destructive" 
                            class="w-full h-11 shadow-sm font-semibold transition-all rounded-lg"
                            :disabled="isProcessing"
                            @click="reportPayment"
                        >
                            {{ isProcessing ? 'Invio in corso...' : 'Riprova Segnalazione' }}
                        </Button>
                    </div>

                    <div v-if="isCondomino && isCredit" class="mb-6 flex items-center justify-between p-3 rounded-lg bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800/50">
                        <span class="text-blue-700 dark:text-blue-500 flex items-center gap-2 font-semibold text-sm"><Info class="w-4 h-4" /> Credito Disponibile</span>
                        <span class="font-bold text-lg text-blue-700 dark:text-blue-500">{{ euro(Math.abs(evento.meta.importo_restante)) }}</span>
                    </div>

                    <div v-if="isCondomino && isPaid" class="mb-6 flex items-center justify-between p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50">
                        <div class="flex flex-col">
                            <span class="text-emerald-700 dark:text-emerald-500 flex items-center gap-2 font-semibold text-sm">
                                <CheckCircle class="w-4 h-4" /> Rata Saldata
                            </span>
                            <span class="text-xs text-emerald-600/80 mt-1">
                                {{ evento.updated_at ? 'Registrato il ' + formatDate(evento.updated_at) : 'Pagamento confermato' }}
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] uppercase text-emerald-600/70 font-bold block">Importo Totale</span>
                            <span class="font-bold text-lg text-emerald-700 dark:text-emerald-500">
                                {{ 
                                    evento.meta.importo_pagato 
                                    ? euro(evento.meta.importo_pagato) 
                                    : euro(evento.meta.importo_originale) 
                                }}
                            </span>
                        </div>
                    </div>

                    <div v-if="isAdmin && evento.meta?.action_url" class="mb-6">
                        <Button as-child class="w-full h-12 text-white font-semibold shadow-lg transition-all rounded-lg" :class="isExpired ? 'bg-red-600 hover:bg-red-700 shadow-red-900/20' : 'bg-blue-600 hover:bg-blue-700 shadow-blue-900/20'">
                            <a :href="evento.meta.action_url" class="flex items-center justify-center gap-2">{{ isExpired ? 'Emetti Subito' : "Vai all'Emissione" }}<ArrowRight class="w-4 h-4" /></a>
                        </Button>
                    </div>

                    <div class="mt-auto pt-6 border-t border-slate-100 dark:border-slate-800">
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-line">{{ evento.description }}</p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>