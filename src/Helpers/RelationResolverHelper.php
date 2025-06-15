<?php

namespace App\Helpers;

use Exception;
use App\Core\Database;

class RelationResolverHelper
{
    public static function resolveNestedRelation(string $baseTable, string $fieldPath, int $companyId): array
    {
        $parts = explode('.', $fieldPath);
        $currentTable = $baseTable;
        $previousAlias = 't';
        $joins = [];

        foreach ($parts as $i => $part) {
            $isLast = ($i === count($parts) - 1);

            if ($isLast) {
                // Campo final
                $fieldAlias = implode('_', $parts);
                return [
                    'joins' => $joins,
                    'final_alias' => $previousAlias,
                    'final_field' => self::mapField($currentTable, $part, $companyId),
                    'field_alias' => $fieldAlias
                ];
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
        ";

        $results = $db->fetchAll($sql, [
            ':table' => $table
        ]);

        if (empty($results)) {
            throw new Exception("No se encontraron claves foráneas en la tabla {$table}.");
        }

        foreach ($results as $relation) {
            $fkColumn = $relation['fk_column'];
            $relatedTable = $relation['related_table'];

            $normalizedFk = self::normalizeField($fkColumn);
            $normalizedLogic = self::normalizeField($logicalField);

            if ($normalizedFk === $normalizedLogic || str_ends_with($normalizedFk, '_' . $normalizedLogic)) {
                return [
                    'fk_column' => $fkColumn,
                    'related_table' => $relatedTable,
                    'related_column' => $relation['related_column'],
                ];
            }
        }

        throw new Exception("No se encontró relación compatible para el campo lógico '{$logicalField}' en la tabla {$table}.");
    }

    protected static function normalizeField(string $field): string
    {
        return preg_replace('/^[a-z]+\d*_/', '', $field); // elimina prefijos tipo fc001_, rc001_, f001_, etc.
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
        return str_replace('.', '_', $field); // Ej: usuario_crea → usuario_crea
    }
}
