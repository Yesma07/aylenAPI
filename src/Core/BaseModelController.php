<?php

namespace App\Core;

use App\Core\Request;
use App\Core\Database;

abstract class BaseModelController
{
    protected Request $request;
    protected Database $db;
    protected string $table;
    protected string $primaryKey;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->db = Database::instance($request->get('context')['company'] ?? null);
    }

    public function search(): array
    {
        return $this->db->genericSearch($this->request->getBody(), $this->table);
    }

    public function create(): array
    {
        return $this->db->genericCreate($this->request->getBody(), $this->table, $this->primaryKey);
    }

    public function write(): array
    {
        return $this->db->genericWrite($this->request->getBody(), $this->table);
    }

    public function delete(): array
    {
        return $this->db->genericDelete($this->request->getBody(), $this->table, $this->primaryKey);
    }

    public function method(): array
    {
        return $this->db->genericMethod($this->request->getBody(), $this->table);
    }
}
