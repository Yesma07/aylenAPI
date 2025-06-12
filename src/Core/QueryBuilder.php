<?php

namespace App\Core;

class QueryBuilder
{
    private string $baseTable;
    private string $alias;
    private array $selectFields = [];
    private array $joins = [];
    private array $where = [];
    private array $orderBy = [];
    private int $limit = 0;
    private int $offset = 0;

    public function __construct(string $table, string $alias = 't')
    {
        $this->baseTable = $table;
        $this->alias = $alias;
    }

    public function addSelect(string $field, ?string $as = null): void
    {
        $as = $as ?? str_replace('.', '_', $field);
        $this->selectFields[] = "{$field} AS \"{$as}\"";
    }

    public function addJoin(string $table, string $on, string $alias): void
    {
        $this->joins[] = "LEFT JOIN {$table} {$alias} ON {$on}";
    }

    public function addWhere(string $condition): void
    {
        $this->where[] = $condition;
    }

    public function setOrder(array $order): void
    {
        $this->orderBy[] = "{$order[0]} {$order[1]}";
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    public function setOffset(int $offset): void
    {
        $this->offset = $offset;
    }

    public function build(): string
    {
        $sql = "SELECT " . implode(', ', $this->selectFields) . " FROM {$this->baseTable} AS {$this->alias}";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if ($this->limit > 0) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset > 0) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }
}
