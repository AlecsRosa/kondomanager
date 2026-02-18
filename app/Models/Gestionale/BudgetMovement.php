<?php

namespace App\Models\Gestionale;

use App\Models\Gestionale\Conto;
use App\Models\Gestionale\PianoRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetMovement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function pianoRate()
    {
        return $this->belongsTo(PianoRate::class);
    }

    public function sourceConto()
    {
        return $this->belongsTo(Conto::class, 'source_conto_id');
    }

    public function destinationConto()
    {
        return $this->belongsTo(Conto::class, 'destination_conto_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
