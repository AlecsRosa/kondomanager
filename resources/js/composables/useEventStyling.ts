import { 
    ClockAlert, 
    ClockArrowUp,
    CheckCircle2, 
    AlertCircle, 
    ArrowUpFromLine, 
    ArrowDownToLine, 
    AlertTriangle,
    XCircle,
    CalendarDays,
    Info 
} from 'lucide-vue-next';

export function useEventStyling() {

    const getDaysRemaining = (dateInput: string | Date | null | undefined): number => {
        if (!dateInput) return 0;
        const now = new Date();
        const target = new Date(dateInput);
        
        now.setHours(0, 0, 0, 0);
        target.setHours(0, 0, 0, 0);

        if (isNaN(target.getTime())) return 0;
        
        const msPerDay = 1000 * 60 * 60 * 24;
        return Math.floor((target.getTime() - now.getTime()) / msPerDay);
    };

    const getEventStyle = (evento: any) => {
        const meta = evento.meta || {};
        const type = meta.type || 'default';
        const status = meta.status || 'pending';
        const requiresAction = meta.requires_action || false;
        
        const isCredit = (meta.importo_restante || 0) < 0;
        
        const dataRiferimento = evento.start_time || evento.occurs || evento.occurs_at;
        const days = getDaysRemaining(dataRiferimento);

        // --- 1. PRIORITÀ AL TIPO ADMIN (Emissione Rata) ---
        if (type === 'emissione_rata') {
            if (days <= 0) {
                return {
                    color: 'text-red-700 dark:text-red-500 font-bold',
                    bgColor: 'bg-red-50 dark:bg-red-900/20',
                    borderColor: 'border-red-200 dark:border-red-800',
                    icon: AlertTriangle,
                    label: 'Scaduto e da emettere'
                };
            }
            
            return {
                color: 'text-blue-600 dark:text-blue-400',
                bgColor: 'bg-blue-50 dark:bg-blue-900/20',
                borderColor: 'border-blue-200 dark:border-blue-800',
                icon: ArrowUpFromLine, 
                label: 'Da emettere'
            };
        }

        // --- 2. NUOVO BLOCCO: CONTROLLO INCASSI (Admin) ---
        if (type === 'controllo_incassi') {
            // Se sono passati giorni dalla data prevista per il controllo
            if (days < 0) {
                return {
                    color: 'text-red-700 dark:text-red-500 font-bold',
                    bgColor: 'bg-red-50 dark:bg-red-900/20',
                    borderColor: 'border-red-200 dark:border-red-800',
                    icon: AlertCircle,
                    label: 'Verifica urgente incassi'
                };
            }
            
            // VIOLA + FRECCIA IN ENTRATA
            return {
                color: 'text-purple-600 dark:text-purple-400', 
                bgColor: 'bg-purple-50 dark:bg-purple-900/20',
                borderColor: 'border-purple-200 dark:border-purple-800',
                icon: ArrowDownToLine,
                label: 'Verifica incassi'
            };
        }

        // --- 3. PRIORITÀ ALLO STATO (User - Scadenza Rata Condomino) ---
        
        // RIFIUTATO
        if (status === 'rejected') {
            return {
                color: 'text-red-600 dark:text-red-400 font-bold',
                bgColor: 'bg-red-50 dark:bg-red-900/20',
                borderColor: 'border-red-200 dark:border-red-800',
                icon: XCircle,
                label: 'Pagamento rifiutato'
            };
        }

        // PAGATO (Verde)
        if (status === 'paid') {
            return {
                color: 'text-emerald-600 dark:text-emerald-400',
                bgColor: 'bg-emerald-50 dark:bg-emerald-900/20',
                borderColor: 'border-emerald-200 dark:border-emerald-800',
                icon: CheckCircle2,
                label: 'Pagato'
            };
        }

        // PARZIALE (Arancione)
        if (status === 'partial') {
            return {
                color: 'text-orange-600 dark:text-orange-400',
                bgColor: 'bg-orange-50 dark:bg-orange-900/20',
                borderColor: 'border-orange-200 dark:border-orange-800',
                icon: ClockArrowUp, 
                label: 'Pagato parzialmente'
            };
        }
        
        // A CREDITO
        if (isCredit) {
            return {
                color: 'text-blue-600 dark:text-blue-400',
                bgColor: 'bg-blue-50 dark:bg-blue-900/20',
                borderColor: 'border-blue-200 dark:border-blue-800',
                icon: Info,
                label: 'A credito'
            };
        }
        
        // IN VERIFICA
        if (status === 'reported' || requiresAction) {
            return {
                color: 'text-amber-600 dark:text-amber-400',
                bgColor: 'bg-amber-50 dark:bg-amber-900/20',
                borderColor: 'border-amber-200 dark:border-amber-800',
                icon: AlertCircle,
                label: 'In verifica'
            };
        }
        
        // --- 4. URGENZA GENERICA ---
        if (days < 0) {
            return {
                color: 'text-red-700 dark:text-red-500 font-bold',
                bgColor: 'bg-red-100 dark:bg-red-900/30',
                borderColor: 'border-red-300 dark:border-red-700',
                icon: ClockAlert,
                label: 'Scaduto'
            };
        } else if (days <= 7) {
            return {
                color: 'text-red-500 dark:text-red-400',
                bgColor: 'bg-red-50 dark:bg-red-900/20',
                borderColor: 'border-red-200 dark:border-red-800',
                icon: ClockAlert,
                label: `Scade tra ${days} giorni`
            };
        } else if (days <= 14) {
            return {
                color: 'text-yellow-500 dark:text-yellow-400',
                bgColor: 'bg-yellow-50 dark:bg-yellow-900/20',
                borderColor: 'border-yellow-200 dark:border-yellow-800',
                icon: ClockArrowUp,
                label: `Scade tra ${days} giorni`
            };
        } else {
            return {
                color: 'text-blue-600 dark:text-blue-400', 
                bgColor: 'bg-blue-50 dark:bg-blue-900/20',
                borderColor: 'border-blue-200 dark:border-blue-800',
                icon: CalendarDays,
                label: `Tra ${days} giorni`
            };
        }
    };

    return { getEventStyle };
}