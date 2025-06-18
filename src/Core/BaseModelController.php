<?php

namespace App\Core;

use App\Core\Request;
use App\Core\Database;
use App\Core\Exceptions\HttpException;

abstract class BaseModelController
{
    protected Request $request;
    protected Database $db;
    protected string $table;
    protected string $primaryKey;

    public function __construct(Request $request)
    {
        $this->request = $request;

        $companyId = $request->getContext('company') ?? null;
        $this->db = Database::instance($companyId);

        $this->validateActiveUser();
    }

    protected function validateActiveUser(): void
    {
        $userId = $this->request->getContext('user_env');

        if (!$userId) {
            throw new HttpException('Falta el ID de usuario en el contexto.', 400);
        }

        $searchPayload = [
            'model' => 'usuarios',
            'fields_names' => ['id', 'estado', 'alive'],
            'domain' => json_encode([['id', '=', $userId]]),
            'context' => $this->request->getBody()['context']
        ];

        $userResult = $this->db->genericSearch($searchPayload, 'm001_usuarios');

        if (empty($userResult) || !isset($userResult[0])) {
            throw new HttpException("Usuario con ID {$userId} no encontrado.", 403);
        }

        $user = $userResult[0];

        if (!($user['estado'] ?? false) || !($user['alive'] ?? false)) {
            throw new HttpException("Usuario inactivo o eliminado. No tiene permitido operar.", 403);
        }
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
        return $this->db->genericMethod();
    }
}
