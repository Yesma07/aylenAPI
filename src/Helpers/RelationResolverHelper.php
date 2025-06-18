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
        $fieldMap = FieldMapperHelper::getFieldMap($table, $companyId);

        // Verifica si el campo lógico existe en el mapeo de esta tabla
        foreach ($fieldMap as $logic => $physical) {
            if ($logic === $logicalField) {
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
                    $relation = $results[0];
                    return [
                        'fk_column' => $physical,
                        'related_table' => $relation['related_table'],
                        'related_column' => $relation['related_column'],
                    ];
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
        return str_replace('.', '_', $field); // Ej: usuario_crea → usuario_crea
    }
}
