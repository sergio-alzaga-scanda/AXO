<?php
// cambiar_estado.php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }
require_once 'db.php';

if (isset($_POST['activo'])) {
    $nuevo_estado = $_POST['activo']; // 1 o 0
    $stmt = $conn->prepare("UPDATE configuracion_servicio SET activo = ? WHERE id = 1");
    $stmt->execute([$nuevo_estado]);
    echo "ok";
}
?>