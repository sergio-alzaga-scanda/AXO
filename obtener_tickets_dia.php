<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit; 
}
require_once 'db.php';

if (!isset($_GET['fecha'])) {
    echo json_encode(['status' => 'error', 'message' => 'Fecha no proporcionada']);
    exit;
}

$fecha = $_GET['fecha'];

try {
    // Obtener tickets cuyo DATE coincida con la fecha solicitada
    $stmt = $conn->prepare("
        SELECT id_ticket, usuario_tecnico, grupo, templete, fecha_asignacion 
        FROM tickets_asignados 
        WHERE DATE(fecha_asignacion) = :fecha
        ORDER BY fecha_asignacion DESC
    ");
    $stmt->execute([':fecha' => $fecha]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['status' => 'success', 'data' => $tickets]);
} catch(PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error de base de datos']);
}
?>
