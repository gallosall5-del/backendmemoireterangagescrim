<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use App\Enums\ScopeType;
use App\Models\Commune;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;

/**
 * Service centralisé gérant la logique des compétences territoriales.
 * Détermine ce qu'un utilisateur a le droit de lire et d'écrire en fonction de son affectation.
 */
class ScopeAccessService
{
    /**
     * Vérifie si l'utilisateur peut lire un modèle donné (consultation).
     */
    public function canRead(User $user, Model $model): bool
    {
        return $this->hasAccess($user, $model, 'read');
    }

    /**
     * Vérifie si l'utilisateur peut écrire dans un modèle donné (création, modif, suppression).
     */
    public function canWrite(User $user, Model $model): bool
    {
        return $this->hasAccess($user, $model, 'write');
    }

    /**
     * Logique principale de vérification territoriale.
     */
    protected function hasAccess(User $user, Model $model, string $action): bool
    {
        $scopeType = $action === 'write' ? $user->write_scope_type : $user->read_scope_type;
        $scopeId = $action === 'write' ? $user->write_scope_id : $user->read_scope_id;

        // Si scope est national, il a accès à tout
        if ($scopeType === ScopeType::NATIONAL) {
            return true;
        }

        // Extraire la commune ou le service du modèle
        $communeId = $this->extractCommuneId($model);
        $serviceId = $this->extractServiceId($model);

        // Si le modèle n'est rattaché à aucun territoire spécifique, on refuse par sécurité (ou on accepte selon la règle)
        if (!$communeId && !$serviceId) {
            // Exceptions pour les entités globales non territoriales
            return false;
        }

        return $this->checkTerritorialAccess($scopeType, $scopeId, $communeId, $serviceId);
    }

    /**
     * Vérifie l'accès à une commune spécifique.
     */
    public function canAccessCommune(User $user, int $communeId, string $action = 'write'): bool
    {
        $scopeType = $action === 'write' ? $user->write_scope_type : $user->read_scope_type;
        $scopeId = $action === 'write' ? $user->write_scope_id : $user->read_scope_id;

        if ($scopeType === ScopeType::NATIONAL) {
            return true;
        }

        return $this->checkTerritorialAccess($scopeType, $scopeId, $communeId, null);
    }

    /**
     * Vérifie l'accès à un service spécifique.
     */
    public function canAccessService(User $user, int $serviceId, string $action = 'write'): bool
    {
        $scopeType = $action === 'write' ? $user->write_scope_type : $user->read_scope_type;
        $scopeId = $action === 'write' ? $user->write_scope_id : $user->read_scope_id;

        if ($scopeType === ScopeType::NATIONAL) {
            return true;
        }

        return $this->checkTerritorialAccess($scopeType, $scopeId, null, $serviceId);
    }

    /**
     * Résout l'appartenance territoriale.
     */
    protected function checkTerritorialAccess(ScopeType $scopeType, ?int $scopeId, ?int $communeId, ?int $serviceId): bool
    {
        if ($scopeType === ScopeType::SERVICE && $serviceId) {
            return $scopeId === $serviceId;
        }

        // Résoudre la commune si on n'a que le service
        if (!$communeId && $serviceId) {
            $service = Service::find($serviceId);
            $communeId = $service ? $service->commune_id : null;
        }

        if (!$communeId) return false;

        if ($scopeType === ScopeType::COMMUNE) {
            return $scopeId === $communeId;
        }

        $commune = Commune::find($communeId);
        if (!$commune) return false;

        if ($scopeType === ScopeType::DEPARTEMENT) {
            return $scopeId === $commune->departement_id;
        }

        if ($scopeType === ScopeType::REGION) {
            $departement = $commune->departement;
            if (!$departement) return false;
            return $scopeId === $departement->region_id;
        }

        return false;
    }

    /**
     * Applique le filtre de lecture sur une requête (Query Scope).
     */
    public function applyReadScope(Builder $query, User $user): Builder
    {
        return $this->applyScope($query, $user->read_scope_type, $user->read_scope_id);
    }

