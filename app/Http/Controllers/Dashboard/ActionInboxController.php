<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Anagrafica; 
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Cache;

class ActionInboxController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $filter = $request->input('filter', 'all');
        $counts = $this->getCounts();
        $tasks = $this->getTasks($filter);

        return Inertia::render('dashboard/ActionInbox', [ 
            'tasks'        => $tasks,
            'counts'       => $counts,
            'activeFilter' => $filter,
        ]);
    }

    private function getCounts(): array
    {
        $deadline = now()->endOfDay()->toDateTimeString();
        
        $stats = Evento::query()
            ->whereJsonContains('meta->requires_action', true)
            ->where(fn($q) => $q->where('visibility', '!=', 'private')->orWhereNull('visibility'))
            ->where('is_completed', false)
            ->selectRaw("
                COUNT(*) as all_tasks,
                SUM(CASE WHEN start_time <= ? THEN 1 ELSE 0 END) as urgent,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(meta, '$.type')) = 'verifica_pagamento' THEN 1 ELSE 0 END) as payments,
                SUM(CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(meta, '$.type')) = 'segnalazione_guasto' THEN 1 ELSE 0 END) as maintenance
            ", [$deadline])
            ->first();

        return [
            'all'         => (int) ($stats->all_tasks ?? 0),
            'urgent'      => (int) ($stats->urgent ?? 0),
            'payments'    => (int) ($stats->payments ?? 0),
            'maintenance' => (int) ($stats->maintenance ?? 0),
        ];
    }

    private function getTasks(string $filter)
    {
        $query = Evento::query()
            ->whereJsonContains('meta->requires_action', true)
            ->where(fn($q) => $q->where('visibility', '!=', 'private')->orWhereNull('visibility'))
            ->where('is_completed', false)
            ->with([
                'condomini:id,nome',
                'anagrafiche:id,nome' 
            ]);

        match($filter) {
            'urgent'      => $query->where('start_time', '<=', now()->endOfDay()),
            'payments'    => $query->whereJsonContains('meta->type', 'verifica_pagamento'),
            'maintenance' => $query->whereJsonContains('meta->type', 'segnalazione_guasto'),
            default       => null
        };

        return $query
            ->orderBy('start_time', 'asc')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($task) {
                
                $condominio = $task->condomini->first();
                
                // --- LOGICA RECUPERO NOME ---
                // 1. Proviamo dalla relazione standard
                $nomeAnagrafica = $task->anagrafiche->first()?->nome;

                // 2. Se è null, proviamo a pescarlo dal JSON usando l'ID
                if (!$nomeAnagrafica && !empty($task->meta['context']['anagrafica_id'])) {
                    $anagraficaId = $task->meta['context']['anagrafica_id'];
                    // Recupero "al volo" (poco costoso per pochi record)
                    $anagraficaModel = Anagrafica::find($anagraficaId);
                    if ($anagraficaModel) {
                        $nomeAnagrafica = $anagraficaModel->nome;
                    }
                }
                // -----------------------------

                return [
                    'id'           => $task->id,
                    'title'        => $task->title,
                    'description'  => $task->description, 
                    'date'         => $task->start_time->toISOString(),
                    'condominio'   => $condominio?->nome ?? 'Generale',
                    'type'         => $task->meta['type'] ?? 'generic',
                    'amount'       => $task->meta['importo_dichiarato'] 
                                   ?? $task->meta['totale_rata'] 
                                   ?? null,
                    'status'       => $this->getTaskStatus($task),
                    'context'      => [
                        // Ora qui avrai il nome corretto
                        'anagrafica_nome' => $nomeAnagrafica, 
                        'action_url'      => $task->meta['action_url'] ?? null,
                        'related_id'      => $task->meta['context']['related_event_id'] ?? null,
                    ],
                ];
            });
    }

    private function getTaskStatus(Evento $task): string
    {
        if (($task->meta['type'] ?? '') === 'verifica_pagamento') {
            return 'pending_verification';
        }

        if ($task->start_time->isPast()) {
            return 'expired';
        }

        return 'scheduled';
    }

    /**
     * Rifiuta una segnalazione di pagamento.
     */
    public function reject(Request $request, Evento $task)
    {
        $request->validate([
            'reason' => 'required|string|max:255', 
        ]);

        // 1. Recupera l'evento originale del condòmino (collegato via meta)
        $userEventId = $task->meta['context']['related_event_id'] ?? null;
        
        if ($userEventId) {
            $userEvent = Evento::find($userEventId);
            if ($userEvent) {
                // Aggiorniamo l'evento utente diventa 'rejected'
                $meta = $userEvent->meta;
                $meta['status'] = 'rejected'; // Stato specifico per dire "ci hai provato ma no"
                $meta['rejection_reason'] = $request->input('reason');
                $meta['rejected_at'] = now()->toIso8601String();
                
                $userEvent->update(['meta' => $meta]);
                
                // QUI: Potresti inviare una notifica email al condòmino ($userEvent->created_by)
            }
        }

        // 2. Chiudi il task Admin
        $task->update([
            'is_completed' => true, // O cancellalo se preferisci
            'completed_at' => now(),
        ]);

        // 3. PURGE CACHE: Aggiorniamo subito il contatore della Inbox
        // Usiamo l'ID dell'utente corrente (Admin) che ha appena completato l'azione
        Cache::forget('inbox_count_' . $request->user()->id);

        return back()->with('success', 'Segnalazione rifiutata e condòmino notificato.');
    }
}