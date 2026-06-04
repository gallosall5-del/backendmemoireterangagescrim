<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

/**
 * Modèle pour les services de la DSP (CC, CA, PP, CU, CS).
 */
class Service extends Model
{
    use HasFactory, Auditable;

    protected $fillable = ['nom', 'type', 'commune_id', 'adresse', 'telephone', 'email', 'latitude', 'longitude'];

    // Un service appartient à une commune
    public function commune()
    {
        return $this->belongsTo(Commune::class);
    }

    // Un service possède plusieurs personnels
    public function personnels()
    {
        return $this->hasMany(Personnel::class);
    }

    // Un service possède plusieurs utilisateurs
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Un service possède plusieurs infractions
    public function infractions()
    {
        return $this->hasMany(Infraction::class);
    }

    // Un service possède plusieurs accidents
    public function accidents()
    {
        return $this->hasMany(Accident::class);
    }

    // Un service possède plusieurs services rémunérés
    public function servicesRemuneres()
    {
        return $this->hasMany(ServiceRemunere::class);
    }

    // Un service possède plusieurs amendes et pièces saisies
    public function amendesPiecesSaisies()
    {
        return $this->hasMany(AmendePieceSaisie::class);
    }

    // Un service possède plusieurs données d'immigration clandestine
    public function immigrationsClandestines()
    {
        return $this->hasMany(ImmigrationClandestine::class);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where('nom', 'ILIKE', "%{$search}%");
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCommune($query, $communeId)
    {
        return $query->where('commune_id', $communeId);
    }
}
