<?php
require "../config/helpers.php";
checkLogin();
if(!isAdmin()) die("No autorizado");

require "../config/db.php";

if($_SERVER["REQUEST_METHOD"]=="POST"){
    $plantilla = $_POST["plantilla_incidente"];
    $categoria = $_POST["categoria"];
    $subcategoria = $_POST["subcategoria"];
    $articulo = $_POST["articulo"];
    $grupo = $_POST["grupo"];
    $origen = $_POST["origen"];
    $id_grupo = $_POST["id_grupo"];

    $stmt = $conn->prepare("INSERT INTO plantillas_incidentes (plantilla_incidente, categoria, subcategoria, articulo, grupo, origen, id_grupo) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$plantilla, $categoria, $subcategoria, $articulo, $grupo, $origen, $id_grupo]);
    header("Location: index.php");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Nueva Plantilla</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Crear Nueva Plantilla de Incidente</h3>

<form method="POST">
    <label>Plantilla</label>
    <input type="text" name="plantilla_incidente" class="form-control" required>

    <label class="mt-2">Categoría</label>
    <input type="text" name="categoria" class="form-control">

    <label class="mt-2">Subcategoría</label>
    <input type="text" name="subcategoria" class="form-control">

    <label class="mt-2">Artículo</label>
    <input type="text" name="articulo" class="form-control">

    <label class="mt-2">Grupo</label>
    <input type="text" name="grupo" class="form-control">

    <label class="mt-2">Origen</label>
    <input type="text" name="origen" class="form-control">

    <label class="mt-2">ID Grupo</label>
    <input type="number" name="id_grupo" class="form-control" required>

    <button class="btn btn-success mt-3">Guardar</button>
    <a href="index.php" class="btn btn-secondary mt-3">Cancelar</a>
</form>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
