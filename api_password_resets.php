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

// Leer posible entrada JSON o parámetros (GET/POST)
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

$id_request = $_REQUEST['id_request'] ?? ($input['id_request'] ?? null);

if ($id_request) {
    // Endpoint para actualizar
    $procesado_en = $_REQUEST['procesado_en'] ?? ($input['procesado_en'] ?? date('Y-m-d H:i:s'));
    $rpa_result = $_REQUEST['rpa_result'] ?? ($input['rpa_result'] ?? null);
    $rpa_error = $_REQUEST['rpa_error'] ?? ($input['rpa_error'] ?? null);
    
    try {
        $updateQuery = "UPDATE sd_password_resets SET procesado = 1, procesado_en = :procesado_en";
        $params = [
            ':procesado_en' => $procesado_en,
            ':id_request' => $id_request
        ];
        
        if ($rpa_result !== null) {
            $updateQuery .= ", rpa_result = :rpa_result";
            $params[':rpa_result'] = $rpa_result;
        }
        if ($rpa_error !== null) {
            $updateQuery .= ", rpa_error = :rpa_error";
            $params[':rpa_error'] = $rpa_error;
        }
        
        if (is_numeric($id_request)) {
            $updateQuery .= " WHERE id = :id_request OR request_id = :id_request";
        } else {
            $updateQuery .= " WHERE request_id = :id_request";
        }
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            http_response_code(200);
            echo json_encode(['status' => 'success', 'message' => 'Registro actualizado correctamente']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Registro no encontrado o no modificado']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

} else {
    // Endpoint para consultar
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
}
?>
