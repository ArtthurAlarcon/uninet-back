<?php
namespace App\Models;

use CodeIgniter\Model;

class TipoTelefonoModel extends Model
{
    protected $table = 'tipos_telefono';
    protected $primaryKey = 'id_tipo_telefono';
    protected $allowedFields = ['tipo', 'descripcion'];
}