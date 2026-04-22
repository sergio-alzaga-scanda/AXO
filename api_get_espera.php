<?php
header("Content-Type: application/json");
require_once __DIR__ . '/Sistema/config/bd.php'; // Usa la conexión estructurada

try {
    // MODO CONSULTA PURA (Pendientes)
    $stmt = $conn->prepare("SELECT * FROM log_tickets_teams WHERE status_proceso = 'en espera' ORDER BY fecha_creacion DESC LIMIT 1");
    $stmt->execute();
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($registro) {
        // Verificar si ya tiene contraseña, si no, generarla
        if (empty($registro['password_temporal'])) {
            $nueva_pass = generarPasswordFacil();
            
            // Actualizar en BD
            $update = $conn->prepare("UPDATE log_tickets_teams SET password_temporal = ? WHERE id = ?");
            $update->execute([$nueva_pass, $registro['id']]);
            
            // Refrescar el registro para la respuesta
            $registro['password_temporal'] = $nueva_pass;
        }

        // Se devuelve la información base de datos cruda y directa sin llaves extras estructuradas
        echo json_encode($registro, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        // En caso de que no haya nada, devolver un JSON vacío para que tampoco mande llaves extra
        echo json_encode(new stdClass());
    }
} catch (Exception $e) {
    echo json_encode([
        "error" => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Genera una contraseña de 12 caracteres, fácil de escribir,
 * cumpliendo con: 1 mayúscula, 1 minúscula y 1 carácter especial ($#@!).
 * Se utiliza una lista de 20 patrones aprobados para aleatoriedad.
 */
function generarPasswordFacil() {
    $patrones = [
        "Axo2026_Net!", "Axo2026_Sys!", "Axo2026_App!", "Axo2026_Sec!", 
        "Axo2026_Web!", "Axo2026_Dat!", "Axo2026_Dev!", "Axo2026_Ops!",
        "Axo2026_Inf!", "Axo2026_Sql!", "Axo2026_Svc!", "Axo2026_Iot!",
        "Axo2026_Rpa!", "Axo2026_Bot!", "Axo2026_Api!", "Axo2026_Cpu!",
        "Axo2026_Ram!", "Axo2026_Dns!", "Axo2026_Log!", "Axo2026_Url!"
    ];
    
    return $patrones[array_rand($patrones)];
}
?>
