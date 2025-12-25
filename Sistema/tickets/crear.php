<?php
require "../config/helpers.php";
checkLogin();
if(!isAdmin()) die("No autorizado");
require "../config/db.php";

if($_SERVER["REQUEST_METHOD"]=="POST"){
    $id_ticket = $_POST["id_ticket"];
    $usuario_tecnico = $_POST["usuario_tecnico"];
    $grupo = $_POST["grupo"];
    $templete = $_POST["templete"];
    $fecha = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("INSERT INTO tickets_asignados (id_ticket, usuario_tecnico, grupo, templete, fecha_asignacion) VALUES (?,?,?,?,?)");
    $stmt->execute([$id_ticket, $usuario_tecnico, $grupo, $templete, $fecha]);
    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Nuevo Ticket</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Crear Ticket</h3>

<form method="POST">
    <label>ID Ticket</label>
    <input type="text" name="id_ticket" class="form-control" required>

    <label class="mt-2">Usuario Técnico</label>
    <input type="text" name="usuario_tecnico" class="form-control" required>

    <label class="mt-2">Grupo</label>
    <input type="text" name="grupo" class="form-control" required>

    <label class="mt-2">Template</label>
    <input type="text" name="templete" class="form-control" required>

    <button class="btn btn-success mt-3">Guardar</button>
</form>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
