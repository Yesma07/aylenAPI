<?php

namespace App\Core;

use App\Core\Exceptions\HttpException;

class Request
{
    private array $rawData;
    private array $context;

    public function __construct()
    {
        try {

            //Validar Tipo de Contenido
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new HttpException('Método no permitido. Solo se permiten solicitudes POST.', 405);
            }

            $input = file_get_contents('php://input');
            if (empty($input)) {
                throw new HttpException('No se pudo leer el cuerpo de la solicitud.', 400);
            }

            //Validar si hay encriptación
            if (!$_ENV['APP_DEBUG']) {
                $input = Crypto::decrypt($input);
            }

            //Parsear el JSON
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new HttpException('Error al decodificar el JSON: ' . json_last_error_msg(), 400);
            }

            //Sanitizar los datos
            $this->rawData = $this->sanitize($data);
            if (!isset($this->rawData['context'])) {
                throw new HttpException('Falta el Contexto en la solicitud.', 400);
            }

            $this->context = $this->rawData['context'];
        } catch (\Exception $e) {
            throw new HttpException('Error interno del servidor: ' . $e->getMessage(), 0);
        }
    }

    public function getBody(): array
    {
        return $this->rawData;
    }

    public function get(string $key, mixed $default = null)
    {
        return $this->rawData[$key] ?? $default;
    }

    public function getContext(string $key, $default = null)
    {
        return $this->context[$key] ?? $default;
    }

    public function getTenant(): string
    {
        $tenant = $this->getContext('middleware') ?? null;

        if (!$tenant) {
            throw new HttpException('Falta el middleware en el contexto.', 400);
        }

        // Convertimos TENANTS en arreglo
        $allowedTenants = explode(',', $_ENV['TENANTS'] ?? '');

        if (!in_array($tenant, $allowedTenants)) {
            throw new HttpException('Middleware no válido: ' . $tenant, 400);
        }

        return $tenant;
    }


    private function sanitize($data): mixed
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }

        return htmlspecialchars(strip_tags((string) $data), ENT_QUOTES, 'UTF-8');
    }
}
