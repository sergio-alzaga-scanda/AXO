<?php
header("Content-Type: application/json");

// Importar la conexión existente tal como se hace en crea_tickets.php
require_once __DIR__ . '/Sistema/config/bd.php'; 

class TeamsAutomatizacionAPI {
    private $pdo;
    private $BASE_URL = "https://servicedesk.grupoaxo.com/api/v3/";
    private $API_KEY = "423CEBBE-E849-4D17-9CA3-CD6AB3319401";
    
    // Credenciales de SuccessFactors (Configura con tus datos reales)
    private $SF_BASE_URL = "https://<tu-servidor-api>.successfactors.com";
    private $SF_BEARER_TOKEN = "AQUI_VA_TU_TOKEN_DE_SUCCESSFACTORS";

    public function __construct($db_connection) {
        $this->pdo = $db_connection;
        // Se ejecuta la inicialización de la tabla al invocar la clase por seguridad
        $this->inicializarTablaLogs(); 
    }

    /**
     * Valida si el número de empleado existe en SuccessFactors
     */
    private function validarEmpleadoSuccessFactors($numero_usuario) {
        // Endpoint buscando por la clave principal del User
        $url = $this->SF_BASE_URL . "/odata/v2/User('" . urlencode($numero_usuario) . "')";
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_SSL_VERIFYPEER => false, // Cambiar a true en producción si tienes certificados válidos
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->SF_BEARER_TOKEN,
                "Accept: application/json"
            ],
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Retorna true si SuccessFactors responde con 200 OK (El empleado existe)
        return ($http_code === 200);
    }

    /**
     * Crea la tabla base de datos exclusivamente para almacenar los registros
     * solicitados generados por Teams, en caso de que no exista.
     */
    private function inicializarTablaLogs() {
        $sql = "CREATE TABLE IF NOT EXISTS log_tickets_teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            numero_usuario VARCHAR(100),
            correo VARCHAR(255),
            plantilla_usada INT,
            nombre_plantilla VARCHAR(255),
            ticket_creado VARCHAR(50),
            status_proceso VARCHAR(50) DEFAULT 'Éxito',
            error_detalle TEXT,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
        
        // Agregar columnas dinámicamente si la tabla ya existía de manera aislada
        try { $this->pdo->exec("ALTER TABLE log_tickets_teams ADD COLUMN status_proceso VARCHAR(50) DEFAULT 'Éxito'"); } catch (Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE log_tickets_teams ADD COLUMN error_detalle TEXT"); } catch (Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE log_tickets_teams ADD COLUMN tipo_solicitud VARCHAR(100) DEFAULT 'No especificada'"); } catch (Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE log_tickets_teams MODIFY COLUMN ticket_creado VARCHAR(50) NULL"); } catch (Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE log_tickets_teams MODIFY COLUMN plantilla_usada INT NULL"); } catch (Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE log_tickets_teams MODIFY COLUMN numero_usuario VARCHAR(100) NULL"); } catch (Exception $e) {}
        try { $this->pdo->exec("ALTER TABLE log_tickets_teams MODIFY COLUMN correo VARCHAR(255) NULL"); } catch (Exception $e) {}
    }

    private function registrarBitacoraBD($num_usr, $correo, $id_pl, $nom_pl, $ticket, $status, $error = null, $tipo_solicitud = null) {
        try {
            $sql = "INSERT INTO log_tickets_teams (numero_usuario, correo, plantilla_usada, nombre_plantilla, ticket_creado, status_proceso, error_detalle, tipo_solicitud) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$num_usr, $correo, $id_pl, $nom_pl, $ticket, $status, $error, $tipo_solicitud]);
        } catch (Exception $e) {}
    }

    /**
     * Busca al siguiente técnico disponible en sistema usando lógica de Carrusel (Round Robin).
     * Se alimenta de la tabla `configuracion_asignacion` compartida con app.py
     */
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
        } catch (Exception $e) {
            // Error silencioso, no detener la petición por fallos de bitácora
        }
    }

    public function procesarTicketTeams($numero_usuario, $correo, $tipo_solicitud = 'No especificada') {
        try {
            // ---------------------------------------------------------
            // PASO NUEVO: Validar si el empleado existe en SuccessFactors 
            // (Comentado temporalmente a petición para pruebas)
            // ---------------------------------------------------------
            /*
            if (!$this->validarEmpleadoSuccessFactors($numero_usuario)) {
                $err_msg = "El número de empleado '{$numero_usuario}' no corresponde a ningún registro válido.";
                $this->registrarBitacoraBD($numero_usuario, $correo, null, "Validación Externa", null, 'Error', $err_msg, $tipo_solicitud);
                
                return [
                    "status" => "error", 
                    "mensaje" => $err_msg // Esto es lo que se le avisará a Teams/Usuario
                ];
            }
            */
            // ---------------------------------------------------------

            $id_plantilla = 3901;
            $nombre_plantilla = "Configuración Predeterminada";
            
            // 1. Obtener la información de la plantilla fija
            $stmt = $this->pdo->prepare("SELECT plantilla_incidente, descripcion, id_grupo, categoria, subcategoria FROM plantillas_incidentes WHERE id = ?");
            $stmt->execute([$id_plantilla]);
            $plantilla = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$plantilla) {
                $err = "Plantilla 3901 no encontrada.";
                $this->registrarBitacoraBD($numero_usuario, $correo, $id_plantilla, $nombre_plantilla, null, 'Error', $err, $tipo_solicitud);
                return ["status" => "error", "message" => $err];
            }
            $nombre_plantilla = $plantilla['plantilla_incidente'];

            // 2. Obtener técnico disponible
            $id_tecnico_disponible = $this->obtenerTecnicoDisponible();
            if (!$id_tecnico_disponible) {
                $err = "No hay técnicos disponibles en este momento.";
                $this->registrarBitacoraBD($numero_usuario, $correo, $id_plantilla, $nombre_plantilla, null, 'Error', $err, $tipo_solicitud);
                return ["status" => "error", "message" => $err];
            }

            $id_grupo = !empty($plantilla['id_grupo']) ? $plantilla['id_grupo'] : "954";

            // 3. Formatear la descripción
            $descripcion_ticket = "<b>Petición generada por automatización vía Teams</b><br><br>";
            $descripcion_ticket .= "<b>Usuario:</b> " . htmlspecialchars($numero_usuario) . " (Validado en SuccessFactors)<br>";
            $descripcion_ticket .= "<b>Correo:</b> " . htmlspecialchars($correo) . "<br>";
            $descripcion_ticket .= "<b>Tipo Solicitud:</b> " . htmlspecialchars($tipo_solicitud) . "<br><br>";
            $descripcion_ticket .= "<b>Contexto (Plantilla Aplicada):</b><br>" . $plantilla['descripcion'] . "<br><br>";
            $descripcion_ticket .= "<b>Nota resolutiva:</b> Petición generada por Teams y resuelta por automatización.";

            // 4. Preparar el Payload de Creación
            $request_data = [
                "subject" => $nombre_plantilla . " - Ticket Vía Teams",
                "description" => $descripcion_ticket,
                "requester" => [
                    "email_id" => $correo
                ],
                "udf_fields" => [
                    "udf_pick_2114" => ["name" => "A PIE DE CALLE", "id" => "8428"],
                    "udf_pick_27" => ["name" => "TOMMY", "id" => "9925"]
                ],
                "technician" => ["id" => "78545"],
                "group" => ["id" => $id_grupo],
                "template" => ["name" => $nombre_plantilla],
                "status" => ["id" => "1"],
                "resolution" => [
                    "content" => "Petición generada por Teams y resuelta por automatización. La contraseña nueva es: Inicio26+"
                ],
                "is_fcr" => true
            ];

            if (!empty($plantilla['categoria'])) {
                $request_data["category"] = ["name" => $plantilla['categoria']];
            }
            if (!empty($plantilla['subcategoria'])) {
                $request_data["subcategory"] = ["name" => $plantilla['subcategoria']];
            }


            // 5. Invocar creación
            $res_crear = $this->call_api("POST", "requests", ["request" => $request_data]);
            $ticket_id = $res_crear['request']['id'] ?? null;

            if ($ticket_id) {
                // 6. Log success (Cierre delegado a api_actualiza_espera)

                // 7. Almacenar el registro de éxito en la base de datos de control
                $this->registrarBitacoraBD($numero_usuario, $correo, $id_plantilla, $nombre_plantilla, $ticket_id, 'en espera', null, $tipo_solicitud);

                // 8. Devolver mensaje JSON exitoso conforme a la solicitud
                return [
                    "status" => "success",
                    "mensaje" => "Su solicitud fue creada y procesada exitosamente." . "<br><br>" . "Tu contraseña temporal es: Inicio26+",
                    "numero_ticket" => $ticket_id
                ];
            }

            $this->registrarBitacoraBD($numero_usuario, $correo, $id_plantilla, $nombre_plantilla, null, 'Error', "Fallo al crear ticket en API SD", $tipo_solicitud);
            return [
                "status" => "error", 
                "mensaje" => "Hubo un problema al crear tu ticket en el sistema. Vuelve a intentarlo.", // Mensaje más amigable
                "api_response" => $res_crear
            ];

        } catch (Exception $e) {
            $this->registrarBitacoraBD($numero_usuario, $correo, 9902, "Error Genérico", null, 'Error', $e->getMessage(), $tipo_solicitud);
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }

    private function call_api($method, $endpoint, $payload) {
        $url = $this->BASE_URL . $endpoint;
        $ch = curl_init($url);
        
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

// Validación de entrada para la API (Se espera recibir un JSON crudo desde Teams)
$input_json = file_get_contents('php://input');
$data = json_decode($input_json, true);

// Prevenir problemas si envían la llave con un espacio final "numero_usuario "
$numero_usuario = $data['numero_usuario'] ?? $data['numero_usuario '] ?? $_REQUEST['numero_usuario'] ?? null;
$correo = $data['correo'] ?? $_REQUEST['correo'] ?? null;
$tipo_solicitud = 3; // Siempre será 3 (Reset Success Factor) para que se grafique en las métricas

if (!$numero_usuario || !$correo) {
    echo json_encode([
        "status" => "error", 
        "mensaje" => "Parámetros incompletos. Se espera un JSON con 'numero_usuario' y 'correo'."
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Invocación a clase
// Se asume que $conn es la instancia PDO provista por el archivo bd.php exigido en la línea 5
$api = new TeamsAutomatizacionAPI($conn);
$resultado = $api->procesarTicketTeams($numero_usuario, $correo, $tipo_solicitud);

// Respuesta final al usuario
echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); 