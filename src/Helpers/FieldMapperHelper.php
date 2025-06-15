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

        // Obtener código numérico de la tabla (cfg001 → 001)
        preg_match('/([a-z]+)(\d+)/', $table, $matches);
        $tableCode = $matches[2] ?? null;
        if (!$tableCode) {
            throw new \Exception("No se pudo extraer el código de la tabla '{$table}'");
        }

        // Prefijos esperados (campo, relación, auditoría, etc.)
        $expectedPrefixes = [
            "f{$tableCode}",   // campos propios
            "r{$tableCode}",   // relaciones
            "fc{$tableCode}",  // campo + compañía
            "rc{$tableCode}",  // relaciones + compañía
        ];

        $map = [];

        foreach ($columns as $col) {
            $colName = $col['column_name'];
            foreach ($expectedPrefixes as $prefix) {
                if (str_starts_with($colName, $prefix . '_')) {
                    $logical = substr($colName, strlen($prefix) + 1);
                    $map[$logical] = $colName;
                    break;
                }
            }

            // Permitir acceso directo a "id", "hash" (sin prefijo)
            if (in_array($colName, ['id', 'hash'])) {
                $map[$colName] = $colName;
            }
        }

        $redis->set($cacheKey, $map);
        return $map;
    }
}
