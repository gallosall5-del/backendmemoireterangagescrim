<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;
use App\Traits\Auditable;

/**
 * Modèle Utilisateur avec authentification JWT et gestion des rôles/permissions.
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasRoles, Auditable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'telephone',
        'service_id',
        'read_scope_type',
        'read_scope_id',
        'write_scope_type',
        'write_scope_id',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'read_scope_type' => \App\Enums\ScopeType::class,
            'write_scope_type' => \App\Enums\ScopeType::class,
        ];
    }

    // ========== JWT ==========

    // Identifiant JWT (clé primaire de l'utilisateur)
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // Claims personnalisés dans le token JWT
    public function getJWTCustomClaims(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->getRoleNames(),
        ];
    }

    // ========== Relations ==========

    // L'utilisateur appartient à un service
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    // L'utilisateur peut avoir un profil personnel
    public function personnel()
    {
        return $this->hasOne(Personnel::class);
    }

    // L'utilisateur possède plusieurs notifications
    public function notificationsInternes()
    {
        return $this->hasMany(NotificationInterne::class);
    }

    // L'utilisateur possède un historique d'audit
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    // ========== Scopes ==========

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ILIKE', "%{$search}%")
              ->orWhere('email', 'ILIKE', "%{$search}%");
        });
    }

    public function scopeByService($query, $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }
}
