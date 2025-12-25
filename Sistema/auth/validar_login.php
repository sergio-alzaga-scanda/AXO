<?php
session_start();
require "../config/db.php";

$usuario = $_POST["usuario"];
$password = $_POST["password"];

$sql = $conn->prepare("SELECT * FROM tecnicos WHERE usuario_login = ? LIMIT 1");
$sql->execute([$usuario]);
$user = $sql->fetch(PDO::FETCH_ASSOC);

if ($user && $user["id_sistema"] === $password) { // texto plano
    $_SESSION["usuario"] = $usuario;
    $_SESSION["id_tecnico"] = $user["id"];
    $_SESSION["rol"] = ($usuario === "admin") ? "admin" : "tecnico";
    header("Location: ../index.php");
} else {
    echo "<script>alert('Credenciales incorrectas'); window.location='login.php';</script>";
}