    /**
     * Applique le filtre d'écriture sur une requête (Query Scope).
     */
    public function applyWriteScope(Builder $query, User $user): Builder
    {
        return $this->applyScope($query, $user->write_scope_type, $user->write_scope_id);
    }

    /**
     * Construction de la requête filtrée selon le scope territorial.
     */
    protected function applyScope(Builder $query, ScopeType $scopeType, ?int $scopeId): Builder
    {
        if ($scopeType === ScopeType::NATIONAL) {
            return $query;
        }

        $model = $query->getModel();
        $table = $model->getTable();

        // Modèles avec service_id (priorité au service)
        if (\Schema::hasColumn($table, 'service_id')) {
            if ($scopeType === ScopeType::SERVICE) {
                return $query->where($table . '.service_id', $scopeId);
            }
            
            // Si le scope est plus large qu'un service, on filtre sur la commune associée au service ou directement sur le modèle
            if (\Schema::hasColumn($table, 'commune_id')) {
                return $this->filterByCommuneScope($query, $table . '.commune_id', $scopeType, $scopeId);
            } else {
                // Utiliser une jointure sur services
                return $query->whereHas('service', function ($q) use ($scopeType, $scopeId) {
                    $this->filterByCommuneScope($q, 'services.commune_id', $scopeType, $scopeId);
                });
            }
        } 
        
        // Modèles avec commune_id
        if (\Schema::hasColumn($table, 'commune_id')) {
            return $this->filterByCommuneScope($query, $table . '.commune_id', $scopeType, $scopeId);
        }

        // Cas des modèles imbriqués (ex: Victime liée à Infraction ou Accident)
        if (method_exists($model, 'infraction') && method_exists($model, 'accident')) {
            return $query->where(function ($q) use ($scopeType, $scopeId) {
                $q->whereHas('infraction', function ($sq) use ($scopeType, $scopeId) {
                    $this->applyScope($sq, $scopeType, $scopeId);
                })->orWhereHas('accident', function ($sq) use ($scopeType, $scopeId) {
                    $this->applyScope($sq, $scopeType, $scopeId);
                });
            });
        }

        // Par défaut, retourner une requête vide si impossible de déterminer le scope
        return $query->whereRaw('1 = 0');
    }

    /**
     * Applique les filtres de territoire sur une colonne commune_id
     */
    protected function filterByCommuneScope(Builder $query, string $communeColumn, ScopeType $scopeType, int $scopeId): Builder
    {
        if ($scopeType === ScopeType::COMMUNE) {
            return $query->where($communeColumn, $scopeId);
        }

        if ($scopeType === ScopeType::DEPARTEMENT) {
            return $query->whereHas('commune', function ($q) use ($scopeId) {
                $q->where('departement_id', $scopeId);
            });
        }

        if ($scopeType === ScopeType::REGION) {
            return $query->whereHas('commune.departement', function ($q) use ($scopeId) {
                $q->where('region_id', $scopeId);
            });
        }

        return $query;
    }

    /**
     * Helpers pour extraire la commune/service d'un modèle arbitraire.
     */
    protected function extractCommuneId(Model $model): ?int
    {
        if (isset($model->commune_id)) return $model->commune_id;
        
        // Cas des victimes, etc.
        if (isset($model->infraction_id) && $model->infraction) {
            return $model->infraction->commune_id;
        }
        if (isset($model->accident_id) && $model->accident) {
            return $model->accident->commune_id;
        }
        
        return null;
    }

    protected function extractServiceId(Model $model): ?int
    {
        if (isset($model->service_id)) return $model->service_id;
        
        if (isset($model->infraction_id) && $model->infraction) {
            return $model->infraction->service_id;
        }
        if (isset($model->accident_id) && $model->accident) {
            return $model->accident->service_id;
        }
        
        return null;
    }
}
