<?php
// Permitir solicitudes desde cualquier origen (CORS) si es necesario
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// ==========================================
// CONFIGURACIÓN DE CREDENCIALES DE LA API
// ==========================================
$USUARIO_VALIDO = 'rpa_user';
$PASSWORD_VALIDO = 'axo_rpa_2026';

// Obtener credenciales (Soporta Basic Auth HTTP o Parámetros GET/POST)
$user = $_SERVER['PHP_AUTH_USER'] ?? $_REQUEST['user'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? $_REQUEST['pass'] ?? '';

// Validar credenciales
if ($user !== $USUARIO_VALIDO || $pass !== $PASSWORD_VALIDO) {
    // Si fallan, solicitar Basic Auth a nivel de navegador/cliente http
    header('WWW-Authenticate: Basic realm="API Reservada"');
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'No autorizado. Credenciales inválidas.'
    ]);
    exit;
}

// Conexión a la base de datos
require_once 'db.php';

try {
    // Consultar el registro más viejo (ascendente por fecha de inserción) donde procesado = 0
    $stmt = $conn->prepare("SELECT * FROM sd_password_resets WHERE procesado = 0 ORDER BY inserted_at ASC LIMIT 1");
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registro) {
        http_response_code(200);
        // Devolver los datos del registro directamente, de forma sencilla
        echo json_encode($registro);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'No hay registros pendientes']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
