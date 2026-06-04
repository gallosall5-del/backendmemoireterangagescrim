<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ScopeAccessService;

class CheckReadScope
{
    protected $scopeService;

    public function __construct(ScopeAccessService $scopeService)
    {
        $this->scopeService = $scopeService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Ce middleware peut être appliqué à des routes spécifiques pour forcer la vérification de lecture
        // Toutefois, en général, on utilise le Query Scope `visibleByUser()` pour filtrer les listes.
        // On peut l'utiliser pour bloquer l'accès à une ressource spécifique si l'ID est dans la requête.
        
        return $next($request);
    }
}
