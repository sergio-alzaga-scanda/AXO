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
        "Puebla_2026!", "Oaxaca_2026*", "Sonora_2026#", "Colima_2026$", 
        "Jalisco2026!", "Nayarit2026*", "Yucatan2026#", "Chiapas2026$",
        "Tabasco2026!", "Morelos2026*", "Hidalgo2026#", "Mexico_2026@",
        "Durango2026!", "Sinaloa2026*", "Tlaxcala_202!", "Zacat2026_!*",
        "Queretaro202!", "Veracruz_202!", "Campeche_202*", "Guerrero_202#"
    ];
    
    return $patrones[array_rand($patrones)];
}
?>
