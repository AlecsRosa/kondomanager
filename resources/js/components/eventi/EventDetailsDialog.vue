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

// Determina se mostrare la colonna sinistra con dettagli finanziari
const hasFinancialDetails = computed(() => {
    // Mostra se è un evento di rata (admin o condomino)
    if (isAdmin.value || isCondomino.value) return true;
    
    // Mostra se ci sono metadati finanziari (numero_rata, gestione, totale_rata, ecc.)
    const meta = props.evento?.meta;
    if (meta?.numero_rata || meta?.totale_rata || meta?.importo_originale || meta?.gestione) {
        return true;
    }
    
    return false;
});

// --- FIX TYPESCRIPT: Tipizzazione esplicita <number> ---
const importoOriginale = computed<number>(() => Number(props.evento?.meta?.totale_rata || props.evento?.meta?.importo_originale || 0));
const importoRestante = computed<number>(() => props.evento?.meta?.importo_restante !== undefined ? Number(props.evento?.meta?.importo_restante) : importoOriginale.value);
const importoPagato = computed<number>(() => Number(props.evento?.meta?.importo_pagato || 0));
const arretratiPregressi = computed<number>(() => Number(props.evento?.meta?.arretrati_pregressi || 0));
// --- Riferimento Arretrati "Smart" ---
const rifArretrati = computed<string>(() => {
    const raw = props.evento?.meta?.rif_arretrati || '';
    if (!raw) return '';

    // 1. Convertiamo in array e Rimuoviamo duplicati
    const parts = raw.split(', ').filter(Boolean);
    const uniqueParts = [...new Set(parts)]; // Rimuove #1 duplicati

    // 2. Se sono poche (max 4), le mostriamo tutte
    if (uniqueParts.length <= 4) {
        return uniqueParts.join(', ');
    }

    // 3. Se sono tante, mostriamo le prime 3 e il conteggio delle altre
    const firstFew = uniqueParts.slice(0, 2).join(', ');
    const remaining = uniqueParts.length - 2;
    
    return `${firstFew} e altre ${remaining}`;
});

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
                
                <!-- Colonna sinistra: mostrata SOLO se ci sono dettagli finanziari -->
                <div v-if="hasFinancialDetails" class="md:w-[45%] bg-slate-50 dark:bg-slate-900/50 p-8 flex flex-col gap-6 border-r border-slate-100 dark:border-slate-800 overflow-y-auto max-h-[80vh]">
                    
                    <div>
                        <div class="flex flex-row flex-wrap items-center gap-2 mb-6">
                            <Badge variant="outline" :class="[getEventStyle(evento).color, 'border-current bg-white dark:bg-slate-900 shadow-sm px-2 py-0.5 whitespace-nowrap']">
                                <component :is="getEventStyle(evento).icon" class="w-3.5 h-3.5 mr-1.5" /> {{ getEventStyle(evento).label }}
                            </Badge>
                        </div>
                        
                        <div class="mb-0">
                            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Data riferimento</span>
                            <div class="flex items-center gap-2" :class="isExpired ? 'text-red-600 dark:text-red-400' : 'text-slate-700 dark:text-slate-200'">
                                <CalendarDays class="w-5 h-5" :class="isExpired ? 'text-red-400' : 'text-slate-400'" />
                                <span class="text-lg font-medium capitalize">{{ formatDate(evento.start_time) }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                         <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1"> 
                             {{ isAdmin ? 'Totale emissione' : (isGeneratingCredit ? 'Importo a credito' : 'Totale da versare') }} 
                         </span>
                        
                        <span class="text-3xl font-bold tracking-tight block tabular-nums" 
                              :class="isGeneratingCredit ? 'text-blue-600 dark:text-blue-400' : (isFullyCoveredByCredit ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white')"> 
                            {{ euro(isFullyCoveredByCredit ? 0 : importoRestante, { forcePlus: false }) }} 
                        </span>

                        <div v-if="scontrinoData.length > 0" class="mt-6 pt-6 border-t border-slate-200 dark:border-slate-800 space-y-6">
                            
                            <div class="flex flex-col gap-2 mb-2">
                                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Dettaglio copertura / Utilizzo credito</p>
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
                                                    {{ item.credito_disponibile < 0 ? 'Credito disp.:' : 'Saldo prog.:' }}
                                                </span>
                                                <span class="font-mono">{{ euro(item.credito_disponibile) }}</span>
                                            </div>

                                            <div class="flex justify-between items-center text-slate-900 dark:text-white font-medium">
                                                <span class="pl-3">Quota rata:</span>
                                                <span class="font-mono text-slate-700 dark:text-slate-300">
                                                    {{ euro(item.quota_rata, { forcePlus: true }) }}
                                                </span>
                                            </div>

                                            <div class="border-t border-slate-100 dark:border-slate-700 pt-1.5 mt-1 flex justify-between items-center">
                                                <span class="text-xs font-bold uppercase text-slate-400">Nuovo saldo:</span>
                                                <span class="font-mono font-bold" :class="item.nuovo_saldo < -0.01 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-900 dark:text-white'">
                                                    {{ euro(item.nuovo_saldo) }}
                                                </span>
                                            </div>
                                            
                                            <div v-if="item.is_credito" class="text-right text-xs text-emerald-600 dark:text-emerald-500 italic">
                                                (Sei ancora a credito)
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4 bg-slate-50/50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-800 -mx-2 px-3 py-3 rounded">
                                
                                <div v-if="importoPagato > 0.01 && !isGeneratingCredit && !isFullyCoveredByCredit" class="flex flex-col gap-1 mb-2 pb-2 border-b border-slate-200 dark:border-slate-700 text-xs">
                                    <div class="flex justify-between text-slate-500 dark:text-slate-400">
                                        <span>Totale rata</span>
                                        <span>{{ euro(importoOriginale) }}</span>
                                    </div>
                                    <div class="flex justify-between text-emerald-600 dark:text-emerald-500 font-medium">
                                        <span class="flex items-center gap-1"><CheckCircle class="w-3 h-3" /> Già versato</span>
                                        <span>- {{ euro(importoPagato) }}</span>
                                    </div>
                                </div>

                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-sm text-slate-900 dark:text-white">Netto da pagare</span>
                                    <span class="text-xl font-bold font-mono tracking-tight" 
                                          :class="isFullyCoveredByCredit ? 'text-emerald-600' : (isGeneratingCredit ? 'text-blue-600' : 'text-slate-900 dark:text-white')"> 
                                        {{ euro(isFullyCoveredByCredit ? 0 : importoRestante, { forcePlus: false }) }} 
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div :class="hasFinancialDetails ? 'md:w-[55%]' : 'w-full'" class="p-6 flex flex-col relative overflow-y-auto max-h-[80vh]">
                    
                    <!-- Badge e data per eventi generici (quando non c'è la colonna sinistra) -->
                    <div v-if="!hasFinancialDetails" class="mb-4">
                        <div class="flex flex-wrap items-center gap-3 mb-4">
                            <Badge variant="outline" :class="[getEventStyle(evento).color, 'border-current bg-white dark:bg-slate-900 shadow-sm px-2.5 py-1 whitespace-nowrap']">
                                <component :is="getEventStyle(evento).icon" class="w-3.5 h-3.5 mr-1.5" /> {{ getEventStyle(evento).label }}
                            </Badge>
                            
                            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-400">
                                <CalendarDays class="w-4 h-4" />
                                <span class="text-sm font-medium capitalize">{{ formatDate(evento.start_time) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <h2 class="text-xl font-bold pr-10 leading-tight flex items-start gap-2" :class="[isExpired ? 'text-red-600 dark:text-red-500' : 'text-slate-900 dark:text-white', hasFinancialDetails ? 'mb-6' : 'mb-3']"> <AlertTriangle v-if="isExpired" class="w-6 h-6 shrink-0" /> {{ evento.title }} </h2>
                    
                    <div v-if="isRejected" class="mb-3 p-4 rounded-lg bg-red-50 border border-red-100"><div class="flex items-start gap-3"><XCircle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" /><div><h4 class="font-bold text-red-700 text-sm">Pagamento rifiutato</h4><div class="bg-white p-2.5 rounded text-xs text-red-800 font-medium border border-red-200/50 italic mt-2"> "{{ evento.meta?.rejection_reason }}" </div><p class="text-xs text-red-500 mt-2">Verifica i dati e riprova.</p></div></div></div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                        <div v-if="evento.meta?.condominio_nome" class="bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 p-3 rounded-lg min-w-0">
                            <span class="text-[10px] uppercase font-semibold text-slate-400 mb-1 flex items-center gap-1.5">
                                <Building2 class="w-3.5 h-3.5" /> Condominio
                            </span>
                            <p class="font-medium text-sm text-slate-900 dark:text-white truncate" :title="evento.meta.condominio_nome">
                                {{ evento.meta.condominio_nome }}
                            </p>
                        </div>
                        <div v-if="evento.meta?.gestione" class="bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 p-3 rounded-lg min-w-0">
                            <span class="text-[10px] uppercase font-semibold text-slate-400 mb-1 flex items-center gap-1.5">
                                <Wallet class="w-3.5 h-3.5" /> Gestione
                            </span>
                            <p class="font-medium text-sm text-slate-900 dark:text-white truncate" :title="evento.meta.gestione">
                                {{ evento.meta.gestione }}
                            </p>
                        </div>
                        <div v-if="evento.meta?.numero_rata" class="bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-800 p-3 rounded-lg min-w-0">
                            <span class="text-[10px] uppercase font-semibold text-slate-400 mb-1 flex items-center gap-1.5">
                                <Banknote class="w-3.5 h-3.5" /> Rata
                            </span>
                            <p class="font-medium text-sm text-slate-900 dark:text-white truncate">
                                Numero {{ evento.meta.numero_rata }}
                            </p>
                        </div>
                    </div>

                    <div v-if="isCondomino">
                        
                        <div v-if="arretratiPregressi > 0.01" class="mb-3 p-4 rounded-lg bg-orange-50 border border-orange-200 flex items-start gap-3">
                            <div class="p-1.5 bg-orange-100 rounded-full text-orange-600 shrink-0 mt-0.5">
                                <AlertTriangle class="w-4 h-4" />
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-orange-800 mb-1">Attenzione: rate precedenti insolute</h4>
                                <p class="text-xs text-orange-700 leading-relaxed mb-2">
                                    Risultano arretrati non saldati per un totale di 
                                    <span class="font-bold">{{ euro(arretratiPregressi) }}</span>
                                    <span v-if="rifArretrati"> (rif. rate {{ rifArretrati }})</span>.
                                </p>
                                <p class="text-xs text-orange-600/80">
                                    L'importo qui sotto si riferisce solo alla rata corrente. 
                                    Per regolarizzare la situazione, verifica le rate scadute precedenti, effettua il pagamento e segnalalo.
                                </p>
                            </div>
                        </div>

                        <div v-if="isPartiallyCoveredByCredit" class="mb-6">
                            <div class="flex items-center justify-between p-4 rounded-lg bg-indigo-50 border border-indigo-200 mb-4">
                                <div class="flex flex-col">
                                    <span class="text-indigo-700 flex items-center gap-2 font-semibold text-sm"><RotateCcw class="w-4 h-4" /> Parzialmente coperta</span>
                                    <span class="text-xs text-indigo-600/80 mt-1">Il credito ha coperto {{ euro(importoOriginale - importoRestante) }}.</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between p-4 rounded-lg bg-amber-50 border border-amber-200 mb-4">
                                <span class="text-amber-700 flex items-center gap-2 font-semibold text-sm"><AlertCircle class="w-4 h-4" /> Resta da versare</span>
                                <span class="font-bold text-xl text-amber-700">{{ euro(importoRestante) }}</span>
                            </div>

                            <div v-if="isReported">
                                <Button class="w-full h-12 bg-amber-100 text-amber-700 border border-amber-200 cursor-not-allowed rounded-lg font-medium shadow-none text-xs" disabled>
                                    Saldo inviato - In attesa di conferma...
                                </Button>
                            </div>
                            <div v-else-if="!isEmitted">
                                <div class="p-3 rounded-lg bg-slate-100 border border-slate-200 mb-3 flex gap-3 items-start">
                                    <Clock class="w-4 h-4 mt-0.5 text-slate-400" />
                                    <div>
                                        <h4 class="font-bold text-slate-700 text-xs mb-0.5">Rata in attesa di emissione</h4>
                                        <p class="text-xs text-slate-500 leading-snug">
                                            L'amministratore non ha ancora abilitato i versamenti per questa scadenza. 
                                            Potrai registrare il pagamento nei prossimi giorni.
                                        </p>
                                    </div>
                                </div>
                                <Button class="w-full h-10 bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed rounded-lg font-medium hover:bg-slate-100 shadow-none text-xs" disabled>
                                    Pagamento non ancora attivo
                                </Button>
                            </div>
                            <div v-else><Button class="w-full h-12 bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm font-semibold rounded-lg" :disabled="isProcessing" @click="reportPayment">{{ isProcessing ? 'Invio...' : 'Segnala pagamento saldo' }}</Button></div>
                        </div>

                        <div v-else-if="isFullyCoveredByCredit" class="mb-3 flex items-center justify-between p-4 rounded-lg bg-emerald-50 border border-emerald-200"><div class="flex flex-col"><span class="text-emerald-700 flex items-center gap-2 font-semibold text-sm"><CheckCircle class="w-4 h-4" /> Coperta da Credito</span><span class="text-xs text-emerald-600/80 mt-1">Rata saldata col credito pregresso.</span></div><div class="text-right"><span class="text-xs uppercase text-emerald-600/70 font-bold block">Da versare</span><span class="font-bold text-xl text-emerald-700">€ 0,00</span></div></div>
                        
                        <div v-else-if="isGeneratingCredit" class="mb-3 flex items-center justify-between p-4 rounded-lg bg-blue-50 border border-blue-200">
                            <div class="flex flex-col">
                                <span class="text-blue-700 flex items-center gap-2 font-semibold text-sm"><Wallet class="w-4 h-4" /> Credito residuo</span>
                                <span class="text-xs text-blue-600/80 mt-1">Eccedenza dal saldo precedente.</span>
                            </div>
                            <span class="font-bold text-xl text-blue-700">{{ euro(importoRestante) }}</span>
                        </div>

                        <div v-else-if="isReported" class="mb-6">
                            <div class="p-4 rounded-lg bg-amber-50 border border-amber-200 mb-4 flex gap-3 items-start">
                                <div class="p-1.5 bg-amber-100 rounded-full text-amber-600 shrink-0 mt-0.5">
                                   <Clock class="w-4 h-4" />
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-amber-800 mb-1">Pagamento in verifica</h4>
                                    <p class="text-xs text-amber-700 leading-relaxed">
                                        Hai segnalato di aver effettuato il pagamento. L'amministratore sta verificando l'incasso. Riceverai una notifica a conferma avvenuta.
                                    </p>
                                </div>
                            </div>
                            <Button class="w-full h-12 bg-amber-100 text-amber-700 border border-amber-200 cursor-not-allowed rounded-lg font-medium shadow-none text-xs" disabled>
                                In attesa di conferma...
                            </Button>
                        </div>

                        <div v-else-if="!isPaid && !isReported && !isRejected" class="mb-6 space-y-4">
                            <div class="flex items-center justify-between p-3 rounded-lg bg-amber-50 border border-amber-200">
                                <span class="text-amber-700 flex items-center gap-2 font-semibold text-sm"><AlertCircle class="w-4 h-4" /> Totale da versare</span>
                                <span class="font-bold text-xl text-amber-700">{{ euro(importoRestante) }}</span>
                            </div>
                            
                            <div v-if="!isEmitted">
                                <div class="p-3 rounded-lg bg-slate-100 border border-slate-200 mb-3 flex gap-3 items-start">
                                    <Clock class="w-4 h-4 mt-0.5 text-slate-500" />
                                    <div>
                                        <h4 class="font-bold text-slate-700 text-xs mb-0.5">Rata in attesa di emissione</h4>
                                        <p class="text-xs text-slate-500 leading-snug">
                                            L'amministratore non ha ancora abilitato i versamenti per questa scadenza. 
                                            Potrai registrare il pagamento nei prossimi giorni.
                                        </p>
                                    </div>
                                </div>
                                <Button class="w-full h-10 bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed rounded-lg font-medium hover:bg-slate-100 shadow-none text-xs" disabled>
                                    Pagamento non ancora attivo
                                </Button>
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
                        <Button as-child class="w-full h-12 text-white font-semibold shadow-lg rounded-lg" :class="isExpired ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700'"><a :href="evento.meta.action_url" class="flex items-center justify-center gap-2">{{ isExpired ? 'Emetti subito' : "Vai all'emissione" }}<ArrowRight class="w-4 h-4" /></a></Button>
                    </div>

                    <div v-if="evento.description" :class="hasFinancialDetails ? 'mt-3 pt-3 border-t border-slate-100 dark:border-slate-800' : ''">
                        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-line">{{ evento.description }}</p>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>