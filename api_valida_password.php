<?php
header("Content-Type: application/json");

$input_json = file_get_contents('php://input');
$data = json_decode($input_json, true) ?: [];

$password = $data['password'] ?? $_REQUEST['password'] ?? null;

if (!$password) {
    echo json_encode([
        "status" => "error", 
        "mensaje" => "Se requiere el parámetro 'password'."
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$esValido = true;

// Validar longitud mínima de 12 caracteres
if (strlen($password) < 12) {
    $esValido = false;
}

// Validar al menos una letra mayúscula
if (!preg_match('/[A-Z]/', $password)) {
    $esValido = false;
}

// Validar al menos una letra minúscula
if (!preg_match('/[a-z]/', $password)) {
    $esValido = false;
}

// Validar al menos un carácter especial
// Cualquier caracter que no sea letra o número
if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
    $esValido = false;
}

echo json_encode([
    "valido" => $esValido
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
