<?php

namespace App\Enums;

enum OrigineQuota: string
{
    case CALCOLO_AUTOMATICO = 'calcolo_automatico';
    case INSERIMENTO_MANUALE = 'inserimento_manuale';
    case RETTIFICA = 'rettifica';
    case STORNO = 'storno';

    public function label(): string
    {
        return match($this) {
            self::CALCOLO_AUTOMATICO => 'Generato dal sistema',
            self::INSERIMENTO_MANUALE => 'Inserimento manuale',
            self::RETTIFICA => 'Rettifica manuale',
            self::STORNO => 'Storno operazione',
        };
    }
}