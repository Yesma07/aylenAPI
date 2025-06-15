<?php

namespace App\Controllers;

use App\Helpers\RedisHelper;
use App\Core\Request;

class RedisController
{
    private RedisHelper $redis;
    private Request $request;

    public function __construct(Request $request)
    {
        $this->redis = new RedisHelper();
        $this->request = $request;
    }

    public function listKeys(): array
    {
        $pattern = $this->request->get('pattern') ?? '*';
        return ['keys' => $this->redis->keys($pattern)];
    }

    public function getKey(): array
    {
        $name = $this->request->get('name');
        if (!$name) {
            throw new \InvalidArgumentException('Parámetro "name" requerido');
        }

        $value = $this->redis->getRaw($name);
        if (!$value) {
            throw new \Exception("La clave '{$name}' no existe");
        }

        return ['key' => $name, 'value' => json_decode($value, true)];
    }

    public function deleteKey(): array
    {
        $name = $this->request->get('name');
        if (!$name) {
            throw new \InvalidArgumentException('Parámetro "name" requerido');
        }

        $deleted = $this->redis->del($name);
        return ['deleted' => $deleted];
    }

    public function flushKeys(): array
    {
        $pattern = $this->request->get('pattern') ?? '*';
        $this->redis->flush($pattern);
        return ['message' => "Claves con patrón '{$pattern}' eliminadas."];
    }

    public function info(): array
    {
        return ['info' => $this->redis->info()];
    }
}
