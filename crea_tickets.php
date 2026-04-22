<?php
header("Content-Type: application/json");

// 1. Importar tu conexión existente
require_once __DIR__ . '/Sistema/config/bd.php'; 

class ServiceDeskAPI {
    private $pdo;
    private $BASE_URL = "https://servicedesk.grupoaxo.com/api/v3/";
    private $API_KEY = "423CEBBE-E849-4D17-9CA3-CD6AB3319401";

    public function __construct($db_connection) {
        $this->pdo = $db_connection;
    }
    //12024 id de la pantilla de pruebas
    // Función adaptada de app.py para obtener el siguiente técnico disponible
    private function obtenerTecnicoDisponible() {
        try {
            // 1. Array de candidatos activos y configurados en modo automático
            $stmt = $this->pdo->query("SELECT id, id_sistema, nombre FROM tecnicos WHERE activo = 1 AND modo_asignacion = 1 ORDER BY orden_asignacion ASC, id ASC");
            $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($tecnicos)) return null;
            $total_tecnicos = count($tecnicos);
            
            // 2. Obtener el ID del último técnico al que se le asignó (desde configuracion_asignacion id=1)
            $stmt_config = $this->pdo->query("SELECT valor FROM configuracion_asignacion WHERE id = 1");
            $fila_config = $stmt_config->fetch(PDO::FETCH_ASSOC);
            $ultimo_id = $fila_config ? (int)$fila_config['valor'] : null;
            
            // 3. Buscar el índice por el que vamos a empezar el ciclo
            $indice_inicio = 0;
            if ($ultimo_id) {
                foreach ($tecnicos as $i => $tec) {
                    if ((int)$tec['id'] === $ultimo_id) {
                        $indice_inicio = $i + 1; // empezamos por el SIGUIENTE
                        break;
                    }
                }
            }
            
            // 4. El índice actual se calcula con módulo (%) para que sea un ciclo infinito
            $indice_actual = $indice_inicio % $total_tecnicos;
            $tecnico_seleccionado = $tecnicos[$indice_actual];
            
            // 5. Guardar asignación en base de datos para que app.py y este script mantengan el puntero rotativo
            $this->guardarUltimoTecnicoAsignado($tecnico_seleccionado['id'], $ultimo_id, $total_tecnicos);
            
            return $tecnico_seleccionado['id_sistema'];
            
        } catch (Exception $e) {
            return null; 
        }
    }

    private function guardarUltimoTecnicoAsignado($id_tecnico, $ultimo_id_asignado, $total_disponibles) {
        try {
            // Compartimos el puntero del carrusel con la BD
            $stmt_config = $this->pdo->prepare("INSERT INTO configuracion_asignacion (id, valor) VALUES (1, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt_config->execute([$id_tecnico, $id_tecnico]);
            
            // Bitácora idéntica al registar_control_asignacion de Python
            $stmt_control = $this->pdo->prepare("INSERT INTO control_asignacion (id_tecnico, ultimo_id_asignado, total_disponibles, metodo) VALUES (?, ?, ?, 'carrusel')");
            $stmt_control->execute([$id_tecnico, $ultimo_id_asignado, $total_disponibles]);
        } catch (Exception $e) { }
    }

    public function ejecutar($id_plantilla, $nombre_usuario, $descripcion_usuario, $correo, $accion, $tipo_solicitud) {
        try {
            $plantilla_nombre = null;
            $plantilla_descripcion = "";
            $id_grupo = "954"; // Default fallback para grupo si no hay plantilla
            
            // Si el usuario envía la plantilla, buscamos en DB
            if ($id_plantilla) {
                // 2. Consultar la información de la plantilla incluyendo id_grupo y clasificación
                $stmt = $this->pdo->prepare("SELECT plantilla_incidente, descripcion, id_grupo, categoria, subcategoria, articulo, tipo_solicitud FROM plantillas_incidentes WHERE id = ?");
                $stmt->execute([$id_plantilla]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
                if ($data) {
                    $plantilla_nombre = $data['plantilla_incidente'];
                    $plantilla_descripcion = $data['descripcion'];
                    if (!empty($data['id_grupo'])) {
                        $id_grupo = $data['id_grupo'];
                    }
                } else {
                    return ["status" => "error", "message" => "ID de plantilla proporcionado no encontrado en DB local"];
                }
            }

            // 3. Obtener Técnico Disponible (lógica similar a app.py)
            $id_tecnico_disponible = $this->obtenerTecnicoDisponible();
            
            if (!$id_tecnico_disponible) {
                // Abortar si no hay técnico y se requería asignación
                return [
                    "status" => "error", 
                    "message" => "No hay técnicos disponibles para este requerimiento en este momento. Por favor, intente más tarde."
                ];
            }

            // 4. Preparar la Descripción Combinada
            $full_description = "<b>Solicitado por:</b> " . htmlspecialchars($nombre_usuario) . "<br><br>";
            $full_description .= "<b>Descripción del Usuario:</b><br>" . nl2br(htmlspecialchars($descripcion_usuario)) . "<br><br>";
            
            if ($plantilla_descripcion) {
                $full_description .= "<b>Descripción de la Plantilla:</b><br>" . $plantilla_descripcion;
            }
            
            if ($accion === "2") {
                $full_description = "<b>[Ticket generado y cerrado automáticamente]</b><br><br>" . $full_description;
            }
            
            // El Asunto es genérico si no hay plantilla
            $subject_final = $plantilla_nombre ? $plantilla_nombre : "Ticket generado vía API";

            // 5. Preparar el JSON simplificado Base
            $status_id = ($accion === "2") ? "4" : "1"; // 4=Espera Visto Bueno, 1=Abierto

            $request_data = [
                "subject" => $subject_final,
                "description" => $full_description,
                "requester" => ["email_id" => $correo], // El solicitante real por correo electrónico
                "technician" => ["id" => $id_tecnico_disponible],
                "group" => ["id" => $id_grupo], 
                "udf_fields" => [
                    "udf_pick_2114" => ["name" => "A PIE DE CALLE", "id" => "8428"],
                    "udf_pick_27" => ["name" => "TOMMY", "id" => "9925"]
                ],
                "status" => ["id" => $status_id]
            ];

            if ($accion === "2") {
                $request_data["is_fcr"] = true;
            }

            // Si hay plantilla encontrada, la incluimos
            if ($plantilla_nombre) {
                $request_data["template"] = ["name" => $plantilla_nombre];
                // Forzar campos obligatorios (ServiceDesk a veces los exige si la plantilla no los auto-complementa)
                if (!empty($data['categoria'])) { $request_data["category"] = ["name" => $data['categoria']]; }
                if (!empty($data['subcategoria'])) { $request_data["subcategory"] = ["name" => $data['subcategoria']]; }
                if (!empty($data['articulo'])) { $request_data["item"] = ["name" => $data['articulo']]; }
                if (!empty($data['tipo_solicitud'])) { $request_data["request_type"] = ["name" => $data['tipo_solicitud']]; }
            }

            // Si se va a cerrar, anexamos el resolution
            if ($accion === "2") {
                $request_data["resolution"] = [
                    "content" => "Ticket creado y cerrado automáticamente a petición del sistema."
                ];
            }

            $payload_crear = ["request" => $request_data];

            // 6. Ejecutar Creación (POST)
            $res_crear = $this->call("POST", "requests", $payload_crear);
            $request_id = $res_crear['request']['id'] ?? null;

            if ($request_id) {
                
                // Si la acción es cerrar, ejecutamos el PUT para forzar el cierre completo
                if ($accion === "2") {
                    $payload_cierre = [
                        "request" => [
                            "closure_info" => [
                                "requester_ack_resolution" => true,
                                "closure_comments" => "Cierre automático: Autentificación exitosa / Resolución directa.",
                                "closure_code" => ["name" => "Resolución Automática"]
                            ]
                        ]
                    ];

                    $res_cierre = $this->call("PUT", "requests/{$request_id}/close", $payload_cierre);
                    $status_final = '4'; // 2 = Cerrado
                } else {
                    $status_final = '1'; // 1 = Abierto
                }

                // 7. Generar esquema local log independiente
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS log_api_tickets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_plantilla_origen INT NULL,
                    nombre_solicitante VARCHAR(255) NULL,
                    descripcion TEXT NULL,
                    correo VARCHAR(255) NULL,
                    accion VARCHAR(50) NULL,
                    ticket_creado VARCHAR(50) NULL,
                    status_proceso VARCHAR(50) NULL,
                    tipo_solicitud VARCHAR(255) NULL,
                    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
                )");

                // Asegurar existencia de columna por actualizaciones
                try {
                    $this->pdo->exec("ALTER TABLE log_api_tickets ADD COLUMN tipo_solicitud VARCHAR(255)");
                } catch(PDOException $e) {}

                // Insertar el log híper detallado asegurando el Timezone de CDMX
                date_default_timezone_set('America/Mexico_City');
                $fecha_actual = date('Y-m-d H:i:s');
                $estado_proceso = ($status_final === '4') ? 'Creado y en espera de visto bueno' : 'Generado automaticamente y resuelto por agente';
                
                $log = $this->pdo->prepare("INSERT INTO log_api_tickets (id_plantilla_origen, nombre_solicitante, descripcion, correo, accion, ticket_creado, status_proceso, tipo_solicitud, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $log->execute([$id_plantilla, $nombre_usuario, $descripcion_usuario, $correo, $accion, $request_id, $estado_proceso, $tipo_solicitud, $fecha_actual]);

                return [
                    "status" => "success",
                    "servicedesk_id" => $request_id,
                    "tecnico_asignado" => $id_tecnico_disponible,
                    "message" => "Ticket " . ($accion === "2" ? "creado y cerrado" : "creado (abierto)") . " con éxito"
                ];
            }

            return [
                "status" => "error", 
                "message" => "Error de la API de ServiceDesk",
                "api_response" => $res_crear
            ];

        } catch (Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    private function call($method, $endpoint, $payload) {
        $url = $this->BASE_URL . $endpoint;
        $ch = curl_init($url);
        
        // Formato obligatorio: input_data=JSON_STRING
        $post_fields = http_build_query([
            'input_data' => json_encode($payload)
        ]);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $post_fields,
            CURLOPT_SSL_VERIFYPEER => false, 
            CURLOPT_HTTPHEADER => [
                "authtoken: " . $this->API_KEY,
                "Accept: application/v3.0+json",
                "Content-Type: application/x-www-form-urlencoded"
            ],
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
}

// 8. Receptar Parámetros Dinámicos (JSON payload, POST o GET)
$input_json = file_get_contents('php://input');
$data = json_decode($input_json, true) ?: [];

// Hacemos id_plantilla opcional.
$id_plantilla = $data['id_plantilla'] ?? $_REQUEST['id_plantilla'] ?? null;
$nombre = $data['nombre'] ?? $_REQUEST['nombre'] ?? 'Usuario Sistema';
$descripcion = $data['descripcion'] ?? $_REQUEST['descripcion'] ?? 'Sin descripción adicional';
$correo = $data['correo'] ?? $_REQUEST['correo'] ?? 'tester_bot@grupoaxo.com'; // Opcional default
$accion = $data['accion'] ?? $data['acción'] ?? $_REQUEST['accion'] ?? $_REQUEST['acción'] ?? '1'; // '2' = cerrar o '1' = abrir
$tipo_solicitud = $data['tipo_solicitud'] ?? $_REQUEST['tipo_solicitud'] ?? 'General'; // Clasificación opcional

// Normalizar entradas de texto crudo del robot hacia IDs fijos de catálogo
$ts_lower = mb_strtolower(trim($tipo_solicitud));
if (strpos($ts_lower, 'restablecimiento') !== false || strpos($ts_lower, 'reset de correo') !== false || $tipo_solicitud == '2') {
    $tipo_solicitud = 2;
} elseif (strpos($ts_lower, 'desbloqueo de') !== false || $tipo_solicitud == '1') {
    $tipo_solicitud = 1;
} elseif (strpos($ts_lower, 'success') !== false || $tipo_solicitud == '3') {
    $tipo_solicitud = 3;
}

// Ya no es forzoso el id_plantilla
// Asumo que la variable $conn (tu conexión PDO) está definida en el archivo bd.php
$api = new ServiceDeskAPI($conn); 
echo json_encode($api->ejecutar($id_plantilla, $nombre, $descripcion, $correo, $accion, $tipo_solicitud), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);


#Restablecimiento de contraseña

#descripcion Tu soliciut ha sido resulta 

#15240  