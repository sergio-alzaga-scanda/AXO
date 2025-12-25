<?php
require "../config/helpers.php";
checkLogin();
if(!isAdmin()) die("No autorizado");

require "../config/db.php";

$id = $_GET["id"] ?? 0;

// Verificar existencia
$stmt = $conn->prepare("SELECT * FROM tickets_asignados WHERE id = ?");
$stmt->execute([$id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die("Ticket no encontrado");
}

// Eliminar ticket
$conn->prepare("DELETE FROM tickets_asignados WHERE id=?")->execute([$id]);
header("Location: index.php");
