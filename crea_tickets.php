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
    
    // Función adaptada de app.py para obtener el siguiente técnico disponible
    private function obtenerTecnicoDisponible() {
        // En tu esquema original, 'tecnicos' tiene id, nombre, activo, id_sistema, etc.
        // Aquí simplificaremos para buscar uno 'activo' = 1 (disponible ahora mismo).
        // Podrías expandirlo para que haga el Round Robin estricto si manejas 'control_asignacion' en PHP.
        $stmt = $this->pdo->query("SELECT id_sistema FROM tecnicos WHERE activo = 1 AND modo_asignacion = 1 ORDER BY orden_asignacion ASC LIMIT 1");
        $tecnico = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $tecnico ? $tecnico['id_sistema'] : null;
    }

    public function ejecutar($id_plantilla, $nombre_usuario, $descripcion_usuario, $id_empleado, $accion) {
        try {
            $plantilla_nombre = null;
            $plantilla_descripcion = "";
            $id_grupo = "954"; // Default fallback para grupo si no hay plantilla
            
            // Si el usuario envía la plantilla, buscamos en DB
            if ($id_plantilla) {
                // 2. Consultar la información de la plantilla incluyendo id_grupo
                $stmt = $this->pdo->prepare("SELECT plantilla_incidente, descripcion, id_grupo FROM plantillas_incidentes WHERE id = ?");
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
            $status_id = ($accion === "2") ? "3" : "1"; // 3=Resuelto/Cerrado, 1=Abierto

            $request_data = [
                "subject" => $subject_final,
                "description" => $full_description,
                "requester" => ["id" => $id_empleado], // El solicitante real
                "technician" => ["id" => $id_tecnico_disponible], // Técnico asignado dinámicamente
                "group" => ["id" => $id_grupo], 
                "udf_fields" => [
                    "udf_pick_2114" => ["name" => "A PIE DE CALLE", "id" => "8428"],
                    "udf_pick_27" => ["name" => "TOMMY", "id" => "9925"]
                ],
                "status" => ["id" => $status_id] 
            ];

            // Si hay plantilla encontrada, la incluimos
            if ($plantilla_nombre) {
                $request_data["template"] = ["name" => $plantilla_nombre];
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
                    $status_final = '2'; // 2 = Cerrado
                } else {
                    $status_final = '1'; // 1 = Abierto
                }

                // 7. Registrar en tu tabla de log local
                $log = $this->pdo->prepare("INSERT INTO tickets_automatizados (id_plantilla_origen, request_id_servicedesk, status_final) VALUES (?, ?, ?)");
                // $id_plantilla puede ser null
                $log->execute([$id_plantilla, $request_id, $status_final]);

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

// 8. Receptar Parámetros Dinámicos (GET o POST)
// Ejemplo de uso: crea_tickets.php?nombre=Juan%20Perez&descripcion=No%20puedo%20entrar&id_empleado=74404&accion=1

// Hacemos id_plantilla opcional.
$id_plantilla = $_REQUEST['id_plantilla'] ?? null;
// El resto de parámetros...
$nombre = $_REQUEST['nombre'] ?? 'Usuario Sistema';
$descripcion = $_REQUEST['descripcion'] ?? 'Sin descripción adicional';
$id_empleado = $_REQUEST['id_empleado'] ?? '74404'; // Pide default
$accion = $_REQUEST['accion'] ?? '1'; // '2' = cerrar o '1' = abrir

// Ya no es forzoso el id_plantilla
// Asumo que la variable $conn (tu conexión PDO) está definida en el archivo bd.php
$api = new ServiceDeskAPI($conn); 
echo json_encode($api->ejecutar($id_plantilla, $nombre, $descripcion, $id_empleado, $accion), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
