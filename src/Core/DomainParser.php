<?php

namespace App\Core;

class DomainParser
{
    public static function parse(string $domain): array
    {
        $domain = trim($domain);

        if (empty($domain) || $domain === "[]") {
            // Puedes cambiar esto a ["1 = 1"] si quieres evitar dominios vacíos
            return [];
        }

        return [self::transformDomain($domain)];
    }

    private static function transformDomain(string $domain): string
    {
        // Limpieza básica de formato
        $domain = preg_replace('/^\[|\]$/', '', $domain); // elimina los [] externos
        $conditions = [];

        // Detectar condiciones OR anidadas
        if (str_starts_with($domain, 'OR[')) {
            $isOr = true;
            $domain = substr($domain, 3, -1); // eliminar OR[ ... ]
        } else {
            $isOr = false;
        }

        // Separar condiciones individuales
        preg_match_all("/\('([^']+)',\s*'([^']+)',\s*'?(.*?)'?\)/", $domain, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            [$full, $field, $operator, $value] = $match;
            $field = "t.$field";
            $operator = strtoupper($operator);

            if (in_array($operator, ['IN', 'NOT IN'])) {
                // Convertir a arreglo SQL
                $value = trim($value, '[]');
                $items = array_map(fn($v) => "'" . trim($v, " '\"") . "'", explode(',', $value));
                $value = '(' . implode(', ', $items) . ')';
                $conditions[] = "$field $operator $value";
            } else {
                $value = addslashes($value);
                $conditions[] = "$field $operator '$value'";
            }
        }

        if (empty($conditions)) {
            return '';
        }

        $glue = $isOr ? ' OR ' : ' AND ';
        return implode($glue, $conditions);
    }
}
