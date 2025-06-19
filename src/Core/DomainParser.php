<?php

namespace App\Core;

use App\Helpers\FieldMapperHelper;
use App\Helpers\RelationResolverHelper;

class DomainParser
{
    public static function parse(string $domain, string $table, int $companyId): array
    {
        $domain = html_entity_decode($domain);
        $structure = self::normalizeToPhpArray($domain);

        if (!is_array($structure)) {
            throw new \Exception("Dominio inválido, debe ser array.");
        }

        return self::buildConditions($structure, $table, $companyId);
    }

    private static function normalizeToPhpArray(string $input): array
    {
        $input = trim($input);
        $input = str_replace(['True', 'False'], ['true', 'false'], $input);

        $input = preg_replace_callback('/\(([^()]+?)\)/', function ($matches) {
            return '[' . $matches[1] . ']';
        }, $input);

        $input = str_replace("'", '"', $input);

        $input = preg_replace_callback('/([A-Z]+)\[/', function ($matches) {
            return '{"' . $matches[1] . '": [';
        }, $input);

        $orCount = substr_count($input, '{"OR": [');
        $andCount = substr_count($input, '{"AND": [');
        $toClose = $orCount + $andCount;

        $input = rtrim($input);
        while ($toClose > 0) {
            $input .= ']}';
            $toClose--;
        }

        $input = preg_replace('/,\s*]/', ']', $input);

        $parsed = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Dominio mal formado: " . json_last_error_msg());
        }

        return $parsed;
    }

    private static function buildConditions(array $domain, string $table, int $companyId, string $logic = 'AND'): array
    {
        $conditions = [];

        foreach ($domain as $key => $item) {
            if (is_string($key) && in_array($key, ['OR', 'AND'])) {
                $subConditions = self::buildConditions($item, $table, $companyId, $key);
                $glue = $key === 'OR' ? ' OR ' : ' AND ';
                $conditions[] = '(' . implode($glue, $subConditions) . ')';
                continue;
            }

            if (is_array($item) && count($item) === 3) {
                [$field, $operator, $value] = $item;

                if (str_contains($field, '.')) {
                    // Resolver campo anidado
                    $resolved = RelationResolverHelper::resolveNestedRelation($table, $field, $companyId);
                    $column = "{$resolved['final_alias']}.{$resolved['final_field']}";
                } else {
                    $fieldMap = FieldMapperHelper::getFieldMap($table, $companyId);
                    if (!isset($fieldMap[$field])) {
                        throw new \Exception("Campo lógico '{$field}' no encontrado en la tabla '{$table}'.");
                    }
                    $column = "t." . $fieldMap[$field];
                }

                if (in_array(strtolower($operator), ['in', 'not in']) && is_array($value)) {
                    $formattedValue = '(' . implode(', ', array_map([self::class, 'formatValue'], $value)) . ')';
                } else {
                    $formattedValue = self::formatValue($value);
                }

                $conditions[] = "{$column} {$operator} {$formattedValue}";
            } elseif (is_array($item)) {
                $subConditions = self::buildConditions($item, $table, $companyId, 'AND');
                $conditions[] = '(' . implode(' AND ', $subConditions) . ')';
            }
        }

        return $conditions;
    }

    private static function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        } elseif (is_int($value) || is_float($value)) {
            return $value;
        } elseif (is_string($value)) {
            return "'" . addslashes($value) . "'";
        } elseif (is_array($value)) {
            return "'" . json_encode($value) . "'";
        } else {
            return "'" . addslashes((string) $value) . "'";
        }
    }

    public static function extractFieldPaths(string $domain): array
    {
        $domain = html_entity_decode($domain);
        $matches = [];

        // Captura todos los field paths: 'algo.algo2.algo3'
        preg_match_all("/\\('([a-zA-Z0-9_.]+)'/", $domain, $matches);

        return array_unique($matches[1] ?? []);
    }

}
