<?php

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Core\TrytonClient;

/**
 * Autor: YMARIN
 * Fecha: 16/06/2025
 * Descripción: Router para manejar las solicitudes entrantes y dirigirlas a los controladores correspondientes.
 * Este router es responsable de interpretar el endpoint solicitado
 */

class Router
{
    /**
     * Maneja la solicitud entrante y dirige a la operación correspondiente.
     *
     * @param Request $request Modelo de solicitud que contiene los datos de la petición.
     * @param string $endpoint Endpoint solicitado, como 'create', 'search', 'write', 'delete', 'method'.
     * @return mixed
     * @throws HttpException
     */
    public static function handle(Request $request, string $endpoint)
    {
        try {
            if (empty($endpoint)) {
                throw new HttpException('Endpoint no especificado.', 400);
            }

            $modelName = $request->get('model')
                ?? throw new HttpException('Falta el modelo en la solicitud.', 400);

            $method = $request->get('method') ?? null;
            $context = $request->getBody()['context'] ?? [];
            $engine = $context['engine'] ?? 'dlm';
            $tenant = (int) $context['company'] ?? null;

            if (!$tenant) {
                throw new HttpException('Falta el ID de empresa en el contexto.', 400);
            }

            // Operaciones de Tryton
            if ($engine === 'tryton') {
                $client = new TrytonClient($request);

                return $client->forward($request->getBody(), $endpoint);
            }

            // Operaciones de DLM
            $controllerClass = self::resolveModelClass($modelName);

            if (!class_exists($controllerClass)) {
                throw new HttpException("Modelo '{$modelName}' no encontrado.", 404);
            }

            $controller = new $controllerClass($request);

            if ($method !== null) {
                if (!method_exists($controller, $method)) {
                    throw new HttpException("Método '{$method}' no encontrado en el modelo '{$modelName}'.", 404);
                }

                return $controller->$method();
            }

            // Detección de operación basada en la estructura del body
            $body = $request->getBody();
            $hasRecord = isset($body['record']);
            $hasValues = isset($body['values']);
            $hasDomain = isset($body['domain']);

            switch ($endpoint) {
                case 'create':
                    if ($hasRecord && !$hasValues && !$hasDomain) {
                        return $controller->create();
                    }
                    break;

                case 'search':
                    return $controller->search();

                case 'write':
                    if ($hasValues && !$hasDomain) {
                        return $controller->write();
                    }
                    break;

                case 'delete':
                    if ($hasDomain && !$hasValues && !$hasRecord) {
                        return $controller->delete();
                    }
                    break;

                default:
                    throw new HttpException("Operación '{$endpoint}' no soportada.", 400);
            }

            throw new HttpException("Payload no válido para la operación '{$endpoint}'.", 400);
        } catch (\Throwable $e) {
            throw new HttpException('Error interno del servidor: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Resuelve la clase del modelo a partir de su nombre.
     *
     * @param string $model Nombre del modelo, como 'r001_usuario' o 'cfg.platform'.
     * @return string Clase completa del controlador.
     */
    private static function resolveModelClass(string $model): string
    {
        // Convierte r001_usuario o cfg.platform a clase y namespace correctos
        $parts = preg_split('/[_\.]/', $model);
        $moduleName = implode('', array_map('ucfirst', $parts)); // Ej: R001Usuario, CfgPlatform
        $controllerClass = $moduleName . 'Controller';

        $route = "App\\Modules\\{$moduleName}\\Controller\\{$controllerClass}";

        return $route;
    }
}
