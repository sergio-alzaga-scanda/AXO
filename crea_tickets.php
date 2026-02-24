<?php
header("Content-Type: application/json");

// 1. Importar tu conexión existente
require_once __DIR__ . '/Sistema/config/bd.php'; 

class ServiceDeskAPI {
    private $pdo;
    private $BASE_URL = "https://servicedesk.grupoaxo.com/api/v3/";
    private $API_KEY = "423CEBBE-E849-4D17-9CA3-CD6AB3319401";
    private $REQUESTER_ID = "74404"; // Tu ID de solicitante

    public function __construct($db_connection) {
        $this->pdo = $db_connection;
    }

    public function ejecutar($id_plantilla) {
        try {
            // 2. Consultar la información mínima en tu base de datos
            $stmt = $this->pdo->prepare("SELECT plantilla_incidente, descripcion FROM plantillas_incidentes WHERE id = ?");
            $stmt->execute([$id_plantilla]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                return ["status" => "error", "message" => "ID de plantilla no encontrado en DB local"];
            }

            // 3. Preparar el JSON simplificado (SOLUCIÓN APLICADA)
            $payload_crear = [
                "request" => [
                    "subject" => $data['plantilla_incidente'],
                    "description" => "Ticket generado y cerrado automáticamente.<br><br>" . $data['descripcion'],
                    "requester" => ["id" => $this->REQUESTER_ID],
                    "technician" => ["id" => $this->REQUESTER_ID],
                    "template" => ["name" => $data['plantilla_incidente']],
                    "resolution" => [
                            "content" => "Ticket creado y cerrado automáticamente."
                        ],
                   
                    "group" => ["id" => "11706"], 
                    
                    // Descomenta la siguiente línea SOLO si estás 100% seguro de que el grupo 107795 pertenece al sitio 20. 
                    // Si no, es mejor omitirlo para evitar el error 4001.
                    // "site" => ["id" => "20"], 

                    "udf_fields" => [
                        "udf_pick_2114" => ["name" => "A PIE DE CALLE", "id" => "8428"],
                        "udf_pick_27" => ["name" => "TOMMY", "id" => "9925"]
                    ],
                    
                    // CRÍTICO: Al crear, el ticket debe nacer Abierto (1). 
                    // Si lo mandas como Cerrado (3) aquí, la API marcará error.
                    "status" => ["id" => "3"] 
                ]
            ];

            // 4. Ejecutar Creación (POST)
            $res_crear = $this->call("POST", "requests", $payload_crear);
            $request_id = $res_crear['request']['id'] ?? null;

            if ($request_id) {
                // 5. Cerrar el Ticket inmediatamente (PUT /close)
                $payload_cierre = [
                    "request" => [
                        "closure_info" => [
                            "requester_ack_resolution" => true,
                            "closure_comments" => "Cierre automático: Autentificación exitosa.",
                            "closure_code" => ["name" => "Resolución Automática"]
                        ]
                    ]
                ];

                $this->call("PUT", "requests/{$request_id}/close", $payload_cierre);

                // 6. Registrar en tu tabla de log local
                $log = $this->pdo->prepare("INSERT INTO tickets_automatizados (id_plantilla_origen, request_id_servicedesk, status_final) VALUES (?, ?, ?)");
                $log->execute([$id_plantilla, $request_id, 'Cerrado Automáticamente']);

                return [
                    "status" => "success",
                    "servicedesk_id" => $request_id,
                    "respuesta_crear" => $res_crear,
                    "message" => "Ticket creado con plantilla y cerrado con éxito"
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

// Ejecución por URL: crear_ticket_axo.php?id_plantilla=XXXX
if (isset($_GET['id_plantilla'])) {
    // Asumo que la variable $conn (tu conexión PDO) está definida en el archivo bd.php
    $api = new ServiceDeskAPI($conn); 
    echo json_encode($api->ejecutar($_GET['id_plantilla']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "Se requiere id_plantilla"]);
}