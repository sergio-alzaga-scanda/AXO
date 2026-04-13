<?php
header("Content-Type: application/json");
require_once __DIR__ . '/Sistema/config/bd.php';

$json_input = file_get_contents('php://input');
$data = json_decode($json_input, true) ?: [];

// Permite leer el ticket enviado por GET (ej. ?ticket=123) o en el JSON ("ticket": "123")
$numero_ticket = $data['ticket'] ?? $_REQUEST['ticket'] ?? null;

if (!$numero_ticket) {
    echo json_encode(["exitoso" => false, "mensaje" => "Número de ticket no proporcionado"]);
    exit;
}

// Retrasar la respuesta por 20 segundos
sleep(20);

try {
    // Buscar en la bitácora el último registro relacionado a este ticket
    $stmt = $conn->prepare("SELECT status_proceso FROM log_tickets_teams WHERE ticket_creado = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$numero_ticket]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($log) {
        // Asume como true cualquier Estatus que signifique finalizado positivamente
        $estado = strtolower(trim($log['status_proceso']));
        $es_exito = in_array($estado, ['correcto', 'éxito', 'exito']);
        
        $estado_general = 'error';
        if ($es_exito) {
            $estado_general = 'exitoso';
        } else if ($estado === 'en espera') {
            $estado_general = 'en_espera';
        }

        echo json_encode([
            "exitoso" => $es_exito,
            "estado_peticion" => $estado_general,
            "estado_bd" => $log['status_proceso']
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(["exitoso" => false, "mensaje" => "El ticket no se encontró en la base de datos local"], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(["exitoso" => false, "mensaje" => "Error al consultar la base de datos"], JSON_UNESCAPED_UNICODE);
}
?>
