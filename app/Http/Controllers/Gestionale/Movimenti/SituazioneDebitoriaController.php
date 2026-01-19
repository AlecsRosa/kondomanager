<?php

namespace App\Http\Controllers\Gestionale\Movimenti;

use App\Http\Controllers\Controller;
use App\Models\Condominio;
use App\Models\Gestionale\RataQuote; 
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SituazioneDebitoriaController extends Controller
{
    public function __invoke(Request $request, Condominio $condominio): JsonResponse
    {
        // 1. Base Query
        $query = RataQuote::query()
            ->whereHas('rata', function($q) use ($condominio) {
                $q->whereHas('pianoRate', fn($p) => $p->where('condominio_id', $condominio->id));
            });

        // 2. Filtro Logico: Debiti Aperti o Crediti
        $query->where(function($q) {
            $q->whereRaw('importo > importo_pagato') 
              ->orWhere('importo', '<', 0);          
        });

        // 3. Filtri Contestuali
        if ($request->has('immobile_id') && $request->immobile_id) {
            $query->where('immobile_id', $request->immobile_id);
        } 
        elseif ($request->has('anagrafica_id') && $request->anagrafica_id) {
            $query->where('anagrafica_id', $request->anagrafica_id);
        } 
        else {
            return response()->json(['rate' => []]);
        }

        // 4. Esecuzione e Mapping
        $quote = $query->with(['rata.pianoRate.gestione', 'immobile', 'rata', 'anagrafica'])
            ->orderBy('immobile_id')
            ->orderBy('data_scadenza', 'asc') 
            ->get()
            ->map(function ($quota) {
                
                $tipologia = null;
                if ($quota->anagrafica_id && $quota->immobile_id) {
                    $tipologia = DB::table('anagrafica_immobile')
                        ->where('anagrafica_id', $quota->anagrafica_id)
                        ->where('immobile_id', $quota->immobile_id)
                        ->value('tipologia');
                }

                $residuo = ($quota->importo - $quota->importo_pagato) / 100;

                return [
                    'id'              => $quota->id,
                    'rata_padre_id'   => $quota->rata_id,
                    'descrizione'     => ($quota->rata->descrizione ?? 'Rata') . ' (n.' . ($quota->rata->numero_rata ?? '-') . ')',
                    'scadenza_human'  => $quota->data_scadenza ? Carbon::parse($quota->data_scadenza)->format('d/m/Y') : 'N/D',
                    'importo_totale'  => $quota->importo / 100,
                    'residuo'         => $residuo,
                    'gestione'        => $quota->rata->pianoRate->gestione->nome ?? 'Generica',
                    'gestione_id'     => $quota->rata->pianoRate->gestione_id,
                    'unita'           => $quota->immobile ? "Int. {$quota->immobile->interno} ({$quota->immobile->nome})" : '-',
                    'intestatario'    => $quota->anagrafica ? $quota->anagrafica->nome : 'N/D',
                    'tipologia'       => $tipologia ? ucfirst($tipologia) : '',
                    'da_pagare'       => 0,     
                    'selezionata'     => false, 
                    'scaduta'         => $quota->data_scadenza && Carbon::parse($quota->data_scadenza)->isPast(),
                    'is_credito'      => $residuo < 0,

                    // MODIFICA QUI: Aggiunto il controllo se la quota è emessa
                    // Se ha un ID scrittura contabile, significa che è stata emessa.
                    'is_emitted'      => !is_null($quota->scrittura_contabile_id),
                ];
            });

        return response()->json(['rate' => $quote]);
    }
}