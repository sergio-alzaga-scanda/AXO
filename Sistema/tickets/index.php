<?php
require "../config/helpers.php";
checkLogin();
require "../config/db.php";

// Admin ve todos, técnico solo sus tickets
if(isAdmin()){
    $tickets = $conn->query("SELECT * FROM tickets_asignados ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT * FROM tickets_asignados WHERE usuario_tecnico=? ORDER BY id DESC");
    $stmt->execute([$_SESSION["usuario"]]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tickets Asignados</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Tickets Asignados</h3>
<?php if(isAdmin()): ?>
    <a href="crear.php" class="btn btn-success mb-3">Nuevo Ticket</a>
<?php endif; ?>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Ticket</th>
            <th>Técnico</th>
            <th>Grupo</th>
            <th>Template</th>
            <th>Fecha Asignación</th>
            <?php if(isAdmin()): ?><th>Acciones</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach($tickets as $t): ?>
        <tr>
            <td><?= $t["id"] ?></td>
            <td><?= $t["id_ticket"] ?></td>
            <td><?= $t["usuario_tecnico"] ?></td>
            <td><?= $t["grupo"] ?></td>
            <td><?= $t["templete"] ?></td>
            <td><?= $t["fecha_asignacion"] ?></td>
            <?php if(isAdmin()): ?>
            <td>
                <a href="editar.php?id=<?= $t["id"] ?>" class="btn btn-warning btn-sm">Editar</a>
                <a onclick="return confirm('¿Eliminar?')" href="eliminar.php?id=<?= $t["id"] ?>" class="btn btn-danger btn-sm">Eliminar</a>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
