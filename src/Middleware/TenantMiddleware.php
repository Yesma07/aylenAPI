<?php

namespace Src\Middleware;

use Src\Config\EnvLoader;

class TenantMiddleware
{
    public static function handle(): void
    {
        $headers = getallheaders();
        $empresa = $headers['X-Tenant-Id'] ?? null;

        if (!$empresa) {
            http_response_code(400);
            echo json_encode(['error' => 'Tenant ID es requerido']);
            exit;
        }

        try {
            EnvLoader::load(strtolower($empresa));
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al cargar el entorno: ' . $e->getMessage()]);
            exit;
        }
    }
}