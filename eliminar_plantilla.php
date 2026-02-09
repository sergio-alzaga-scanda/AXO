<?php
session_start();
require_once 'db.php';
require_once 'funciones.php'; // Incluir funciones

if (isset($_GET['id'])) {
    $stmt = $conn->prepare("DELETE FROM plantillas_incidentes WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $id = $_GET['id'];
    registrarAccion($conn, $_SESSION['user_id'], $_SESSION['nombre'], 'ELIMINAR_PLANTILLA', "Eliminó la plantilla ID: $id ");

}
header("Location: plantillas.php");
?>