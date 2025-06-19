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

        $map = self::buildMapFromColumns($table, $columns);
        $redis->set($cacheKey, $map);
        return $map;
    }

    public static function preloadFieldMaps(array $tables, int $companyId = 0): void
    {
        $redis = new RedisHelper();
        $db = Database::instance($companyId);
        $pending = [];

        foreach ($tables as $table) {
            $cacheKey = "field_map:{$companyId}:{$table}";
            if (!$redis->has($cacheKey)) {
                $pending[] = $table;
            }
        }

        if (empty($pending))
            return;

        $placeholders = implode(', ', array_fill(0, count($pending), '?'));
        $sql = "
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_name IN ($placeholders)
        ";

        $rows = $db->fetchAll($sql, $pending);
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['table_name']][] = $row['column_name'];
        }

        foreach ($grouped as $table => $columns) {
            $map = self::buildMapFromColumns($table, array_map(fn($c) => ['column_name' => $c], $columns));
            $redis->set("field_map:{$companyId}:{$table}", $map);
        }
    }

    private static function buildMapFromColumns(string $table, array $columns): array
    {
        preg_match('/([a-z]+)(\d+)/', $table, $matches);
        $tableCode = $matches[2] ?? null;
        if (!$tableCode) {
            throw new \Exception("No se pudo extraer el código de la tabla '{$table}'");
        }

        $expectedPrefixes = [
            "f{$tableCode}",
            "r{$tableCode}",
            "fc{$tableCode}",
            "rc{$tableCode}",
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

            if (in_array($colName, ['id', 'hash'])) {
                $map[$colName] = $colName;
            }
        }

        return $map;
    }
}
