<?php
require_once 'db.php';

if (isset($_GET['id'])) {
    $stmt = $conn->prepare("DELETE FROM plantillas_incidentes WHERE id = ?");
    $stmt->execute([$_GET['id']]);
}
header("Location: plantillas.php");
?>