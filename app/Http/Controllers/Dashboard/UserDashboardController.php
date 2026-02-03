<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Comunicazioni\ComunicazioneResource;
use App\Http\Resources\Documenti\DocumentoResource;
use App\Http\Resources\Evento\EventoResource;
use App\Http\Resources\Segnalazioni\SegnalazioneResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\SegnalazioneService;
use App\Services\ComunicazioneService;
use App\Services\DocumentoService;
use App\Services\RecurrenceService;
use Inertia\Response;
use App\Traits\CalculatesFinancialWaterfall;

class UserDashboardController extends Controller
{
    use CalculatesFinancialWaterfall; 
    
    public function __construct(
        private SegnalazioneService $segnalazioneService,
        private ComunicazioneService $comunicazioneService,
        private DocumentoService $documentoService,
        private RecurrenceService $recurrenceService
    ) {}
    
    public function __invoke(Request $request): Response
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->anagrafica) {
                abort(403, __('auth.not_authenticated'));
            }

            $anagrafica = $user->anagrafica;
            $condominioIds = $anagrafica->condomini->pluck('id');
            
            $segnalazioni = $this->segnalazioneService->getSegnalazioni(
                anagrafica: $anagrafica,
                condominioIds: $condominioIds,
                validated: [],
                limit: 3 
            );

            $comunicazioni = $this->comunicazioneService->getComunicazioni(
                anagrafica: $anagrafica,
                condominioIds: $condominioIds,
                validated: [],
                limit: 3 
            );

            $documenti = $this->documentoService->getDocumenti(
                anagrafica: $anagrafica,
                condominioIds: $condominioIds,
                validated: [],
                limit: 3
            );

            // Fetch raw events
            $eventiCollection = $this->recurrenceService->getEventsInNextDays(
                days: 30,
                anagrafica: $anagrafica,
                condominioIds: $condominioIds
            );

            // 3. APPLICAZIONE WATERFALL (DASHBOARD)
            $eventiProcessati = $this->applyFinancialWaterfall(
                $eventiCollection, 
                $anagrafica->id
            );
            
            $eventiLimited = $eventiProcessati->take(50);

        } catch (\Exception $e) {
            Log::error('Error getting dashboard widgets: ' . $e->getMessage());
            abort(500, 'Unable to fetch reports.');
        }

        return Inertia::render('dashboard/UserDashboard', [
            'segnalazioni'  => SegnalazioneResource::collection($segnalazioni),
            'comunicazioni' => ComunicazioneResource::collection($comunicazioni),
            'eventi'        => EventoResource::collection($eventiLimited),
            'documenti'     => DocumentoResource::collection($documenti),
        ]);
    }
}