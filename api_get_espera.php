<?php
header("Content-Type: application/json");
require_once __DIR__ . '/Sistema/config/bd.php'; // Usa la conexión estructurada

try {
    // MODO CONSULTA PURA (Pendientes)
    $stmt = $conn->prepare("SELECT * FROM log_tickets_teams WHERE status_proceso = 'en espera' ORDER BY fecha_creacion DESC LIMIT 1");
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registro) {
        // Se devuelve la información base de datos cruda y directa sin llaves extras estructuradas
        echo json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        // En caso de que no haya nada, devolver un JSON vacío para que tampoco mande llaves extra
        echo json_encode(new stdClass());
    }
} catch (Exception $e) {
    echo json_encode([
        "error" => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
