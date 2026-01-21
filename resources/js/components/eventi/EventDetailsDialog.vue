<script setup lang="ts">
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useEventStyling } from '@/composables/useEventStyling';
import { useCurrencyFormatter } from '@/composables/useCurrencyFormatter'; 
import { format, differenceInDays } from 'date-fns';
import { it } from 'date-fns/locale';
import { Building2, Wallet, Banknote, CalendarDays, AlertCircle, ArrowRight, CheckCircle, AlertTriangle, Info, Clock, XCircle, Coins, RotateCcw } from 'lucide-vue-next'; 
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3'; 

const props = defineProps<{ isOpen: boolean; evento: any; }>();
const emit = defineEmits(['close']);
const { getEventStyle } = useEventStyling();
// Configurazione formatter (fromCents: true è default)
const { euro } = useCurrencyFormatter(); 
const isProcessing = ref(false); 

const isAdmin = computed(() => props.evento?.meta?.type === 'emissione_rata');
const isCondomino = computed(() => props.evento?.meta?.type === 'scadenza_rata_condomino');

// --- FIX TYPESCRIPT: Tipizzazione esplicita <number> ---
const importoOriginale = computed<number>(() => Number(props.evento?.meta?.totale_rata || props.evento?.meta?.importo_originale || 0));
const importoRestante = computed<number>(() => props.evento?.meta?.importo_restante !== undefined ? Number(props.evento?.meta?.importo_restante) : importoOriginale.value);
const importoPagato = computed<number>(() => Number(props.evento?.meta?.importo_pagato || 0));

// Stati
const isPaid = computed(() => props.evento?.meta?.status === 'paid'); 
const isReported = computed(() => props.evento?.meta?.status === 'reported');
const isRejected = computed(() => props.evento?.meta?.status === 'rejected');

// Logiche Credito
const isGeneratingCredit = computed(() => isCondomino.value && importoRestante.value < -0.01);
const isFullyCoveredByCredit = computed(() => props.evento?.meta?.is_covered_by_credit === true);

// Ora TS non si lamenta più perché sa che sono number
const isPartiallyCoveredByCredit = computed(() => 
    isCondomino.value && 
    !isGeneratingCredit.value && 
    !isFullyCoveredByCredit.value && 
    !isPaid.value && 
    importoRestante.value > 0.01 && 
    importoRestante.value < importoOriginale.value
);

const daysDiff = computed(() => { if (!props.evento?.start_time) return 0; return differenceInDays(new Date(props.evento.start_time), new Date()); });
const isExpired = computed(() => daysDiff.value < 0 && !isGeneratingCredit.value && !isFullyCoveredByCredit.value && !isPaid.value && !isReported.value && importoRestante.value > 0.01);
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

// --- INTERFACCIA PER TYPESCRIPT ---
interface ScontrinoItem {
    descrizione: string;
    credito_disponibile: number;
    quota_rata: number;
    nuovo_saldo: number;
    is_credito: boolean;
}

