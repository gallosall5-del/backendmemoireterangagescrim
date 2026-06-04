<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ScopeAccessService;
use App\Models\AuditLog;

class CheckWriteScope
{
    protected $scopeService;

    public function __construct(ScopeAccessService $scopeService)
    {
        $this->scopeService = $scopeService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Délégué principalement à CheckTerritorialAccess
        // Peut être utilisé pour des vérifications plus granulaires sur des endpoints spécifiques
        
        return $next($request);
    }
}
