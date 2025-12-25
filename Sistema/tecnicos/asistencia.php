<?php
require "../config/helpers.php";
checkLogin();
require "../config/db.php";

$id = $_GET["id"] ?? $_SESSION["id_tecnico"];

// Obtener técnico
$stmt = $conn->prepare("SELECT * FROM tecnicos WHERE id = ?");
$stmt->execute([$id]);
$tec = $stmt->fetch(PDO::FETCH_ASSOC);

// Registrar asistencia
if(isset($_POST["accion"])){
    $accion = $_POST["accion"];
    $fecha = date("Y-m-d H:i:s");

    if($accion == "entrada"){
        $conn->prepare("UPDATE tecnicos SET asistencia=1, activo=1 WHERE id=?")->execute([$id]);
        $mensaje = "Entrada registrada correctamente.";
    } elseif($accion == "salida"){
        $conn->prepare("UPDATE tecnicos SET activo=0 WHERE id=?")->execute([$id]);
        $mensaje = "Salida registrada correctamente.";
    } elseif($accion == "falta"){
        $conn->prepare("UPDATE tecnicos SET asistencia=0, activo=0 WHERE id=?")->execute([$id]);
        $mensaje = "Falta registrada.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Asistencia - <?= $tec["nombre"] ?></title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body class="p-4">

<h3>Asistencia de <?= $tec["nombre"] ?></h3>

<?php if(isset($mensaje)) echo "<div class='alert alert-success'>$mensaje</div>"; ?>

<form method="POST">
    <button type="submit" name="accion" value="entrada" class="btn btn-success">Registrar Entrada</button>
    <button type="submit" name="accion" value="salida" class="btn btn-warning">Registrar Salida</button>
    <button type="submit" name="accion" value="falta" class="btn btn-danger">Registrar Falta</button>
</form>

<p class="mt-3">Estado actual: <strong><?= $tec["activo"] == 1 ? "Dentro de horario" : ($tec["activo"]==2?"Dentro pero nuevo":"Fuera de horario") ?></strong></p>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