// --- LOGICA SCONTRINO ---
const scontrinoData = computed<ScontrinoItem[]>(() => {
    const quote = props.evento.meta?.dettaglio_quote || [];
    
    // Calcolo Saldo Iniziale Globale
    let saldoInizialeGlobale = 0;
    quote.forEach((q: any) => { if (q.audit?.saldo_usato) saldoInizialeGlobale += Number(q.audit.saldo_usato); });
    
    let currentAvailableCredit = saldoInizialeGlobale;

    return quote.map((q: any) => {
        const quotaPura = Number(q.audit?.quota_pura !== undefined ? q.audit.quota_pura : q.importo);
        
        if (q.audit?.credito_pregresso_usato) {
            currentAvailableCredit += Number(q.audit.credito_pregresso_usato);
        }

        const nuovoSaldo = currentAvailableCredit + quotaPura;
        
        const item: ScontrinoItem = {
            descrizione: q.descrizione,
            credito_disponibile: currentAvailableCredit,
            quota_rata: quotaPura,
            nuovo_saldo: nuovoSaldo,
            is_credito: nuovoSaldo < -0.01
        };

        currentAvailableCredit = nuovoSaldo;
        
        return item;
    });
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="emit('close')">
        <DialogContent class="sm:max-w-5xl p-0 overflow-hidden rounded-xl border-none shadow-2xl bg-white dark:bg-slate-950 block gap-0">
            <div class="flex flex-col md:flex-row h-full min-h-[450px]">
                
                <div class="md:w-[45%] bg-slate-50 dark:bg-slate-900/50 p-8 flex flex-col gap-6 border-r border-slate-100 dark:border-slate-800 overflow-y-auto max-h-[80vh]">
                    
                    <div>
                        <div class="flex flex-row flex-wrap items-center gap-2 mb-6">
                            <Badge variant="outline" :class="[getEventStyle(evento).color, 'border-current bg-white dark:bg-slate-900 shadow-sm px-2 py-0.5 whitespace-nowrap']">
                                <component :is="getEventStyle(evento).icon" class="w-3.5 h-3.5 mr-1.5" /> {{ getEventStyle(evento).label }}
                            </Badge>
                            
                            <Badge v-if="isExpired && !isRejected && !isAdmin" variant="destructive" class="bg-red-100 text-red-700 border-red-200 px-2 py-0.5 whitespace-nowrap">
                                <AlertTriangle class="w-3.5 h-3.5 mr-1" /> Scaduto
                            </Badge>
                        </div>
                        
                        <div class="mb-0">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Data Riferimento</span>
                            <div class="flex items-center gap-2" :class="isExpired ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-200'">
                                <CalendarDays class="w-5 h-5" :class="isExpired ? 'text-red-400' : 'text-slate-400'" />
                                <span class="text-lg font-medium capitalize">{{ formatDate(evento.start_time) }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                         <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1"> 
                             {{ isAdmin ? 'Totale Emissione' : (isGeneratingCredit ? 'Importo a Credito' : 'Totale da Versare') }} 
                         </span>
                        
                        <span class="text-4xl font-bold tracking-tight block tabular-nums" 
                              :class="isGeneratingCredit ? 'text-blue-600 dark:text-blue-400' : (isFullyCoveredByCredit ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white')"> 
                            {{ euro(isFullyCoveredByCredit ? 0 : importoRestante, { forcePlus: false }) }} 
                        </span>

                        <div v-if="scontrinoData.length > 0" class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-800 space-y-6">
                            
                            <div class="flex flex-col gap-2 mb-2">
                                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Dettaglio Copertura / Utilizzo Credito</p>
                            </div>

                            <div v-for="(item, idx) in scontrinoData" :key="idx" class="relative group">
                                <div v-if="idx < scontrinoData.length - 1" class="absolute left-[11px] top-6 bottom-[-24px] w-px bg-slate-200 dark:bg-slate-700 z-0"></div>

                                <div class="flex items-start gap-3 relative z-10">
                                    <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border bg-white dark:bg-slate-800 shadow-sm border-slate-200 text-slate-500">
                                        <Building2 class="h-3 w-3" />
                                    </div>
                                    
                                    <div class="flex-1">
                                        <div class="font-bold text-xs text-slate-700 dark:text-slate-200 mb-2">{{ item.descrizione }}</div>
                                        
                                        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-lg p-2.5 space-y-1.5 text-xs shadow-sm">
                                            
                                            <div class="flex justify-between items-center text-slate-500 dark:text-slate-400">
                                                <span class="flex items-center gap-1.5">
                                                    <div class="w-1.5 h-1.5 rounded-full" :class="item.credito_disponibile < 0 ? 'bg-emerald-500' : 'bg-red-400'"></div>
                                                    {{ item.credito_disponibile < 0 ? 'Credito Disp.:' : 'Saldo Prog.:' }}
                                                </span>
                                                <span class="font-mono">{{ euro(item.credito_disponibile) }}</span>
                                            </div>

                                            <div class="flex justify-between items-center text-slate-900 dark:text-white font-medium">
                                                <span class="pl-3">Quota Rata:</span>
                                                <span class="font-mono text-slate-700 dark:text-slate-300">
                                                    {{ euro(item.quota_rata, { forcePlus: true }) }}
                                                </span>
                                            </div>

                                            <div class="border-t border-slate-100 dark:border-slate-700 pt-1.5 mt-1 flex justify-between items-center">
                                                <span class="text-[10px] font-bold uppercase text-slate-400">Nuovo Saldo:</span>
                                                <span class="font-mono font-bold" :class="item.nuovo_saldo < -0.01 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white'">
                                                    {{ euro(item.nuovo_saldo) }}
                                                </span>
                                            </div>
                                            
                                            <div v-if="item.is_credito" class="text-right text-[10px] text-emerald-600 dark:text-emerald-500 italic">
                                                (Sei ancora a credito)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 flex justify-between items-center pt-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 -mx-2 px-2 py-2 rounded">
                                <span class="font-bold text-sm text-slate-900 dark:text-white">Netto da Pagare</span>
                                <span class="text-xl font-bold font-mono tracking-tight" 
                                      :class="isFullyCoveredByCredit ? 'text-emerald-600' : (isGeneratingCredit ? 'text-blue-600' : 'text-slate-900 dark:text-white')"> 
                                    {{ euro(isFullyCoveredByCredit ? 0 : importoRestante, { forcePlus: false }) }} 
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="md:w-[55%] p-8 flex flex-col relative overflow-y-auto max-h-[80vh]"> 
                    
                    <h2 class="text-2xl font-bold pr-10 mb-6 leading-tight flex items-start gap-2" :class="isExpired ? 'text-red-600 dark:text-red-500' : 'text-slate-900 dark:text-white'"> <AlertTriangle v-if="isExpired" class="w-7 h-7 shrink-0" /> {{ evento.title }} </h2>
                    
                    <div v-if="isRejected" class="mb-6 p-4 rounded-lg bg-red-50 border border-red-100"><div class="flex items-start gap-3"><XCircle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" /><div><h4 class="font-bold text-red-700 text-sm">Pagamento Rifiutato</h4><div class="bg-white p-2.5 rounded text-xs text-red-800 font-medium border border-red-200/50 italic mt-2"> "{{ evento.meta?.rejection_reason }}" </div><p class="text-xs text-red-500 mt-2">Verifica i dati e riprova.</p></div></div></div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 mb-8">
                        <div v-if="evento.meta?.condominio_nome" class="group"><span class="text-xs text-slate-500 mb-1 flex items-center gap-1.5"><Building2 class="w-3.5 h-3.5" /> Condominio</span><p class="font-medium text-slate-900 truncate">{{ evento.meta.condominio_nome }}</p></div>
                        <div v-if="evento.meta?.gestione" class="group"><span class="text-xs text-slate-500 mb-1 flex items-center gap-1.5"><Wallet class="w-3.5 h-3.5" /> Gestione</span><p class="font-medium text-slate-900 truncate">{{ evento.meta.gestione }}</p></div>
                        <div v-if="evento.meta?.numero_rata" class="group"><span class="text-xs text-slate-500 mb-1 flex items-center gap-1.5"><Banknote class="w-3.5 h-3.5" /> Rata</span><p class="font-medium text-slate-900">Numero {{ evento.meta.numero_rata }}</p></div>
                    </div>

                    <div v-if="isCondomino">
                        <div v-if="isPartiallyCoveredByCredit" class="mb-6">
                            <div class="flex items-center justify-between p-4 rounded-lg bg-indigo-50 border border-indigo-200 mb-4">
                                <div class="flex flex-col">
                                    <span class="text-indigo-700 flex items-center gap-2 font-semibold text-sm"><RotateCcw class="w-4 h-4" /> Parzialmente Coperta</span>
                                    <span class="text-xs text-indigo-600/80 mt-1">Il credito ha coperto {{ euro(importoOriginale - importoRestante) }}.</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-4 rounded-lg bg-amber-50 border border-amber-200 mb-4">
                                <span class="text-amber-700 flex items-center gap-2 font-semibold text-sm"><AlertCircle class="w-4 h-4" /> Resta da Versare</span>
                                <span class="font-bold text-xl text-amber-700">{{ euro(importoRestante) }}</span>
                            </div>
                            <div v-if="!isEmitted">
                                <div class="p-3 rounded-lg bg-slate-100 border border-slate-200 mb-3 flex gap-3 items-start">
                                    <Clock class="w-4 h-4 mt-0.5 text-slate-400" />
                                    <div>
                                        <h4 class="font-bold text-slate-700 text-xs mb-0.5">Il pagamento non è ancora aperto</h4>
                                        <p class="text-[11px] text-slate-500 leading-snug">Non devi fare nulla per ora: il pagamento verrà abilitato a breve.</p>
                                    </div>
                                </div>
                                <Button class="w-full h-10 bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed rounded-lg font-medium hover:bg-slate-100 shadow-none text-xs" disabled>In attesa di emissione...</Button>
                            </div>
                            <div v-else><Button class="w-full h-12 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm font-semibold rounded-lg" :disabled="isProcessing" @click="reportPayment">{{ isProcessing ? 'Invio...' : 'Segnala Saldo Rimanente' }}</Button></div>
                        </div>

                        <div v-else-if="isFullyCoveredByCredit" class="mb-6 flex items-center justify-between p-4 rounded-lg bg-emerald-50 border border-emerald-200"><div class="flex flex-col"><span class="text-emerald-700 flex items-center gap-2 font-semibold text-sm"><CheckCircle class="w-4 h-4" /> Coperta da Credito</span><span class="text-xs text-emerald-600/80 mt-1">Rata saldata col credito pregresso.</span></div><div class="text-right"><span class="text-[10px] uppercase text-emerald-600/70 font-bold block">Da versare</span><span class="font-bold text-xl text-emerald-700">€ 0,00</span></div></div>
                        
                        <div v-else-if="isGeneratingCredit" class="mb-6 flex items-center justify-between p-4 rounded-lg bg-blue-50 border border-blue-200">
                            <div class="flex flex-col">
                                <span class="text-blue-700 flex items-center gap-2 font-semibold text-sm"><Wallet class="w-4 h-4" /> Credito Residuo</span>
                                <span class="text-xs text-blue-600/80 mt-1">Eccedenza dal saldo precedente.</span>
                            </div>
                            <span class="font-bold text-xl text-blue-700">{{ euro(importoRestante) }}</span>
                        </div>

                        <div v-else-if="!isPaid && !isReported && !isRejected" class="mb-6 space-y-4">
                            <div class="flex items-center justify-between p-4 rounded-lg bg-amber-50 border border-amber-200"><span class="text-amber-700 flex items-center gap-2 font-semibold text-sm"><AlertCircle class="w-4 h-4" /> Totale da Versare</span><span class="font-bold text-xl text-amber-700">{{ euro(importoRestante) }}</span></div>
                            
                            <div v-if="!isEmitted">
                                <div class="p-3 rounded-lg bg-slate-100 border border-slate-200 mb-3 flex gap-3 items-start">
                                    <Clock class="w-4 h-4 mt-0.5 text-slate-400" />
                                    <div>
                                        <h4 class="font-bold text-slate-700 text-xs mb-0.5">Il pagamento non è ancora aperto</h4>
                                        <p class="text-[11px] text-slate-500 leading-snug">Non devi fare nulla per ora: il pagamento verrà abilitato a breve.</p>
                                    </div>
                                </div>
                                <Button class="w-full h-10 bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed rounded-lg font-medium hover:bg-slate-100 shadow-none text-xs" disabled>In attesa di emissione...</Button>
                            </div>
                            <div v-else>
                                <Button class="w-full h-12 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm font-semibold rounded-lg" :disabled="isProcessing" @click="reportPayment">{{ isProcessing ? 'Invio...' : 'Ho effettuato il pagamento' }}</Button>
                            </div>
                        </div>

                        <div v-if="isRejected" class="mb-6">
                             <Button variant="destructive" class="w-full h-12 shadow-sm font-semibold rounded-lg" :disabled="isProcessing" @click="reportPayment">{{ isProcessing ? 'Invio...' : 'Riprova Segnalazione' }}</Button>
                        </div>
                    </div>

                    <div v-if="isAdmin && evento.meta?.action_url" class="mb-6">
                        <Button as-child class="w-full h-12 text-white font-semibold shadow-lg rounded-lg" :class="isExpired ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"><a :href="evento.meta.action_url" class="flex items-center justify-center gap-2">{{ isExpired ? 'Emetti Subito' : "Vai all'Emissione" }}<ArrowRight class="w-4 h-4" /></a></Button>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800">
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-line">{{ evento.description }}</p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>