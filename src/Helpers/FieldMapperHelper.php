<?php

namespace App\Helpers;

use App\Core\Database;
use App\Helpers\RedisHelper;

class FieldMapperHelper
{
    public static function getFieldMap(string $table, int $companyId = 0): array
    {
        $redis = new RedisHelper();
        $cacheKey = "field_map:{$companyId}:{$table}";

        if ($redis->has($cacheKey)) {
            return $redis->get($cacheKey);
        }

        $db = Database::instance($companyId);

        $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = ?";
        $columns = $db->fetchAll($sql, [$table]);

        $prefix = self::extractPrefix($table);
        $map = [];

        foreach ($columns as $col) {
            $name = $col['column_name'];

            // Si la columna tiene prefijo tipo "fc001_nombre", extrae "nombre"
            if (str_starts_with($name, $prefix . '_')) {
                $logical = substr($name, strlen($prefix) + 1);
                $map[$logical] = $name;
            } elseif ($name === 'id' || $name === 'hash') {
                $map[$name] = $name;
            }
        }

        $redis->set($cacheKey, $map);

        return $map;
    }

    private static function extractPrefix(string $table): string
    {
        $parts = explode('_', $table);
        return $parts[0]; // ej: fc001
    }
}
