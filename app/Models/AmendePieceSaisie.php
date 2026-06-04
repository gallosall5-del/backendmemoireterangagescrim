<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\Traits\HasTerritorialScope;

/**
 * Modèle pour les amendes forfaitaires et pièces saisies.
 */
class AmendePieceSaisie extends Model
{
    use HasFactory, Auditable, HasTerritorialScope;

    protected $table = 'amendes_pieces_saisies';

    protected $fillable = [
        'workflow_status',
        'type', 'service_id', 'date', 'montant', 'description', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'montant' => 'decimal:2',
        ];
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ========== Scopes ==========

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByService($query, $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeByDateRange($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
