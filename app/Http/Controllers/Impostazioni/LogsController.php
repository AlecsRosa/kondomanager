<?php

namespace App\Http\Controllers\Impostazioni;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        return Inertia::render('impostazioni/impostazioniLogs', [
            'filters' => $request->only(['search']),
            
            // TAB 1: Mail Logs
            'mailLogs' => MailLog::query()
                ->when($search, function ($query, $search) {
                    $query->where('recipient', 'like', "%{$search}%")
                          ->orWhere('subject', 'like', "%{$search}%");
                })
                ->latest('sent_at')
                // 'mail_page' è la chiave per non confondere la paginazione con l'altro tab
                ->paginate(10, ['*'], 'mail_page')
                ->withQueryString(),

            // TAB 2: Activity Logs (Spatie)
            'activityLogs' => ''
           /*  'activityLogs' => Activity::query()
                ->with('causer') // Carica l'utente che ha fatto l'azione
                ->when($search, function ($query, $search) {
                    $query->where('description', 'like', "%{$search}%")
                          ->orWhereHas('causer', function ($q) use ($search) {
                              $q->where('name', 'like', "%{$search}%"); // Assumendo che User abbia 'name'
                          });
                })
                ->latest()
                ->paginate(10, ['*'], 'activity_page')
                ->withQueryString(), */
        ]);
    }
}
