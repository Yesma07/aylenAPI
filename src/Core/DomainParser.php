<?php

namespace App\Core;

class DomainParser
{
    public static function parse(string $domain, array $fieldMap): array
    {
        $domain = html_entity_decode($domain);
        $structure = self::normalizeToPhpArray($domain);

        if (!is_array($structure)) {
            throw new \Exception("Dominio inválido, debe ser array");
        }

        return self::buildConditions($structure, $fieldMap);
    }

    private static function normalizeToPhpArray(string $input): array
    {
        $input = trim($input);

        // Sustituir True/False estilo Python
        $input = str_replace(['True', 'False'], ['true', 'false'], $input);

        // Reemplazar paréntesis que encierran tuplas por corchetes
        $input = preg_replace_callback('/\(([^()]+?)\)/', function ($matches) {
            return '[' . $matches[1] . ']';
        }, $input);

        // Convertir comillas simples en dobles
        $input = str_replace("'", '"', $input);

        // Convertir operadores lógicos OR[ y AND[ en estructura JSON
        $input = preg_replace_callback('/([A-Z]+)\[/', function ($matches) {
            return '{"' . $matches[1] . '": [';
        }, $input);

        // Cerrar llaves por cada apertura de OR/AND
        $orCount = substr_count($input, '{"OR": [');
        $andCount = substr_count($input, '{"AND": [');
        $toClose = $orCount + $andCount;

        $input = rtrim($input);
        while ($toClose > 0) {
            $input .= ']}';
            $toClose--;
        }

        // Corregir comas colgantes antes de cierre de arreglo
        $input = preg_replace('/,\s*]/', ']', $input);

        $parsed = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Dominio mal formado: " . json_last_error_msg());
        }

        return $parsed;
    }

    private static function buildConditions(array $domain, array $fieldMap, string $logic = 'AND'): array
    {
        $conditions = [];

        foreach ($domain as $key => $item) {
            if (is_string($key) && in_array($key, ['OR', 'AND'])) {
                $subConditions = self::buildConditions($item, $fieldMap, $key);
                $glue = $key === 'OR' ? ' OR ' : ' AND ';
                $conditions[] = '(' . implode($glue, $subConditions) . ')';
                continue;
            }

            if (is_array($item) && count($item) === 3) {
                [$field, $operator, $value] = $item;

                if (!isset($fieldMap[$field])) {
                    throw new \Exception("Campo lógico '{$field}' no encontrado en fieldMap.");
                }

                $column = "t." . $fieldMap[$field];

                if (in_array(strtolower($operator), ['in', 'not in']) && is_array($value)) {
                    $formattedValue = '(' . implode(', ', array_map([self::class, 'formatValue'], $value)) . ')';
                } else {
                    $formattedValue = self::formatValue($value);
                }

                $conditions[] = "{$column} {$operator} {$formattedValue}";
            } elseif (is_array($item)) {
                $subConditions = self::buildConditions($item, $fieldMap, 'AND');
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
            return "'" . addslashes((string)$value) . "'";
        }
    }
}
