<?php
require "../config/helpers.php";
checkLogin();
if(!isAdmin()) die("No autorizado");

require "../config/db.php";

// Obtener todas las plantillas
$plantillas = $conn->query("SELECT * FROM plantillas_incidentes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Plantillas de Incidentes</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Plantillas de Incidentes</h3>
<a href="crear.php" class="btn btn-success mb-3">Nueva Plantilla</a>

<table class="table table-bordered table-striped">
    <thead>
        <tr>
            <th>ID</th>
            <th>Plantilla</th>
            <th>Categoria</th>
            <th>Subcategoria</th>
            <th>Articulo</th>
            <th>Grupo</th>
            <th>Origen</th>
            <th>ID Grupo</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($plantillas as $p): ?>
        <tr>
            <td><?= $p["id"] ?></td>
            <td><?= htmlspecialchars($p["plantilla_incidente"]) ?></td>
            <td><?= htmlspecialchars($p["categoria"]) ?></td>
            <td><?= htmlspecialchars($p["subcategoria"]) ?></td>
            <td><?= htmlspecialchars($p["articulo"]) ?></td>
            <td><?= htmlspecialchars($p["grupo"]) ?></td>
            <td><?= htmlspecialchars($p["origen"]) ?></td>
            <td><?= $p["id_grupo"] ?></td>
            <td>
                <a href="editar.php?id=<?= $p["id"] ?>" class="btn btn-warning btn-sm">Editar</a>
                <a onclick="return confirm('¿Eliminar?')" href="eliminar.php?id=<?= $p["id"] ?>" class="btn btn-danger btn-sm">Eliminar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
