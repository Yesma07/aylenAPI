<?php

namespace App\Helpers;

use PDO;
use App\Helpers\RedisHelper;

class ModelHelper
{
    private static PDO $db;
    private static RedisHelper $redis;

    public static function init(PDO $pdo): void
    {
        self::$db = $pdo;
        self::$redis = new RedisHelper();
    }

    public static function getModelConfig(string $model): ?array
    {
        $cacheKey = "model_config:{$model}";

        // 1. Verifica cache
        if (self::$redis->has($cacheKey)) {
            return self::$redis->get($cacheKey);
        }

        // 2. Separar esquema y tabla
        [$schema, $table] = self::parseModel($model);
        $fullTable = "{$schema}.{$table}";

        // 3. Obtener campos
        $stmt = self::$db->prepare("
            SELECT column_name, ordinal_position
            FROM information_schema.columns
            WHERE table_schema = :schema AND table_name = :table
        ");
        $stmt->execute(['schema' => $schema, 'table' => $table]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$columns) {
            return null;
        }

        $fields = [];
        foreach ($columns as $col) {
            $fields[$col['column_name']] = $col['column_name'];
        }

        // 4. Buscar campo "alive"
        $aliveField = null;
        foreach ($fields as $f) {
            if (str_contains($f, 'alive')) {
                $aliveField = $f;
                break;
            }
        }

        // 5. Obtener PK
        $stmt = self::$db->prepare("
            SELECT a.attname AS pk
            FROM pg_index i
            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
            WHERE i.indrelid = :full_table::regclass AND i.indisprimary
        ");
        $stmt->execute(['full_table' => $fullTable]);
        $pkRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $pk = $pkRow['pk'] ?? 'id';

        // 6. Estructura
        $config = [
            'table' => $fullTable,
            'fields' => $fields,
            'alive' => $aliveField,
            'pk' => $pk
        ];

        // 7. Guardar en Redis
        self::$redis->set($cacheKey, $config, 3600);

        return $config;
    }

    private static function parseModel(string $model): array
    {
        if (str_contains($model, '.')) {
            return explode('.', $model);
        }

        return ['public', $model];
    }
}
