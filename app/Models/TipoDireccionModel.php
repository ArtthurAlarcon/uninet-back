<?php
namespace App\Models;

use CodeIgniter\Model;

class TipoDireccionModel extends Model
{
    protected $table = 'tipos_direccion';
    protected $primaryKey = 'id_tipo_direccion';
    protected $allowedFields = ['tipo', 'descripcion'];
}