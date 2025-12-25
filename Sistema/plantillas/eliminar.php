<?php
require "../config/helpers.php";
checkLogin();
if(!isAdmin()) die("No autorizado");

require "../config/db.php";

$id = $_GET["id"] ?? 0;

// Verificar existencia
$stmt = $conn->prepare("SELECT * FROM plantillas_incidentes WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$p) die("Plantilla no encontrada");

// Eliminar
$conn->prepare("DELETE FROM plantillas_incidentes WHERE id=?")->execute([$id]);
header("Location: index.php");
