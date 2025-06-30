<?php
namespace App\Controllers;

use App\Models\ContactoModel;
use CodeIgniter\API\ResponseTrait;  // <- Añade esto para respuestas JSON

class Contactos extends BaseController {
    use ResponseTrait;  // <- Habilita métodos como `respond()`

    protected $contactoModel;

    public function __construct() {
        $this->contactoModel = new ContactoModel();
    }

    // GET /contactos → Listar todos los contactos (JSON)
    public function index() {
        $contactos = $this->contactoModel->findAll();
        return $this->respond($contactos);  // Devuelve JSON automático
    }

    // GET /contactos → Listar todos los contactos con datos (JSON)
    public function listarCompletos() {
        $contactos = $this->contactoModel->getContactosCompletosAgrupados();
        return $this->respond($contactos);
    }

    public function listarConRelaciones() { // <- ¡Nombre exacto!
        $data['data'] = $this->contactoModel->getContactosConRelaciones();

        return $this->respond($data);
    }

    // POST /contactos → Crear un nuevo contacto (JSON)
    public function create() {
        // 1. Obtener todos los datos del POST (incluyendo el path de la imagen)
        $data = $this->request->getPost(); // Esto captura todos los campos del formData

        // 2. Decodificar los JSON strings de relaciones
        $telefonos = json_decode($data['telefonos'] ?? '[]', true);
        $emails = json_decode($data['emails'] ?? '[]', true);
        $direcciones = json_decode($data['direcciones'] ?? '[]', true);

        // 3. Validación básica
        if (empty($data['nombre'])) {
            return $this->failValidationErrors('El nombre es obligatorio');
        }

        // 4. Iniciar transacción
        $this->db->transBegin();

        try {
            // 5. Insertar contacto principal (incluyendo foto_path si existe)
            $idContacto = $this->contactoModel->insert([
                'nombre' => $data['nombre'],
                'apellido_paterno' => $data['apellido_paterno'] ?? null,
                'apellido_materno' => $data['apellido_materno'] ?? null,
                'foto_path' => $data['foto_path'] ?? null, // <- Aquí va el path ya procesado
                // ... otros campos
            ]);

            // 6. Procesar relaciones (mismo código anterior)
            // ... [insertar teléfonos, emails, direcciones]

            // 7. Confirmar transacción
            $this->db->transCommit();

            return $this->respondCreated([
                'status' => 'success',
                'id' => $idContacto,
                'foto_path' => $data['foto_path'] ?? null // Opcional: devolver el path
            ]);

        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->failServerError('Error: ' . $e->getMessage());
        }
    }   

    private function guardarRelaciones($idContacto, $data) {
        $db = \Config\Database::connect();

        // Insertar teléfonos
        if (isset($data->telefonos)) {
            foreach ($data->telefonos as $telefono) {
                $db->table('telefonos_contacto')->insert([
                    'id_contacto' => $idContacto,
                    'telefono' => $telefono->numero,
                    'id_tipo_telefono' => $telefono->tipo_id ?? null,
                    'etiqueta_personalizada' => $telefono->etiqueta ?? null,
                    'notas' => $telefono->notas ?? null
                ]);
            }
        }

        // Insertar emails
        if (isset($data->emails)) {
            foreach ($data->emails as $email) {
                $db->table('emails_contacto')->insert([
                    'id_contacto' => $idContacto,
                    'email' => $email->direccion,
                    'es_principal' => $email->es_principal ?? false,
                    'notas' => $email->notas ?? null
                ]);
            }
        }

        // Insertar direcciones
        if (isset($data->direcciones)) {
            foreach ($data->direcciones as $direccion) {
                $db->table('direcciones_contacto')->insert([
                    'id_contacto' => $idContacto,
                    'calle' => $direccion->calle ?? null,
                    'numero_exterior' => $direccion->numero_exterior ?? null,
                    'numero_interior' => $direccion->numero_interior ?? null,
                    'colonia' => $direccion->colonia ?? null,
                    'municipio' => $direccion->municipio ?? null,
                    'estado' => $direccion->estado ?? null,
                    'codigo_postal' => $direccion->codigo_postal ?? null,
                    'pais' => $direccion->pais ?? null,
                    'id_tipo_direccion' => $direccion->tipo_id ?? null,
                    'etiqueta_personalizada' => $direccion->etiqueta ?? null,
                    'notas' => $direccion->notas ?? null
                ]);
            }
        }
    }

    // GET /contactos/{id} → Obtener un contacto por ID (JSON)
    public function show($id) {
        $contacto = $this->contactoModel->find($id);
        if (!$contacto) {
            return $this->failNotFound('Contacto no encontrado');
        }
        return $this->respond($contacto);
    }

    // PUT /contactos/{id} → Actualizar un contacto (JSON)
    public function update($id) {
        $data = [
            'nombre'   => $this->request->getJSONVar('nombre'),
            'telefono' => $this->request->getJSONVar('telefono'),
            'email'    => $this->request->getJSONVar('email')
        ];

        $this->contactoModel->update($id, $data);
        return $this->respond(['message' => 'Contacto actualizado']);
    }

    // DELETE /contactos/{id} → Eliminar un contacto (JSON)
    public function delete($id) {
        $contacto = $this->contactoModel->find($id);
        if (!$contacto) {
            return $this->failNotFound('Contacto no encontrado');
        }
        $this->contactoModel->delete($id);
        return $this->respondDeleted(['message' => 'Contacto eliminado']);
    }
}