import { ref } from 'vue';
import type { Rata, BilancioFinale, PreviewContabile } from '@/types/gestionale/rata';

export function usePaymentDistribution() {
    const rawRateList = ref<Rata[]>([]);
    const loadingRate = ref(false);
    const mode = ref<'auto' | 'manual'>('auto');
    const priorityRataId = ref<number | null>(null);

    // HELPER
    const parseDate = (dateStr: string | null) => {
        if (!dateStr) return '9999-12-31';
        if (dateStr.includes('/')) { const [d, m, y] = dateStr.split('/'); return `${y}-${m}-${d}`; }
        return dateStr;
    };
    // Helper robusto per parsare qualsiasi cosa arrivi dal backend
    const parseMoney = (val: any) => { 
        if (typeof val === 'number') return val;
        if (!val) return 0;
        return parseFloat(String(val).replace(/[^\d.-]/g, '')) || 0; 
    };
    
    const setPriorityRataId = (id: number | null) => { priorityRataId.value = id; };
    const isScaduta = (data: string | null) => { if (!data) return false; return new Date(data) < new Date(new Date().toDateString()); };

    const getRateListByGestione = (gestioneId: number | null) => {
        if (!rawRateList.value) return [];
        
        let list = [...rawRateList.value];
        if (gestioneId) {
            list = list.filter(r => r.gestione_id === gestioneId);
        }

        const cronologicalSort = (a: Rata, b: Rata) => {
            const dateA = new Date(parseDate(a.scadenza_human || a.data_scadenza)).getTime();
            const dateB = new Date(parseDate(b.scadenza_human || b.data_scadenza)).getTime();
            if (dateA !== dateB) return dateA - dateB;
            return (a.id || 0) - (b.id || 0);
        };
        list.sort(cronologicalSort);

        // --- LOGICA WATERFALL (SpaceX Corrected) ---
        
        let creditoDisponibilePerIlFuturo = 0;
        list.forEach(r => {
            const val = parseMoney(r.residuo);
            if (val < 0) creditoDisponibilePerIlFuturo += Math.abs(val);
        });
        
        list = list.map(r => {
            const rata = { ...r };
            if (r.dettaglio_quote) {
                rata.dettaglio_quote = r.dettaglio_quote.map((q: any) => ({...q}));
            }
            const residuoRata = parseMoney(r.residuo);

            // --- CASO A: RATA A CREDITO (Es. Rata -66€) ---
            if (residuoRata < 0) {
                let saldoInizialeReale = 0;
                let usiamoDatiJson = false;

                if (rata.dettaglio_quote) {
                    saldoInizialeReale = rata.dettaglio_quote.reduce((sum: number, q: any) => sum + parseMoney(q.componente_saldo), 0);
                    if (saldoInizialeReale < 0) usiamoDatiJson = true;
                }

                let saldoScorrevoleInterno = 0;
                if (usiamoDatiJson) {
                    saldoScorrevoleInterno = saldoInizialeReale; 
                } else {
                    let costiTotaliRata = 0;
                    if (rata.dettaglio_quote) {
                        costiTotaliRata = rata.dettaglio_quote.reduce((sum: number, q: any) => {
                            // Qui usiamo componente_spesa o residuo_originale come fallback per il costo puro
                            const costo = parseMoney(q.componente_spesa) || parseMoney(q.residuo_originale) || 0;
                            return sum + Math.abs(costo);
                        }, 0);
                    }
                    saldoScorrevoleInterno = residuoRata - costiTotaliRata;
                }

                if (rata.dettaglio_quote) {
                    rata.dettaglio_quote = rata.dettaglio_quote.map((q: any) => {
                        const costo = parseMoney(q.componente_spesa) || 0;
                        q.waterfall_start = parseFloat(saldoScorrevoleInterno.toFixed(2));
                        q.waterfall_cost = costo;
                        saldoScorrevoleInterno += costo;
                        q.waterfall_end = parseFloat(saldoScorrevoleInterno.toFixed(2));
                        return q;
                    });
                }
                
                rata.residuo = residuoRata; 
            }
            
            // --- CASO B: RATA A DEBITO (Es. Rata +105€ o +33€) ---
            else if (residuoRata > 0) {
                
                // 1. FIX CRUCIALE: Calcolo del Debito Pregresso Implicito
                // Dobbiamo sottrarre al totale residuo SOLO la componente di spesa pura (la quota corrente)
                // Se non abbiamo componente_spesa, usiamo residuo come fallback (ma per Cecilia abbiamo componente_spesa!)
                
                let sommaQuotePure = 0;
                if (rata.dettaglio_quote) {
                    sommaQuotePure = rata.dettaglio_quote.reduce((sum: number, q: any) => {
                        // Se c'è componente_spesa usiamo quella (es. 5.85), altrimenti residuo (es. 105.85)
                        const quotaPura = parseMoney(q.componente_spesa);
                        return sum + (quotaPura !== 0 ? quotaPura : parseMoney(q.residuo));
                    }, 0);
                }
                
                // Es: 105.85 - 5.85 = 100.00 (Debito Pregresso)
                // Es: 33.29 - 33.29 = 0.00 (Nessun debito pregresso)
                let debitoPregressoImplicito = Math.max(0, residuoRata - sommaQuotePure);

                // 2. Definiamo il "Saldo Contabile Attuale" (Start)
                let saldoContabileAttuale = debitoPregressoImplicito - creditoDisponibilePerIlFuturo;

                // 3. Waterfall Interna
                if (rata.dettaglio_quote) {
                    rata.dettaglio_quote = rata.dettaglio_quote.map((q: any) => {
                        // FIX: Il "costo" dello step waterfall è la QUOTA PURA, non il residuo totale
                        let costoStep = parseMoney(q.componente_spesa);
                        if (costoStep === 0) costoStep = parseMoney(q.residuo); // Fallback se manca dato
                        
                        q.waterfall_start = parseFloat(saldoContabileAttuale.toFixed(2));
                        q.waterfall_cost = costoStep;
                        
                        saldoContabileAttuale += costoStep;
                        
                        q.waterfall_end = parseFloat(saldoContabileAttuale.toFixed(2));
                        
                        // Calcolo Copertura per UI (Rimane basato sul residuo totale per coerenza di pagamento)
                        const residuoTotaleQuota = parseMoney(q.residuo);
                        
                        if (q.waterfall_end <= 0.01) {
                            q.coperta_da_waterfall = residuoTotaleQuota;
                            q.residuo_originale = residuoTotaleQuota;
                            q.residuo = 0;
                        } else {
                            if (q.waterfall_start < 0) {
                                const coperto = Math.abs(q.waterfall_start);
                                q.coperta_da_waterfall = coperto;
                                q.residuo_originale = residuoTotaleQuota;
                                q.residuo = parseFloat((residuoTotaleQuota - coperto).toFixed(2));
                            } else {
                                q.coperta_da_waterfall = 0;
                                q.residuo_originale = residuoTotaleQuota;
                                q.residuo = residuoTotaleQuota;
                            }
                        }
                        return q;
                    });
                }

                // 4. Aggiornamento Rata Madre e Credito Globale
                if (saldoContabileAttuale < 0) {
                    rata.residuo_originale = residuoRata;
                    rata.residuo = 0;
                    rata.coperta_da_credito = true;
                    creditoDisponibilePerIlFuturo = Math.abs(saldoContabileAttuale);
                } else {
                    if (creditoDisponibilePerIlFuturo > 0) {
                        rata.residuo_originale = residuoRata;
                        rata.residuo = parseFloat(saldoContabileAttuale.toFixed(2));
                        rata.parzialmente_coperta = true;
                        creditoDisponibilePerIlFuturo = 0;
                    } else {
                        rata.residuo = residuoRata;
                        creditoDisponibilePerIlFuturo = 0;
                    }
                }
            } else {
                rata.residuo = residuoRata; 
            }

            return rata;
        });

        // 3. Ordinamento Finale
        const uiSort = (a: Rata, b: Rata) => {
            const dateA = new Date(parseDate(a.scadenza_human || a.data_scadenza)).getTime();
            const dateB = new Date(parseDate(b.scadenza_human || b.data_scadenza)).getTime();
            if (dateA !== dateB) return dateA - dateB;
            const idRataA = a.rata_padre_id || a.rata_id || 0;
            const idRataB = b.rata_padre_id || b.rata_id || 0;
            if (idRataA !== idRataB) return idRataA - idRataB;
            return Number(b.residuo) - Number(a.residuo);
        };

        if (priorityRataId.value) {
            list.sort((a, b) => { 
                const pId = Number(priorityRataId.value);
                const aId = Number(a.id); const bId = Number(b.id);
                const aIsPriority = (aId === pId) || (a.rata_padre_id === pId);
                const bIsPriority = (bId === pId) || (b.rata_padre_id === pId);
                if (aIsPriority && !bIsPriority) return -1;
                if (!aIsPriority && bIsPriority) return 1;
                return uiSort(a, b);
            });
        } else {
            list.sort(uiSort);
        }

        return list;
    };

    // --- GETTERS (Invariati) ---
    const getTotalAllocato = (rateList: Rata[]) => rateList.reduce((sum, r) => sum + (parseFloat(String(r.da_pagare)) || 0), 0);
    const getTotaleDebito = (rateList: Rata[]) => rateList.reduce((sum, r) => sum + (Number(r.residuo) || 0), 0);
    const getBilancioFinale = (totaleDebito: number, importoTotale: number): BilancioFinale => {
        const differenza = totaleDebito - importoTotale;
        if (differenza > 0.01) return { label: 'Residuo:', value: differenza, class: 'text-red-600 bg-red-50 border-red-200' };
        else if (differenza < -0.01) return { label: 'Credito:', value: Math.abs(differenza), class: 'text-emerald-600 bg-emerald-50 border-emerald-200' };
        else return { label: 'Saldo:', value: 0, class: 'text-gray-600 bg-gray-50 border-gray-200' };
    };
    const getPreviewContabile = (rateList: Rata[], importoTotale: number, eccedenza: number): PreviewContabile => {
        const rateCoinvolte = rateList.filter(r => r.selezionata && r.da_pagare > 0);
        return {
            hasData: importoTotale > 0,
            totale_versato: importoTotale,
            allocato_rate: getTotalAllocato(rateList),
            anticipo: eccedenza,
            righe: rateCoinvolte.map(r => {
                const residuoDopoPagamento = Number(r.residuo) - r.da_pagare;
                return {
                    id: r.id, descrizione: r.descrizione, pagato: r.da_pagare,
                    status: residuoDopoPagamento < 0.01 ? 'SALDATA' : 'PARZIALE',
                    residuo_futuro: Math.max(0, residuoDopoPagamento)
                };
            })
        };
    };
    const distributeGreedy = (rateList: Rata[], importoTotale: number) => {
        let budget = parseFloat(String(importoTotale)) || 0;
        rateList.forEach(r => {
            if (Number(r.residuo) <= 0) { r.da_pagare = 0; r.selezionata = false; return; }
            const allocabile = Math.min(budget, Number(r.residuo));
            r.da_pagare = parseFloat(allocabile.toFixed(2));
            r.selezionata = r.da_pagare > 0;
            budget -= r.da_pagare;
            if (budget < 0.01) budget = 0;
        });
        return parseFloat(budget.toFixed(2));
    };
    const calculateExcess = (rateList: Rata[], importoTotale: number) => {
        const tot = parseFloat(String(importoTotale)) || 0;
        const alloc = rateList.reduce((s, r) => s + (parseFloat(String(r.da_pagare)) || 0), 0);
        return Math.max(0, parseFloat((tot - alloc).toFixed(2)));
    };
    const onManualChange = (rata: Rata, val: string) => {
        if (mode.value === 'auto') return;
        let amount = parseFloat(val) || 0;
        if (amount > Number(rata.residuo)) amount = Number(rata.residuo);
        rata.da_pagare = amount; rata.selezionata = amount > 0;
    };
    const resetAllocation = (rateList: Rata[]) => { mode.value = 'manual'; rateList.forEach(r => { r.da_pagare = 0; r.selezionata = false; }); };
    const pagaTutto = (rateList: Rata[]) => { mode.value = 'manual'; let somma = 0; rateList.forEach(r => { if (Number(r.residuo) > 0) { r.da_pagare = Number(r.residuo); r.selezionata = true; somma += Number(r.residuo); } else { r.da_pagare = 0; r.selezionata = false; } }); return parseFloat(somma.toFixed(2)); };
    const pagaScadute = (rateList: Rata[]) => { mode.value = 'manual'; let somma = 0; rateList.forEach(r => { if (r.scaduta && Number(r.residuo) > 0) { r.da_pagare = Number(r.residuo); r.selezionata = true; somma += Number(r.residuo); } else { r.da_pagare = 0; r.selezionata = false; } }); return parseFloat(somma.toFixed(2)); };
    const syncFormData = (rateList: Rata[]) => rateList.filter(r => r.selezionata && r.da_pagare > 0).map(r => ({ rata_id: r.id, importo: r.da_pagare }));

    return { 
        rawRateList, 
        loadingRate,
         mode, 
         isScaduta, 
         setPriorityRataId, 
         getRateListByGestione, 
         getTotalAllocato, 
         getTotaleDebito, 
         getBilancioFinale, 
         getPreviewContabile, 
         distributeGreedy, 
         calculateExcess, 
         onManualChange, 
         resetAllocation, 
         pagaTutto, 
         pagaScadute, 
         syncFormData 
        };
}