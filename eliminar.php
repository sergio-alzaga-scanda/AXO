<?php
session_start();
require_once 'db.php';
require_once 'funciones.php'; // Incluir funciones

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // 1. Primero borramos los horarios asociados a este técnico
        $stmtHorarios = $conn->prepare("DELETE FROM horarios_tecnicos WHERE id_tecnico = ?");
        $stmtHorarios->execute([$id]);

        // 2. Ahora sí, borramos al técnico
        $stmtTecnico = $conn->prepare("DELETE FROM tecnicos WHERE id = ?");
        $stmtTecnico->execute([$id]);

    } catch (PDOException $e) {
        // Si hay error, lo mostramos en pantalla para saber qué pasa
        die("Error al eliminar: " . $e->getMessage());
    }
    registrarAccion($conn, $_SESSION['user_id'], $_SESSION['nombre'], 'ELIMINAR_TECNICO', "Eliminó al técnico ID: $id");
}


header("Location: dashboard.php");
exit;
?>