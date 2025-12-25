<?php
require "../config/helpers.php";
checkLogin();
if(!isAdmin()) { die("No autorizado"); }
require "../config/db.php";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $nombre = $_POST["nombre"];
    $usuario = $_POST["usuario_login"];
    $password = $_POST["password"];
    $correo = $_POST["correo"];
    $hora_entrada = $_POST["hora_entrada"];
    $hora_salida = $_POST["hora_salida"];
    $es_nuevo = isset($_POST["es_nuevo"]) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO tecnicos (nombre, usuario_login, id_sistema, correo, hora_entrada, hora_salida, es_nuevo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $usuario, $password, $correo, $hora_entrada, $hora_salida, $es_nuevo]);
    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Crear Técnico</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Crear Técnico</h3>

<form method="POST">
    <label>Nombre</label>
    <input type="text" name="nombre" class="form-control" required>

    <label class="mt-2">Usuario Login</label>
    <input type="text" name="usuario_login" class="form-control" required>

    <label class="mt-2">Contraseña</label>
    <input type="text" name="password" class="form-control" required>

    <label class="mt-2">Correo</label>
    <input type="email" name="correo" class="form-control" required>

    <label class="mt-2">Hora Entrada</label>
    <input type="time" name="hora_entrada" class="form-control" required>

    <label class="mt-2">Hora Salida</label>
    <input type="time" name="hora_salida" class="form-control" required>

    <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" name="es_nuevo" value="1">
        <label class="form-check-label">Es Nuevo</label>
    </div>

    <button class="btn btn-success mt-3">Guardar</button>
</form>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
