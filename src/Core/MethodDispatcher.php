<?php

namespace App\Core;

class MethodDispatcher
{
    public static function dispatch(string $model, string $method, array $data, array $context = []): array
    {
        // Normaliza el nombre del módulo, ej: m001_usuarios → M001Usuarios
        $moduleName = str_replace(' ', '', ucwords(str_replace('_', ' ', $model)));

        $controllerClass = "\\App\\Modules\\{$moduleName}\\Controller";

        if (!class_exists($controllerClass)) {
            throw new \Exception("Clase '$controllerClass' no encontrada.");
        }

        $request = new \App\Core\Request(); // Pasamos el request como siempre
        $instance = new $controllerClass($request);

        if (!method_exists($instance, $method)) {
            throw new \Exception("Método '$method' no existe en $controllerClass.");
        }

        return $instance->$method($data, $context);
    }
}