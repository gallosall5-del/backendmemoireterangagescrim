<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modèle pour les victimes et impliqués dans les infractions et accidents.
 */
class Victime extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom', 'prenom', 'no_cin_passeport', 'sexe',
        'age', 'nationalite', 'infraction_id', 'accident_id',
    ];

    // La victime peut être liée à une infraction
    public function infraction()
    {
        return $this->belongsTo(Infraction::class);
    }

    // La victime peut être liée à un accident
    public function accident()
    {
        return $this->belongsTo(Accident::class);
    }

    // Nom complet
    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenom} {$this->nom}");
    }
}
