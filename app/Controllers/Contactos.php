<?php
namespace App\Controllers;

use App\Models\ContactoModel;
use App\Models\TipoTelefonoModel;
use App\Models\TipoDireccionModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\BaseConnection;

class Contactos extends BaseController
{
    use ResponseTrait;

    protected $contactoModel;
    protected $db; 

    public function __construct() {
        $this->contactoModel = new ContactoModel();
        $this->db = \Config\Database::connect();
    }

    // GET /contactos → Listar todos los contactos (JSON)
    public function index() {
        $contactos = $this->contactoModel->findAll();
        return $this->respond($contactos); 
    }

    // GET /contactos → Listar todos los contactos con datos (JSON)
    public function listarCompletos() {
        $contactos = $this->contactoModel->getContactosCompletosAgrupados();
        return $this->respond($contactos);
    }

    public function listarConRelaciones() { 
        $data['data'] = $this->contactoModel->getContactosConRelaciones();

        return $this->respond($data);
    }

    public function create()
    {
        // 1. Validar datos básicos
        $rules = [
            'nombre' => 'required|max_length[50]',
            'apellido_paterno' => 'required|max_length[50]',
            'apellido_materno' => 'permit_empty|max_length[50]',
            'fecha_nacimiento' => 'permit_empty|valid_date',
            'alias' => 'permit_empty|max_length[50]'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        // 2. Procesar imagen
        $fotoPath = null;
        $file = $this->request->getFile('foto');

        if ($file && $file->isValid() && !$file->hasMoved()) {
            $uploadPath = WRITEPATH . 'uploads/contactos';
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $newName = $file->getRandomName();
            $file->move($uploadPath, $newName);
            $fotoPath = $newName;
        }

        // 3. Formatear fecha correctamente
        $fechaNacimiento = $this->request->getPost('fecha_nacimiento');
        if ($fechaNacimiento) {
            try {
                $fechaObj = new \DateTime($fechaNacimiento);
                $fechaFormateada = $fechaObj->format('Y-m-d');
            } catch (\Exception $e) {
                $fechaFormateada = null;
            }
        } else {
            $fechaFormateada = null;
        }

        // 4. Iniciar transacción
        $this->db->transBegin();

        try {
            // 5. Insertar contacto principal
            $contactoData = [
                'nombre' => $this->request->getPost('nombre'),
                'apellido_paterno' => $this->request->getPost('apellido_paterno'),
                'apellido_materno' => $this->request->getPost('apellido_materno'),
                'fecha_nacimiento' => $fechaFormateada,
                'alias' => $this->request->getPost('alias'),
                'foto_path' => $fotoPath
            ];

            log_message('debug', 'Datos del contacto: ' . print_r($contactoData, true));

            $idContacto = $this->contactoModel->insert($contactoData);

            if (!$idContacto) {
                throw new \RuntimeException('Error al crear el contacto');
            }

            // 6. Procesar relaciones (telefonos, emails, direcciones)
            $this->procesarRelaciones($idContacto);

            // 7. Confirmar transacción
            $this->db->transCommit();

            return $this->respondCreated([
                'status' => 'success',
                'id' => $idContacto,
                'message' => 'Contacto creado exitosamente'
            ]);

        } catch (\Exception $e) {
            $this->db->transRollback();

            // Eliminar imagen si hubo error
            if ($fotoPath && file_exists(WRITEPATH . 'uploads/contactos/' . $fotoPath)) {
                unlink(WRITEPATH . 'uploads/contactos/' . $fotoPath);
            }

            log_message('error', 'Error al crear contacto: ' . $e->getMessage());
            return $this->failServerError('Error al crear el contacto: ' . $e->getMessage());
        }
    }

    protected function procesarRelaciones($idContacto)
    {
        // Procesar teléfonos
        $telefonos = $this->request->getPost('telefonos');
        if ($telefonos) {
            $telefonos = json_decode($telefonos, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($telefonos)) {
                foreach ($telefonos as $telefono) {
                    $this->guardarTelefono($idContacto, $telefono);
                }
            }
        }

        // Procesar emails
        $emails = $this->request->getPost('emails');
        if ($emails) {
            $emails = json_decode($emails, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($emails)) {
                foreach ($emails as $email) {
                    $this->guardarEmail($idContacto, $email);
                }
            }
        }

        // Procesar direcciones
        $direcciones = $this->request->getPost('direcciones');
        if ($direcciones) {
            $direcciones = json_decode($direcciones, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($direcciones)) {
                foreach ($direcciones as $direccion) {
                    $this->guardarDireccion($idContacto, $direccion);
                }
            }
        }
    }

    protected function guardarTelefono($idContacto, $telefono)
    {
        $this->db->table('telefonos_contacto')->insert([
            'id_contacto' => $idContacto,
            'telefono' => $telefono['telefono'],
            'id_tipo_telefono' => $this->obtenerTipoTelefono($telefono['tipo'] ?? null),
            'etiqueta_personalizada' => $telefono['etiqueta_personalizada'] ?? null,
            'notas' => $telefono['notas'] ?? null
        ]);
    }

    protected function guardarEmail($idContacto, $email)
    {
        $this->db->table('emails_contacto')->insert([
            'id_contacto' => $idContacto,
            'email' => $email['email'],
            'es_principal' => $email['es_principal'] ?? false,
            'notas' => $email['notas'] ?? null
        ]);
    }

    protected function guardarDireccion($idContacto, $direccion)
    {
        $this->db->table('direcciones_contacto')->insert([
            'id_contacto' => $idContacto,
            'calle' => $direccion['calle'],
            'numero_exterior' => $direccion['numero_exterior'],
            'numero_interior' => $direccion['numero_interior'] ?? null,
            'colonia' => $direccion['colonia'],
            'municipio' => $direccion['municipio'],
            'estado' => $direccion['estado'],
            'codigo_postal' => $direccion['codigo_postal'],
            'pais' => $direccion['pais'] ?? 'México',
            'id_tipo_direccion' => $this->obtenerTipoDireccion($direccion['tipo'] ?? null),
            'etiqueta_personalizada' => $direccion['etiqueta_personalizada'] ?? null,
            'notas' => $direccion['notas'] ?? null
        ]);
    }

    protected function obtenerTipoTelefono($tipo)
    {
        if (empty($tipo)) return null;
        
        $model = model('TipoTelefonoModel');
        $existente = $model->where('tipo', $tipo)->first();
        
        if ($existente) {
            return $existente['id_tipo_telefono'];
        }
        
        return $model->insert(['tipo' => $tipo, 'descripcion' => 'Creado automáticamente']);
    }

    protected function obtenerTipoDireccion($tipo)
    {
        if (empty($tipo)) return null;
        
        $model = model('TipoDireccionModel');
        $existente = $model->where('tipo', $tipo)->first();
        
        if ($existente) {
            return $existente['id_tipo_direccion'];
        }
        
        return $model->insert(['tipo' => $tipo, 'descripcion' => 'Creado automáticamente']);
    }

    public function updateWithPost($id)
    {
        // 1. Validar que el contacto exista
        $contacto = $this->contactoModel->find($id);
        if (!$contacto) {
            return $this->failNotFound('Contacto no encontrado');
        }
    
        // 2. Validar datos básicos
        $rules = [
            'nombre' => 'required|max_length[50]',
            'apellido_paterno' => 'required|max_length[50]',
            'apellido_materno' => 'permit_empty|max_length[50]',
            'fecha_nacimiento' => 'permit_empty|valid_date',
            'alias' => 'permit_empty|max_length[50]'
        ];
    
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }
    
        // 3. Procesar imagen
        $fotoPath = $contacto['foto_path'];
        $file = $this->request->getFile('foto');
        $shouldDeletePhoto = $this->request->getPost('shouldDeletePhoto') === 'true';
    
        if ($shouldDeletePhoto && $fotoPath) {
            if (file_exists(WRITEPATH . 'uploads/contactos/' . $fotoPath)) {
                unlink(WRITEPATH . 'uploads/contactos/' . $fotoPath);
            }
            $fotoPath = null;
        } elseif ($file && $file->isValid() && !$file->hasMoved()) {
            if ($fotoPath && file_exists(WRITEPATH . 'uploads/contactos/' . $fotoPath)) {
                unlink(WRITEPATH . 'uploads/contactos/' . $fotoPath);
            }
            $newName = $file->getRandomName();
            $file->move(WRITEPATH . 'uploads/contactos', $newName);
            $fotoPath = $newName;
        }
    
        $this->db->transBegin();
    
        try {
            $contactoData = [
                'nombre' => $this->request->getPost('nombre'),
                'apellido_paterno' => $this->request->getPost('apellido_paterno'),
                'apellido_materno' => $this->request->getPost('apellido_materno'),
                'fecha_nacimiento' => $this->request->getPost('fecha_nacimiento'),
                'alias' => $this->request->getPost('alias'),
                'foto_path' => $fotoPath
            ];
        
            $this->contactoModel->update($id, $contactoData);
        
            // 6. Procesar relaciones
            $telefonos = json_decode($this->request->getPost('telefonos') ?? '[]', true) ?? [];
            $emails = json_decode($this->request->getPost('emails') ?? '[]', true) ?? [];
            $direcciones = json_decode($this->request->getPost('direcciones') ?? '[]', true) ?? [];
        
            $this->eliminarRelaciones($id, $this->db);
            $this->procesarRelaciones($id, $telefonos, $emails, $direcciones);
        
            // 7. Confirmar transacción
            $this->db->transCommit();
        
            return $this->respond([
                'status' => 'success',
                'message' => 'Contacto actualizado exitosamente',
                'data' => ['foto_path' => $fotoPath]
            ]);
        
        } catch (\Exception $e) {
            $this->db->transRollback();
            log_message('error', 'Error al actualizar contacto: ' . $e->getMessage());
            return $this->failServerError('Error al actualizar el contacto: ' . $e->getMessage());
        }
    }

