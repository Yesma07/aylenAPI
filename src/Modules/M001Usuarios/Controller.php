<?php 

namespace App\Modules\M001Usuarios;

use App\Core\Request;
use App\Core\BaseModelController;

class Controller extends BaseModelController
{
    protected string $table = 'm001_usuarios';
    protected string $primaryKey = 'f001_id';

    public function __construct(Request $request)
    {
        parent::__construct($request);
    }

    // Métodos específicos del controlador M001Usuarios
    public function getActiveUsers(): array
    {
        $query = "SELECT * FROM {$this->table} WHERE f001_estado = TRUE";
        return $this->db->fetchAll($query);
    }

}