<?php

namespace App\Helpers;

class RedisHelper
{
    private \Redis $redis;
    private string $prefix;

    public function __construct(string $host = '127.0.0.1', int $port = 6379, string $prefix = 'erp:')
    {
        $this->redis = new \Redis();

        if (!$this->redis->connect($host, $port)) {
            throw new \RuntimeException("No se pudo conectar a Redis en {$host}:{$port}");
        }

        $this->prefix = $prefix;
    }

    private function key(string $key): string
    {
        return $this->prefix . $key;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $encoded = json_encode($value);
        return $this->redis->setex($this->key($key), $ttl, $encoded);
    }

    public function get(string $key): mixed
    {
        $data = $this->redis->get($this->key($key));
        return $data ? json_decode($data, true) : null;
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->key($key)) > 0;
    }

    public function del(string $key): int
    {
        return $this->redis->del($this->key($key));
    }

    public function flush(string $pattern = '*'): void
    {
        $keys = $this->redis->keys($this->key($pattern));
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }
}
