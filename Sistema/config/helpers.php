<?php
session_start();

// Verifica si hay usuario logueado
function checkLogin() {
    if (!isset($_SESSION["usuario"])) {
        header("Location: ../auth/login.php");
        exit;
    }
}

// Verifica rol
function isAdmin() {
    return isset($_SESSION["rol"]) && $_SESSION["rol"] === "admin";
}

// Actualiza estado activo según horario
function actualizarEstadoHorario($conn, $id_tecnico) {
    $sql = $conn->prepare("SELECT hora_entrada, hora_salida, es_nuevo FROM tecnicos WHERE id = ?");
    $sql->execute([$id_tecnico]);
    $t = $sql->fetch(PDO::FETCH_ASSOC);

    $hora = date("H:i:s");

    if ($hora >= $t["hora_entrada"] && $hora <= $t["hora_salida"]) {
        $activo = $t["es_nuevo"] ? 2 : 1;
    } else {
        $activo = 0;
    }

    $conn->prepare("UPDATE tecnicos SET activo = ? WHERE id = ?")->execute([$activo, $id_tecnico]);
}
