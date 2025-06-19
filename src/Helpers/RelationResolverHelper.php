<?php

namespace App\Helpers;

use Exception;
use App\Core\Database;

class RelationResolverHelper
{
    public static function resolveNestedRelation(string $baseTable, string $fieldPath, int $companyId): array
    {
        $cacheKey = "resolve_nested:{$companyId}:{$baseTable}:" . md5($fieldPath);
        $redis = new RedisHelper();
        if ($redis->has($cacheKey)) {
            return $redis->get($cacheKey);
        }

        $parts = explode('.', $fieldPath);
        $currentTable = $baseTable;
        $previousAlias = 't';
        $joins = [];

        foreach ($parts as $i => $part) {
            $isLast = ($i === count($parts) - 1);

            if ($isLast) {
                $fieldAlias = implode('_', $parts);
                $result = [
                    'joins' => $joins,
                    'final_alias' => $previousAlias,
                    'final_field' => self::mapField($currentTable, $part, $companyId),
                    'field_alias' => $fieldAlias
                ];
                $redis->set($cacheKey, $result);
                return $result;
            } else {
                $logicalField = $part;
                $aliasKey = implode('_', array_slice($parts, 0, $i + 1));
                $alias = self::generateAlias($aliasKey);

                $relation = self::getRelationInfo($currentTable, $logicalField, $companyId);
                $joins[] = [
                    'table' => $relation['related_table'],
                    'on' => "{$alias}.{$relation['related_column']} = {$previousAlias}.{$relation['fk_column']}",
                    'alias' => $alias
                ];

                $currentTable = $relation['related_table'];
                $previousAlias = $alias;
            }
        }

        throw new Exception("No se pudo resolver la relación para '{$fieldPath}'");
    }

    public static function getRelationInfo(string $table, string $logicalField, int $companyId): array
    {
        $fieldMap = FieldMapperHelper::getFieldMap($table, $companyId);

        foreach ($fieldMap as $logic => $physical) {
            if ($logic === $logicalField) {
                $redis = new RedisHelper();
                $cacheKey = "relation_info:{$companyId}:{$table}:{$logicalField}";
                if ($redis->has($cacheKey)) {
                    return $redis->get($cacheKey);
                }

                $db = Database::instance($companyId);

                $sql = "
                    SELECT
                        kcu.column_name AS fk_column,
                        ccu.table_name AS related_table,
                        ccu.column_name AS related_column
                    FROM 
                        information_schema.table_constraints AS tc
                    JOIN 
                        information_schema.key_column_usage AS kcu
                        ON tc.constraint_name = kcu.constraint_name
                        AND tc.constraint_schema = kcu.constraint_schema
                    JOIN 
                        information_schema.constraint_column_usage AS ccu
                        ON ccu.constraint_name = tc.constraint_name
                        AND ccu.constraint_schema = tc.constraint_schema
                    WHERE 
                        tc.constraint_type = 'FOREIGN KEY'
                        AND tc.table_name = :table
                        AND kcu.column_name = :column
                ";

                $results = $db->fetchAll($sql, [
                    ':table' => $table,
                    ':column' => $physical
                ]);

                if (!empty($results)) {
                    $relation = [
                        'fk_column' => $physical,
                        'related_table' => $results[0]['related_table'],
                        'related_column' => $results[0]['related_column'],
                    ];
                    $redis->set($cacheKey, $relation);
                    return $relation;
                } else {
                    throw new Exception("El campo físico '{$physical}' no tiene una clave foránea definida en la tabla '{$table}'.");
                }
            }
        }

        throw new Exception("No se encontró el campo lógico '{$logicalField}' en la tabla '{$table}'.");
    }

    protected static function mapField(string $table, string $logicalField, int $companyId): string
    {
        $fieldMap = FieldMapperHelper::getFieldMap($table, $companyId);
        if (!isset($fieldMap[$logicalField])) {
            throw new Exception("No se encontró el campo lógico '{$logicalField}' en la tabla '{$table}'");
        }
        return $fieldMap[$logicalField];
    }

    protected static function generateAlias(string $field): string
    {
        return str_replace('.', '_', $field);
    }
}
