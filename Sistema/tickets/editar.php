<?php
require "../config/helpers.php";
checkLogin();
if(!isAdmin()) die("No autorizado");

require "../config/db.php";

$id = $_GET["id"] ?? 0;

// Obtener ticket
$stmt = $conn->prepare("SELECT * FROM tickets_asignados WHERE id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Ticket no encontrado");
}

// Actualizar ticket
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_ticket = $_POST["id_ticket"];
    $usuario_tecnico = $_POST["usuario_tecnico"];
    $grupo = $_POST["grupo"];
    $templete = $_POST["templete"];

    $stmt = $conn->prepare("UPDATE tickets_asignados SET id_ticket=?, usuario_tecnico=?, grupo=?, templete=? WHERE id=?");
    $stmt->execute([$id_ticket, $usuario_tecnico, $grupo, $templete, $id]);

    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Editar Ticket</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Editar Ticket</h3>

<form method="POST">
    <label>ID Ticket</label>
    <input type="text" name="id_ticket" class="form-control" value="<?= htmlspecialchars($ticket['id_ticket']) ?>" required>

    <label class="mt-2">Usuario Técnico</label>
    <input type="text" name="usuario_tecnico" class="form-control" value="<?= htmlspecialchars($ticket['usuario_tecnico']) ?>" required>

    <label class="mt-2">Grupo</label>
    <input type="text" name="grupo" class="form-control" value="<?= htmlspecialchars($ticket['grupo']) ?>" required>

    <label class="mt-2">Template</label>
    <input type="text" name="templete" class="form-control" value="<?= htmlspecialchars($ticket['templete']) ?>" required>

    <button class="btn btn-success mt-3">Guardar Cambios</button>
    <a href="index.php" class="btn btn-secondary mt-3">Cancelar</a>
</form>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
