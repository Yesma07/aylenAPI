<?php 

namespace Src\Config;

use Dotenv\Dotenv;

class EnvLoader
{
    public static function load(string $empresa): void
    {
        $envPath = __DIR__ . '/../../env';
        $envFile = ".env.{$empresa}";

        if (!file_exists($envPath . '/' . $envFile)) {
            throw new \Exception("Archivo de entorno {$envFile} no encontrado en la ruta {$envPath}");
        }

        $dotenv = Dotenv::createImmutable($envPath, $envFile);
        $dotenv->load();
    }
}