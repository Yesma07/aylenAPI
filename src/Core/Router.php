<?php

namespace App\Core;

use App\Core\Exceptions\HttpException;

class Router
{
    public static function handle(Request $request, string $endpoint)
    {
        try {

            if(empty($endpoint)) {
                throw new HttpException('Endpoint no especificado.', 400);
            }
            
            $modelName = $request->get('model') 
            ?? throw new HttpException('Falta el modelo en la solicitud.', 400);
            
            $method = $request->get('method') ?? null;
            
            // Obtener clase del controlador del modelo
            $controllerClass = self::resolveModelClass($modelName);
            
            if (!class_exists($controllerClass)) {
                throw new HttpException("Modelo '{$modelName}' no encontrado.", 404);
            }
            
            $controller = new $controllerClass($request);
            
            // Si es un método personalizado
            if ($method !== null) {
                if (!method_exists($controller, $method)) {
                    throw new HttpException("Método '{$method}' no encontrado en el modelo '{$modelName}'.", 404);
                }
    
                return $controller->$method();
            }
    
            // Determinar acción por convención
            $body = $request->getBody();
            
            $hasRecord = isset($body['record']);
            $hasValues = isset($body['values']);
            $hasDomain = isset($body['domain']);
    
            switch($endpoint) {
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
    
        } catch (\Throwable $e) {
            throw new HttpException('Error interno del servidor: ' . $e->getMessage(), 500);
        }
    }

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
