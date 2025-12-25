<?php
require "../config/helpers.php";
checkLogin();
if(!isAdmin()) { die("No autorizado"); }
require "../config/db.php";

$id = $_GET["id"];
$stmt = $conn->prepare("SELECT * FROM tecnicos WHERE id = ?");
$stmt->execute([$id]);
$t = $stmt->fetch(PDO::FETCH_ASSOC);

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $nombre = $_POST["nombre"];
    $usuario = $_POST["usuario_login"];
    $password = $_POST["password"];
    $correo = $_POST["correo"];
    $hora_entrada = $_POST["hora_entrada"];
    $hora_salida = $_POST["hora_salida"];
    $es_nuevo = isset($_POST["es_nuevo"]) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE tecnicos SET nombre=?, usuario_login=?, id_sistema=?, correo=?, hora_entrada=?, hora_salida=?, es_nuevo=? WHERE id=?");
    $stmt->execute([$nombre, $usuario, $password, $correo, $hora_entrada, $hora_salida, $es_nuevo, $id]);
    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Editar Técnico</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Editar Técnico</h3>

<form method="POST">
    <label>Nombre</label>
    <input type="text" name="nombre" class="form-control" value="<?= $t['nombre'] ?>" required>

    <label class="mt-2">Usuario Login</label>
    <input type="text" name="usuario_login" class="form-control" value="<?= $t['usuario_login'] ?>" required>

    <label class="mt-2">Contraseña</label>
    <input type="text" name="password" class="form-control" value="<?= $t['id_sistema'] ?>" required>

    <label class="mt-2">Correo</label>
    <input type="email" name="correo" class="form-control" value="<?= $t['correo'] ?>" required>

    <label class="mt-2">Hora Entrada</label>
    <input type="time" name="hora_entrada" class="form-control" value="<?= $t['hora_entrada'] ?>" required>

    <label class="mt-2">Hora Salida</label>
    <input type="time" name="hora_salida" class="form-control" value="<?= $t['hora_salida'] ?>" required>

    <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" name="es_nuevo" value="1" <?= $t['es_nuevo'] ? "checked" : "" ?>>
        <label class="form-check-label">Es Nuevo</label>
    </div>

    <button class="btn btn-success mt-3">Guardar Cambios</button>
</form>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
