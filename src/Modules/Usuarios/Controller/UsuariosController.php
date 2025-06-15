<?php

namespace App\Modules\Usuarios\Controller;

use App\Core\BaseModelController;

class UsuariosController extends BaseModelController
{
    protected string $table = 'm001_usuarios';
    protected string $primaryKey = 'id';
}

