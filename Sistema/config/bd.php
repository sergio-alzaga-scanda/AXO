<?php
// Configuración de conexión a la base de datos
$host = "localhost";
$port = 3307;
$user = "root";
$pass = "";
$dbname = "axo";

try {
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
