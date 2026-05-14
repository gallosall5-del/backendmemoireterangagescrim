<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modèle pour les notifications internes du système.
 */
class NotificationInterne extends Model
{
    use HasFactory;

    protected $table = 'notifications_internes';

    protected $fillable = [
        'user_id', 'titre', 'message', 'type', 'is_read', 'canal',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    // La notification appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ========== Scopes ==========

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCanal($query, $canal)
    {
        return $query->where('canal', $canal);
    }
}
