<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "Teranga GESCRIM API",
    version: "1.0.0",
    description: "API REST pour le système de Gestion de la Criminalité et de la Délinquance — Direction de la Sécurité Publique (DSP) du Sénégal.",
    contact: new OA\Contact(email: "admin@gescrim.sn", name: "NAMASTECH"),
    license: new OA\License(name: "Propriétaire", url: "https://gescrim.sn")
)]
#[OA\Server(url: "http://localhost:8000", description: "Serveur de développement")]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Token JWT obtenu via POST /api/auth/login"
)]
class OpenApi
{
}
