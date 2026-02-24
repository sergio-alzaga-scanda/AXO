<?php
header("Content-Type: application/json");
require_once __DIR__ . '/Sistema/config/bd.php'; 

class ServiceDeskCierre {
    private $pdo;
    private $API_KEY = "423CEBBE-E849-4D17-9CA3-CD6AB3319401";
    // ID obtenido de tu ticket de ejemplo: "74406"
    private $TECHNICIAN_ID = "74406"; 

    public function __construct($db_connection) {
        $this->pdo = $db_connection;
    }

    public function ejecutarCierre($request_id) {
        // URL dinámica basada en tu ejemplo de Python
        $url = "https://servicedesk.grupoaxo.com/api/v3/requests/{$request_id}/close";

        // Estructura de datos validada con tu ticket cerrado
        $payload = [
    "request" => [
         "technician" => ["id" =>  $this->TECHNICIAN_ID ], // Valor directo "74406"
        // "resolution" => [
        //     "content" => "Ticket procesado y cerrado automáticamente por el sistema de autentificación."
        // ],
        // "udf_fields" => [
        //     // Se envían como strings directos para evitar el error "Extra key"
        //     "udf_pick_2114" => "A PIE DE CALLE",
        //     "udf_pick_27" => "SERVICIOS AXO"
        // ],
        "closure_info" => [
            "requester_ack_resolution" => true,
            "closure_comments" => "Cierre automático exitoso.",
            "closure_code" => null
        ]
    ]
];

        // Codificación exacta como el script de Python (data={'input_data': input_data})
        $post_fields = http_build_query([
            'input_data' => json_encode($payload)
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => $post_fields,
            CURLOPT_SSL_VERIFYPEER => false, // Equivalente a verify=False en Python
            CURLOPT_HTTPHEADER => [
                "authtoken: " . $this->API_KEY,
                "PORTALID: 1", // Header de tu ejemplo de Python
                "Accept: application/v3.0+json" //
            ],
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ["status" => "error_curl", "message" => $error];
        }

        $res_json = json_decode($response, true);

        // Si el éxito es 2000, actualizamos tu base de datos local
        if (isset($res_json['response_status']) && $res_json['response_status']['status_code'] == 2000) {
            $stmt = $this->pdo->prepare("UPDATE tickets_automatizados SET status_final = 'Cerrada' WHERE request_id_servicedesk = ?");
            $stmt->execute([$request_id]);
        }

        return $res_json;
    }
}

// Punto de entrada: cerrar_ticket.php?id_ticket=XXXXX
if (isset($_GET['id_ticket'])) {
    $api = new ServiceDeskCierre($conn);
    echo json_encode($api->ejecutarCierre($_GET['id_ticket']), JSON_PRETTY_PRINT);
} else {
    echo json_encode(["status" => "error", "message" => "Proporcione id_ticket"]);
}