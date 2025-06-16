<?php

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Core\Database;
use App\Core\Request;

class TrytonClient
{
    private string $host;
    private string $dbName;
    private string $user;
    private string $baseUrl;

    public function __construct(Request $request)
    {
        $context = $request->getBody()['context'] ?? [];
        $companyId = $context['company'] ?? 1; // Por defecto 1

        // Conectamos a la base de datos con el tenant
        $db = Database::instance($companyId);

        $userEnvId = $context['user_env'] ?? null;
        if (!$userEnvId) {
            throw new HttpException("Falta el campo 'user_env' en el contexto.", 400);
        }

        // Consultamos los datos del usuario
        $usuarioPayload = [
            'model' => 'usuarios',
            'fields_names' => ['id', 'id_user_tryton', 'puerto_tryton'],
            'domain' => json_encode([['id', '=', $userEnvId]]),
            'context' => $context
        ];

        $usuarios = $db->genericSearch($usuarioPayload, 'm001_usuarios');
        if (empty($usuarios) || !isset($usuarios[0])) {
            throw new HttpException("Usuario con ID {$userEnvId} no encontrado.", 403);
        }

        $usuario = $usuarios[0];
        $trytonUser = $usuario['id_user_tryton'] ?? null;
        $trytonPort = $usuario['puerto_tryton'] ?? null;

        if (!$trytonUser || !$trytonPort) {
            throw new HttpException("Faltan datos de conexión Tryton para el usuario.", 500);
        }

        // Construimos la URL
        $this->host = $_ENV["TRYTON_COMPANY_{$companyId}_HOST"] ?? '';
        $this->dbName = $_ENV["TRYTON_COMPANY_{$companyId}_DBNAME"] ?? '';

        if (!$this->host || !$this->dbName) {
            throw new HttpException("Faltan variables de entorno TRYTON_COMPANY_{$companyId}_HOST o DBNAME.", 500);
        }

        $this->user = $trytonUser;

        // Ejemplo final: http://192.168.254.206:8010/erp_template
        $this->baseUrl = rtrim($this->host, '/') . ':' . $trytonPort . '/' . $this->dbName;
    }

    public function forward(array $body, string $endpoint)
    {
        // Limpiamos el contexto
        $originalContext = $body['context'] ?? [];
        unset($originalContext['engine'], $originalContext['user_env']);

        // Creamos el nuevo contexto
        $cleanedContext = array_merge([
            'company' => 1,
            'user' => $this->user,
        ], $originalContext);

        $body['context'] = $cleanedContext;

        $jsonBody = json_encode($body);
        if (!$jsonBody) {
            throw new HttpException("No se pudo serializar el cuerpo de la solicitud.", 500);
        }

        $fullUrl = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $headers = [
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new HttpException("Error al conectar con Tryton: {$error}", 500);
        }

        if ($httpCode >= 400) {
            throw new HttpException("Error HTTP {$httpCode} desde Tryton: {$response}", $httpCode);
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new HttpException("Respuesta Tryton inválida: " . json_last_error_msg(), 500);
        }

        return $result;
    }
}
