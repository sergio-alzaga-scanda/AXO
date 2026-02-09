<?php
// api_estado.php
header('Content-Type: application/json');
require_once 'db.php';

try {
    $stmt = $conn->query("SELECT activo FROM configuracion_servicio WHERE id = 1");
    $estado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Si no existe, asumimos inactivo
    $activo = $estado ? (bool)$estado['activo'] : false;

    echo json_encode([
        'servicio' => 'axo_bot',
        'activo' => $activo,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>