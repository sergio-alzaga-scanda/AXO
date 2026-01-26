<?php
require_once 'db.php';

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
}

header("Location: dashboard.php");
exit;
?>