<?php
require "../config/helpers.php";
checkLogin();
require "../config/db.php";

// Solo Admin puede ver todos los técnicos
if (isAdmin()) {
    $tecnicos = $conn->query("SELECT * FROM tecnicos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare("SELECT * FROM tecnicos WHERE id = ?");
    $stmt->execute([$_SESSION["id_tecnico"]]);
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Técnicos</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Técnicos</h3>

<?php if (isAdmin()): ?>
    <a href="crear.php" class="btn btn-success mb-3">Nuevo Técnico</a>
<?php endif; ?>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Usuario</th>
            <th>Activo</th>
            <th>Horario</th>
            <th>Nuevo</th>
            <th>Asistencia</th>
            <th>Cambio Horario</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($tecnicos as $t): ?>
        <tr>
            <td><?= $t["id"] ?></td>
            <td><?= $t["nombre"] ?></td>
            <td><?= $t["usuario_login"] ?></td>
            <td><?= $t["activo"] ?></td>
            <td><?= $t["hora_entrada"] . " - " . $t["hora_salida"] ?></td>
            <td><?= $t["es_nuevo"] ? "Sí" : "No" ?></td>
            <td><?= $t["asistencia"] ? "Sí" : "No" ?></td>
            <td><?= $t["cambio_horario"] ? "Sí" : "No" ?></td>
            <td>
                <?php if(isAdmin()): ?>
                    <a href="editar.php?id=<?= $t['id'] ?>" class="btn btn-warning btn-sm">Editar</a>
                    <a onclick="return confirm('¿Eliminar?')" href="eliminar.php?id=<?= $t['id'] ?>" class="btn btn-danger btn-sm">Eliminar</a>
                <?php else: ?>
                    <a href="asistencia.php?id=<?= $t['id'] ?>" class="btn btn-info btn-sm">Marcar Asistencia</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
