<?php
// cambiar_estado.php
session_start();
require_once 'db.php';
require_once 'funciones.php'; // Incluir funciones

if (isset($_POST['activo']) && isset($_SESSION['user_id'])) {
    $nuevo_estado = $_POST['activo'];
    $stmt = $conn->prepare("UPDATE configuracion_servicio SET activo = ? WHERE id = 1");
    $stmt->execute([$nuevo_estado]);
    
    // NUEVO: Registrar Log
    $estado_texto = ($nuevo_estado == 1) ? 'ENCENDIDO' : 'APAGADO';
    registrarAccion($conn, $_SESSION['user_id'], $_SESSION['nombre'], 'TOGGLE_SERVICIO', "Cambió el estado del servicio a: $estado_texto");
    
    echo "ok";
}
?>