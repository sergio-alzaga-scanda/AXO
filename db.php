<?php
// db.php
$host = "localhost";
$port = "3307";
$user = "root";
$pass = "";
$db   = "axo";

try {
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Establecer zona horaria para cálculos correctos
    date_default_timezone_set('America/Mexico_City'); 
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>