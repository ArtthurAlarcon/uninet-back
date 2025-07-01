<?php

namespace App\Models;

use CodeIgniter\Model;

class ContactoModel extends Model {
    protected $table      = 'contactos';
    protected $primaryKey = 'id_contacto';
    protected $allowedFields = [
        'nombre', 
        'apellido_paterno', 
        'apellido_materno', 
        'fecha_nacimiento', 
        'alias', 
        'foto_path'
    ];

    protected $useAutoIncrement = true;
    
    protected $returnType = 'array';
    
    protected $useTimestamps = true;
    protected $createdField = 'fecha_creacion';
    protected $updatedField = 'fecha_actualizacion';

    public function getContactosConRelaciones() {
        $sql =
            "SELECT 
                c.id_contacto,
                c.nombre,
                c.apellido_paterno,
                c.apellido_materno,
                c.fecha_nacimiento,
                c.alias,
                c.foto_path,
                GROUP_CONCAT(DISTINCT CONCAT_WS('|', 
                    t.id_telefono, 
                    t.telefono, 
                    IFNULL(tt.tipo, 'Sin tipo'), 
                    IFNULL(t.etiqueta_personalizada, ''),
                    IFNULL(t.notas, '')
                ) SEPARATOR ';;') AS telefonos,

                GROUP_CONCAT(DISTINCT CONCAT_WS('|', 
                    e.id_email, 
                    e.email, 
                    e.es_principal,
                    IFNULL(e.notas, '')
                ) SEPARATOR ';;') AS emails,

                GROUP_CONCAT(DISTINCT CONCAT_WS('|', 
                    d.id_direccion,
                    IFNULL(d.calle, ''),
                    IFNULL(d.numero_exterior, ''),
                    IFNULL(d.numero_interior, ''),
                    IFNULL(d.colonia, ''),
                    IFNULL(d.municipio, ''),
                    IFNULL(d.estado, ''),
                    IFNULL(d.codigo_postal, ''),
                    IFNULL(d.pais, ''),
                    IFNULL(td.tipo, 'Sin tipo'),
                    IFNULL(d.etiqueta_personalizada, ''),
                    IFNULL(d.notas, '')
                ) SEPARATOR ';;') AS direcciones

            FROM contactos c
            LEFT JOIN telefonos_contacto t ON t.id_contacto = c.id_contacto
            LEFT JOIN tipos_telefono tt ON tt.id_tipo_telefono = t.id_tipo_telefono
            LEFT JOIN emails_contacto e ON e.id_contacto = c.id_contacto
            LEFT JOIN direcciones_contacto d ON d.id_contacto = c.id_contacto
            LEFT JOIN tipos_direccion td ON td.id_tipo_direccion = d.id_tipo_direccion
            GROUP BY 
                c.id_contacto
        ";

        $result = $this->db->query($sql)->getResultArray();

        foreach ($result as &$row) {
            $row['telefonos'] = array_map(function($item) {
                $parts = explode('|', $item);
                return [
                    'id_telefono' => $parts[0] ?? null,
                    'telefono' => $parts[1] ?? null,
                    'tipo' => $parts[2] ?? null,
                    'etiqueta_personalizada' => $parts[3] ?? null,
                    'notas' => $parts[4] ?? null
                ];
            }, explode(';;', $row['telefonos'] ?? ''));

            $row['emails'] = array_map(function($item) {
                $parts = explode('|', $item);
                return [
                    'id_email' => $parts[0] ?? null,
                    'email' => $parts[1] ?? null,
                    'es_principal' => (bool)($parts[2] ?? false),
                    'notas' => $parts[3] ?? null
                ];
            }, explode(';;', $row['emails'] ?? ''));

            $row['direcciones'] = array_map(function($item) {
                $parts = explode('|', $item);
                return [
                    'id_direccion' => $parts[0] ?? null,
                    'calle' => $parts[1] ?? null,
                    'numero_exterior' => $parts[2] ?? null,
                    'numero_interior' => $parts[3] ?? null,
                    'colonia' => $parts[4] ?? null,
                    'municipio' => $parts[5] ?? null,
                    'estado' => $parts[6] ?? null,
                    'codigo_postal' => $parts[7] ?? null,
                    'pais' => $parts[8] ?? null,
                    'tipo' => $parts[9] ?? null,
                    'etiqueta_personalizada' => $parts[10] ?? null,
                    'notas' => $parts[11] ?? null
                ];
            }, explode(';;', $row['direcciones'] ?? ''));

            $row['telefonos'] = array_filter($row['telefonos'], function($t) {
                return !empty($t['telefono']);
            });
            $row['emails'] = array_filter($row['emails'], function($e) {
                return !empty($e['email']);
            });
            $row['direcciones'] = array_filter($row['direcciones'], function($d) {
                return !empty($d['calle']) || !empty($d['colonia']);
            });

            $row['telefonos'] = array_values($row['telefonos']);
            $row['emails'] = array_values($row['emails']);
            $row['direcciones'] = array_values($row['direcciones']);
        }

        return $result;
    }

    public function getContactoCompleto($id)
    {
        $contacto = $this->find($id);
        if (!$contacto) return null;
    
        // Obtener relaciones
        $builder = $this->builder();
        $contacto['telefonos'] = $this->db->table('telefonos_contacto')
            ->where('id_contacto', $id)->get()->getResultArray();
        
        $contacto['emails'] = $this->db->table('emails_contacto')
            ->where('id_contacto', $id)->get()->getResultArray();
        
        $contacto['direcciones'] = $this->db->table('direcciones_contacto')
            ->where('id_contacto', $id)->get()->getResultArray();
    
        return $contacto;
    }

}