    public function delete($id = null)
    {
        if (empty($id)) {
            return $this->failNotFound('ID de contacto no proporcionado');
        }

        // Obtener el contacto primero para eliminar la imagen asociada
        $contacto = $this->contactoModel->find($id);
        if (!$contacto) {
            return $this->failNotFound('Contacto no encontrado');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            // Eliminar relaciones primero (por las claves foráneas)
            $this->eliminarRelaciones($id, $db);

            // Eliminar el contacto principal
            $deleted = $this->contactoModel->delete($id);
            if (!$deleted) {
                throw new \RuntimeException('Error al eliminar el contacto');
            }

            // Eliminar la imagen asociada si existe
            if (!empty($contacto['foto_path'])) {
                $this->eliminarArchivosContacto($contacto['foto_path']);
            }

            $db->transCommit();

            return $this->respondDeleted([
                'status' => 'success',
                'message' => 'Contacto eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'Error al eliminar contacto: ' . $e->getMessage());
            return $this->failServerError('Error al eliminar el contacto: ' . $e->getMessage());
        }
    }

    protected function eliminarRelaciones($idContacto, $db)
    {
        // Eliminar teléfonos
        $db->table('telefonos_contacto')->where('id_contacto', $idContacto)->delete();
        
        // Eliminar emails
        $db->table('emails_contacto')->where('id_contacto', $idContacto)->delete();
        
        // Eliminar direcciones
        $db->table('direcciones_contacto')->where('id_contacto', $idContacto)->delete();
        
        // Eliminar tipos de teléfono/dirección que ya no están en uso
        $this->limpiarTiposSinUso($db);
    }
    
    protected function limpiarTiposSinUso($db)
    {
        // Limpiar tipos de teléfono sin uso
        $db->query("DELETE FROM tipos_telefono 
                   WHERE id_tipo_telefono NOT IN 
                   (SELECT DISTINCT id_tipo_telefono FROM telefonos_contacto WHERE id_tipo_telefono IS NOT NULL)");
                   
        // Limpiar tipos de dirección sin uso
        $db->query("DELETE FROM tipos_direccion 
                   WHERE id_tipo_direccion NOT IN 
                   (SELECT DISTINCT id_tipo_direccion FROM direcciones_contacto WHERE id_tipo_direccion IS NOT NULL)");
    }

    protected function eliminarArchivosContacto($fotoPath)
    {
        $archivoPrincipal = WRITEPATH . 'uploads/contactos/' . $fotoPath;
        $archivoThumbnail = WRITEPATH . 'uploads/contactos/thumbs/' . $fotoPath;

        if (file_exists($archivoPrincipal)) {
            unlink($archivoPrincipal);
        }

        if (file_exists($archivoThumbnail)) {
            unlink($archivoThumbnail);
        }
    }

    public function show($id = null)
    {
        if (empty($id)) {
            return $this->failNotFound('ID de contacto no proporcionado');
        }

        $contacto['data'] = $this->contactoModel->getContactoCompleto($id);

        if (!$contacto) {
            return $this->failNotFound('Contacto no encontrado');
        }

        return $this->respond($contacto);
    }

    public function serveImage($filename)
    {
        // Ruta segura usando WRITEPATH (no accesible directamente desde web)
        $path = WRITEPATH . 'uploads/contactos/' . $filename;

        if (!file_exists($path)) {
            return $this->failNotFound('Imagen no encontrada');
        }

        // Obtener el tipo MIME correcto
        $mime = mime_content_type($path);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) {
            return $this->failForbidden('Tipo de archivo no permitido');
        }

        // Servir la imagen con el tipo MIME correcto
        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setBody(file_get_contents($path));
    }
}