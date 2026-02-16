<?php

namespace App\Models\Gestionale;

use App\Models\Tabella;
use Database\Factories\Gestionale\ContoFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conto extends Model
{
    use HasFactory;

    protected $table = 'conti';

    protected $fillable = [
        'piano_conto_id',
        'conto_contabile_id',
        'parent_id',
        'nome',
        'descrizione',
        'tipo',
        'importo',
        'destinazione_id',
        'destinazione_type',
        'note',
    ];
    
    /** RELAZIONI */
    public function pianoConto()
    {
        return $this->belongsTo(PianoConto::class, 'piano_conto_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function sottoconti()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    // Collega la voce di budget (es. "Cancelleria") al conto patrimoniale (es. "Debiti v/Fornitori" o cassa specifica)
    public function contoContabile()
    {
        return $this->belongsTo(ContoContabile::class, 'conto_contabile_id');
    }

    public function destinazione()
    {
        return $this->morphTo();
    }

    public function tabelleMillesimali()
    {
        return $this->hasMany(ContoTabellaMillesimale::class);
    }

    // Nel modello Conto
    public function tabelle()
    {
        return $this->belongsToMany(Tabella::class, 'conto_tabella_millesimale')
            ->withPivot('coefficiente')
            ->withTimestamps();
    }

    public function ripartizioni()
    {
        return $this->hasManyThrough(
            ContoTabellaRipartizione::class,
            ContoTabellaMillesimale::class,
            'conto_id',
            'conto_tabella_millesimale_id'
        );
    }

    public function pianiRate(): BelongsToMany
    {
        return $this->belongsToMany(PianoRate::class, 'piano_rate_capitoli', 'conto_id', 'piano_rate_id');
    }

    /**
     * Recupera tutti gli ID dei sottoconti (figli, nipoti, ecc.)
     */
    public function getAllChildrenIds(): array
    {
        $ids = [];
        foreach ($this->sottoconti as $sottoconto) {
            $ids[] = $sottoconto->id;
            $ids = array_merge($ids, $sottoconto->getAllChildrenIds());
        }
        return $ids;
    }

    /**
     * Recupera tutti gli ID dei padri (parent, grandparent, ecc.)
     */
    public function getAllAncestorsIds(): array
    {
        $ids = [];
        $parent = $this->parent;
        while ($parent) {
            $ids[] = $parent->id;
            $parent = $parent->parent;
        }
        return $ids;
    }

    protected static function newFactory()
    {
        return ContoFactory::new();
    }
}
