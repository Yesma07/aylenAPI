<?php

namespace App\Core;

use PDO;
use PDOException;
use App\Core\Exceptions\HttpException;
use App\Helpers\ModelHelper;
use App\Helpers\FieldMapperHelper;

class Database
{
    private static array $instances = [];
    private PDO $pdo;

    private function __construct(array $config)
    {
        try {
            $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['dbname']}";
            $this->pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        } catch (PDOException $e) {
            throw new HttpException("Error en la conexión a la base de Datos: " . $e->getMessage(), 500);
        }
    }

    public static function instance(?int $companyId = null): self
    {
        $companyKey = $companyId ?? 0;

        if (!isset(self::$instances[$companyKey])) {
            $config = [
                'host'     => $_ENV["DB_COMPANY_{$companyKey}_HOST"] ?? null,
                'port'     => $_ENV["DB_COMPANY_{$companyKey}_PORT"] ?? null,
                'dbname'   => $_ENV["DB_COMPANY_{$companyKey}_DBNAME"] ?? null,
                'user'     => $_ENV["DB_COMPANY_{$companyKey}_USER"] ?? null,
                'password' => $_ENV["DB_COMPANY_{$companyKey}_PASSWORD"] ?? null,
            ];

            if (in_array(null, $config, true)) {
                throw new HttpException("Configuración de base de datos incompleta para la empresa {$companyKey}", 500);
            }

            self::$instances[$companyKey] = new self($config);
        }

        return self::$instances[$companyKey];
    }


    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function lastInsertId(string $seq): string|int
    {
        return $this->pdo->lastInsertId($seq);
    }

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    private function extractPrefix(string $table): string
    {
        // El prefijo es lo que va antes del primer guión bajo
        $parts = explode('_', $table);
        return $parts[0]; // ej: fc001
    }

    //Definición de Métodos Genéricos
 public function genericSearch(array $payload, string $table): array
    {
        $builder = new QueryBuilder($table, 't');

        $fields = $payload['fields_names'] ?? [];
        $prefix = $this->extractPrefix($table);

        // Si está vacío, usa 'id' pero con prefijo
        if (empty($fields)) {
            $fields = ['id'];
        }

        foreach ($fields as $field) {
            if (str_contains($field, '.')) {
                // JOIN anidado, ejemplo: area.nombre
                $parts = explode('.', $field);
                $alias = substr($parts[0], 0, 1); // ejemplo: a de area
                $joinTable = "mXXX_{$parts[0]}";
                $builder->addJoin($joinTable, "{$alias}.id = t.{$parts[0]}_id", $alias);
                $builder->addSelect("{$alias}.{$parts[1]}", "{$parts[0]}_{$parts[1]}");
            } else {
                $realField = $field === 'id' ? 'id' : "{$prefix}_{$field}";
                $builder->addSelect("t.{$realField}", $field);
            }
        }

        $domain = $payload['domain'] ?? "[]";
        $parsedDomain = DomainParser::parse($domain);

        if (!empty($parsedDomain)) {
            foreach ($parsedDomain as $condition) {
                if (!empty(trim($condition))) {
                    $builder->addWhere($condition);
                }
            }
        }

        foreach ($payload['order_by'] ?? [] as $order) {
            $builder->setOrder($order);
        }

        $builder->setLimit($payload['limit'] ?? 0);
        $builder->setOffset($payload['offset'] ?? 0);

        $sql = $builder->build();
        echo "SQL: {$sql}\n";
        return $this->fetchAll($sql);
    }

    public function genericCreate(array $payload, string $table, $pk): array
    {
        $model = $payload['model'] ?? null;
        $record = $payload['record'] ?? [];
        $fields = $payload['fields_names'] ?? ['id', 'hash'];

        if (!$model || empty($record)) {
            throw new HttpException("Modelo o registro no proporcionado.", 400);
        }

        $modelConfig = ModelHelper::getModelConfig($model);
        if (!$modelConfig) {
            throw new HttpException("Modelo {$model} no encontrado.", 404);
        }

        $table = $modelConfig['table'];
        $fieldMap = $modelConfig['fields'];

        $this->pdo->beginTransaction();

        try {
            // Filtrar Campos Válidos
            $cleanFields = [];

            foreach ($record as $field => $value) {
                if ($field !== 'nested' && isset($fieldMap[$field])) {
                    $cleanFields[$fieldMap[$field]] = $value;
                }
            }

            // Insertar el registro
            $cols = implode(', ', array_keys($cleanFields));
            $placeholders = implode(', ', array_fill(0, count($cleanFields), '?'));
            $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$placeholders}) RETURNING id, hash");
            $stmt->execute(array_values($cleanFields));
            $newId = $stmt->fetchColumn();

            // Insertar Registros Anidados
            if ($record['nested']) {
                $nested = $record['nested'];
                $referModel = $nested['model'] ?? null;
                $fieldRefer = $nested['field_refer'] ?? null;
                $recordsNested = $nested['record'] ?? [];

                $nestedConfig = ModelHelper::getModelConfig($referModel);
                if (!$nestedConfig) {
                    throw new HttpException("Modelo anidado {$referModel} no encontrado.", 404);
                }

                $nestedTable = $nestedConfig['table'];
                $nestedFieldMap = $nestedConfig['fields'];

                foreach ($recordsNested as $nestedRecord) {
                    $cleanNestedFields = [];
                    foreach ($nestedRecord as $field => $value) {
                        if (isset($nestedFieldMap[$field])) {
                            $cleanNestedFields[$nestedFieldMap[$field]] = $value;
                        }
                    }

                    if (!isset($nestedFieldMap[$fieldRefer])) {
                        throw new HttpException("Campo de referencia {$fieldRefer} no encontrado en el modelo anidado {$referModel}.", 400);
                    }

                    $cleanNestedFields[$nestedFieldMap[$fieldRefer]] = $newId;

                    $colsNested = implode(', ', array_keys($cleanNestedFields));
                    $placeholdersNested = implode(', ', array_fill(0, count($cleanNestedFields), '?'));
                    $stmtNested = $this->pdo->prepare("INSERT INTO {$nestedTable} ({$colsNested}) VALUES ({$placeholdersNested})");
                    $stmtNested->execute(array_values($cleanNestedFields));
                }
            }

            $this->pdo->commit();

            $out = ['id' => $newId, 'hash' => $cleanFields['hash'] ?? null];
            if (count($fields) > 0) {
                $colFields = array_map(fn($f) => $fieldMap[$f] ?? null, $fields);
                $colFields = array_filter($colFields);
                $colList = implode(', ', $colFields);
                $stmtData = $this->pdo->prepare("SELECT {$colList} FROM {$table} WHERE id = ?");
                $stmtData->execute([$newId]);
                $out = $stmtData->fetchAll(PDO::FETCH_ASSOC);
            }

            return [
                'status' => 'success',
                'data' => $out
            ];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return [
                'status' => 'error',
                'message' => "Error al crear el registro: " . $e->getMessage()
            ];
        }
    }

    public function genericWrite(array $payload, string $table): array
    {
        $model = $payload['model'] ?? null;
        $values = $payload['values'] ?? [];
        $domain = $payload['domain'] ?? "[]";

        if (!$model || empty($values)) {
            throw new HttpException("Modelo o valores no proporcionados.", 400);
        }

        $modelConfig = ModelHelper::getModelConfig($model);
        if (!$modelConfig) {
            throw new HttpException("Modelo {$model} no encontrado.", 404);
        }

        $table = $modelConfig['table'];
        $fieldMap = $modelConfig['fields'];

        $setParts = [];
        $params = [];

        foreach ($values as $field => $value) {
            if (!isset($fieldMap[$field])) {
                throw new HttpException("Campo {$field} no encontrado en el modelo {$model}.", 400);
            }

            $setParts[] = "{$fieldMap[$field]} = ?";
            $params[] = $value;
        }

        //Parsear el dominio
        $parsedDomain = DomainParser::parse($domain, $fieldMap);
        if (empty($parsedDomain)) {
            throw new HttpException("Dominio no válido o vacío.", 400);
        }

        $whereSql = $parsedDomain['sql'];
        $params = array_merge($params, $parsedDomain['params']);

        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$whereSql}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'status' => 'success',
            'updated_rows' => $stmt->rowCount()
        ];
    }

    public function genericDelete(array $payload, string $table, string $pk): array
    {
        $model = $payload['model'] ?? null;
        $domain = $payload['domain'] ?? "[]";

        if (!$model) {
            throw new HttpException("Modelo no proporcionado.", 400);
        }

        $modelConfig = ModelHelper::getModelConfig($model);
        if (!$modelConfig) {
            throw new HttpException("Modelo {$model} no encontrado.", 404);
        }

        $table = $modelConfig['table'];
        $fieldMap = $modelConfig['fields'];
        $aliveField = $modelConfig['alive'] ?? null;
        if (!$aliveField) {
            throw new HttpException("Modelo {$model} no tiene campo 'alive' definido.", 400);
        }

        //Parsear el dominio
        $parsedDomain = DomainParser::parse($domain, $fieldMap);
        if (empty($parsedDomain)) {
            throw new HttpException("Dominio no válido o vacío.", 400);
        }

        $whereSql = $parsedDomain['sql'];
        $params = $parsedDomain['params'];

        $sql = "UPDATE {$table} SET {$aliveField} = false WHERE {$whereSql}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return [
            'status' => 'success',
            'deleted_rows' => $stmt->rowCount()
        ];
    }

    public function genericMethod(): array
    {
        $model = $payload['model'] ?? null;
        $method = $payload['method'] ?? null;
        $data = $payload['data'] ?? [];
        $context = $payload['context'] ?? [];

        if (!$model || !$method) {
            throw new HttpException("Modelo o método no proporcionados.", 400);
        }

        return MethodDispatcher::dispatch($model, $method, $data, $context);
    }
}
