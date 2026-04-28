<?php
// Endpoint Exclusivo para Recibir Resultado RPA e impactar BD o generar Escalación
header("Content-Type: application/json");
require_once __DIR__ . '/Sistema/config/bd.php'; 

$input_json = file_get_contents('php://input');
$data = json_decode($input_json, true) ?: [];

$id = $data['id'] ?? $_REQUEST['id'] ?? null;
$resultado = $data['resultado'] ?? $_REQUEST['resultado'] ?? null;

if (!$id || !$resultado) {
    echo json_encode(["status" => "error", "message" => "Faltan parámetros 'id' o 'resultado'."]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM log_tickets_teams WHERE id = ?");
    $stmt->execute([$id]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$log) {
        // Enviar 2 al bot usando formato JSON
        http_response_code(404);
        echo json_encode([
            "resultado_local" => 2, 
            "status" => "error", 
            "message" => "No se encontró el registro en log_tickets_teams."
        ]);
        exit;
    }

    if ($resultado == 1) {
        // Proceso Exitoso
        $update = $conn->prepare("UPDATE log_tickets_teams SET status_proceso = 'Correcto' WHERE id = ?");
        $update->execute([$id]);
        
        // CERRAR EL TICKET ABIERTO EN LA MESA RECIEN
        $ticket_id = $log['ticket_creado'] ?? null;
        if ($ticket_id) {
            $url = "https://servicedesk.grupoaxo.com/api/v3/requests/{$ticket_id}";
            $api_key = "423CEBBE-E849-4D17-9CA3-CD6AB3319401";

            $password_final = !empty($log['password_temporal']) ? $log['password_temporal'] : 'Inicio_2026*!';

            $payload_cierre = [
                "request" => [
                    "status" => ["id" => "4"],
                    "resolution" => [
                        "content" => "Petición generada por Teams y resuelta exitosamente por automatización (RPA). La contraseña temporal es {$password_final}"
                    ],
                    "is_fcr" => true
                ]
            ];
            $post_fields = http_build_query(['input_data' => json_encode($payload_cierre)]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS => $post_fields,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    "authtoken: {$api_key}",
                    "Accept: application/v3.0+json",
                    "Content-Type: application/x-www-form-urlencoded"
                ]
            ]);
            $sd_res = curl_exec($ch);
            curl_close($ch);
        }

        // Enviar notificación de éxito
        if (!empty($log['correo'])) {
            enviarCorreoConfirmacion($log['correo'], 1, $log['ticket_creado'] ?? 'N/A', $password_final);
        }

        // Responder con información de depuración
        echo json_encode([
            "resultado_local" => 1,
            "sd_response" => json_decode($sd_res ?? '{}', true)
        ]);
        exit;

    } else if ($resultado == 2) {
        $ticket_original = $log['ticket_creado'] ?? null;
        if ($ticket_id ?? $ticket_original) {
            $url = "https://servicedesk.grupoaxo.com/api/v3/requests/{$ticket_original}";
            $api_key = "423CEBBE-E849-4D17-9CA3-CD6AB3319401";

            // Obtener técnico usando carrusel
            $tec_id_sistema = obtenerTecnicoDisponible($conn);

            // 1. Asignar el técnico
            $request_data = [
                "status" => ["id" => "1"]
            ];
            if ($tec_id_sistema) {
                $request_data['technician'] = ["id" => $tec_id_sistema];
            }
            
            $post_fields = http_build_query(['input_data' => json_encode(["request" => $request_data])]);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_POSTFIELDS => $post_fields,
                CURLOPT_SSL_VERIFYPEER => false, 
                CURLOPT_HTTPHEADER => [
                    "authtoken: {$api_key}",
                    "Accept: application/v3.0+json",
                    "Content-Type: application/x-www-form-urlencoded"
                ]
            ]);
            curl_exec($ch);
            curl_close($ch);

            // 2. Agregar Nota de RPA Fallido
            $url_nota = "https://servicedesk.grupoaxo.com/api/v3/requests/{$ticket_original}/notes";
            $payload_nota = [
                "note" => [
                    "description" => "El RPA intentó procesar la solicitud para el reseteo de Success Factors (SSFF), pero los datos proporcionados no son válidos o no coinciden. El ticket ha sido reasignado al equipo para atención manual.",
                    "show_to_requester" => true
                ]
            ];
            $post_fields_nota = http_build_query(['input_data' => json_encode($payload_nota)]);
            $ch_nota = curl_init($url_nota);
            curl_setopt_array($ch_nota, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $post_fields_nota,
                CURLOPT_SSL_VERIFYPEER => false, 
                CURLOPT_HTTPHEADER => [
                    "authtoken: {$api_key}",
                    "Accept: application/v3.0+json",
                    "Content-Type: application/x-www-form-urlencoded"
                ]
            ]);
            curl_exec($ch_nota);
            curl_close($ch_nota);
        }

        // Actualizamos registro log como Erróneo
        $update = $conn->prepare("UPDATE log_tickets_teams SET status_proceso = 'Error', error_detalle = ? WHERE id = ?");
        $update->execute(["Falló el RPA porque los datos no corresponden. Se asignó la atención manual.", $id]);

        // Enviar notificación de fallo (pase a agente)
        if (!empty($log['correo'])) {
            enviarCorreoConfirmacion($log['correo'], 2, $ticket_original ?? 'N/A');
        }

        // Retornamos 2 porque hubo error operativo base
        echo json_encode([
            "resultado_local" => 2,
            "status" => "error",
            "message" => "El RPA reportó fallo en los datos, reasignado manual exitoso."
        ]);
    }

} catch (\Throwable $e) {
    // Retornar error capturado explícitamente en formato JSON en caso de fallo catastrófico (BD, Fatal, etc)
    http_response_code(500);
    echo json_encode([
        "resultado_local" => 2,
        "status" => "fatal_error",
        "message" => $e->getMessage(),
        "file" => basename($e->getFile()),
        "line" => $e->getLine()
    ]);
}

// Funciones de Carrusel
function obtenerTecnicoDisponible($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, id_sistema, nombre FROM tecnicos WHERE activo = 1 AND modo_asignacion = 1 ORDER BY orden_asignacion ASC, id ASC");
        $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($tecnicos)) return null;
        $total_tecnicos = count($tecnicos);
        
        $stmt_config = $pdo->query("SELECT valor FROM configuracion_asignacion WHERE id = 1");
        $fila_config = $stmt_config->fetch(PDO::FETCH_ASSOC);
        $ultimo_id = $fila_config ? (int)$fila_config['valor'] : null;
        
        $indice_inicio = 0;
        if ($ultimo_id) {
            foreach ($tecnicos as $i => $tec) {
                if ((int)$tec['id'] === $ultimo_id) {
                    $indice_inicio = $i + 1;
                    break;
                }
            }
        }
        
        $indice_actual = $indice_inicio % $total_tecnicos;
        $tecnico_seleccionado = $tecnicos[$indice_actual];
        
        guardarUltimoTecnicoAsignado($pdo, $tecnico_seleccionado['id'], $ultimo_id, $total_tecnicos);
        
        return $tecnico_seleccionado['id_sistema'];
    } catch (Exception $e) {
        return null; 
    }
}

function guardarUltimoTecnicoAsignado($pdo, $id_tecnico, $ultimo_id_asignado, $total_disponibles) {
    try {
        $stmt_config = $pdo->prepare("INSERT INTO configuracion_asignacion (id, valor) VALUES (1, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt_config->execute([$id_tecnico, $id_tecnico]);
        $stmt_control = $pdo->prepare("INSERT INTO control_asignacion (id_tecnico, ultimo_id_asignado, total_disponibles, metodo) VALUES (?, ?, ?, 'carrusel')");
        $stmt_control->execute([$id_tecnico, $ultimo_id_asignado, $total_disponibles]);
    } catch (Exception $e) {}
}

function enviarCorreoConfirmacion($correo_destino, $resultado, $ticket, $pass_dinamica = 'Inicio_2026*!') {
    if (empty($correo_destino)) return;
    
    $url = "https://api.resend.com/emails";
    $token = "re_MGLhPdwA_JvoX4Fkikh3S3gP2Yw5coGwh";

    if ($resultado == 1) {
        $html = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2>¡Hola! Tu solicitud ha sido procesada</h2>
                <p>Tu solicitud de reseteo de contraseña para Success Factors (Ticket: <b>{$ticket}</b>) ha sido completada exitosamente de forma automatizada.</p>
                <br>Tu nueva <b>contraseña temporal</b> es: <strong style='font-size: 18px; color: #0d6efd;'>{$pass_dinamica}</strong>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #888;'><em>Este es un mensaje generado automáticamente, por favor no respondas a este correo.</em></p>
            </div>
        ";
    } else {
        $html = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2>Actualización de tu solicitud de RPA 🤖</h2>
                <p>Estimado usuario,</p>
                <p>Te informamos que hemos recibido tu solicitud de reseteo de contraseña para Success Factors (Ticket: <b>{$ticket}</b>).</p>
                <p>Durante el proceso de automatización por nuestra asistente, detectamos que los datos proporcionados no corresponden o no coinciden de forma exacta con nuestros registros en el corporativo, por lo que no fue posible procesarla automáticamente.</p>
                <p>No te preocupes, <b>tu solicitud ha sido canalizada directamente con un agente humano especializado</b> de nuestra Mesa de Ayuda, quien se pondrá en contacto contigo a la brevedad para brindarte seguimiento personalizado y resolver tu solicitud sin que tengas que volver a levantarla.</p>
                <p>Agradecemos tu paciencia.</p>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #888;'><em>Este es un mensaje generado automáticamente, por favor no respondas a este correo.</em></p>
            </div>
        ";
    }

    /*
    // --- CÓDIGO ORIGINAL RESEND API (COMENTADO A FAVOR DE OPCIÓN A) ---
    $payload = [
        "from" => "ArIA <ArIA@updates.swiftdesk.com.mx>",
        "to" => $correo_destino,
        "subject" => "Solicitud de reset de password para success factor",
        "html" => $html,
        "attachments" => []
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json"
        ]
    ]);
    curl_exec($ch);
    curl_close($ch);
    */

    // --- NUEVO CÓDIGO SMTP OFICIAL GRUPO AXO (OPCIÓN A) ---
    $libs_path = __DIR__ . '/libs/PHPMailer/src';
    if(!file_exists($libs_path . '/PHPMailer.php')) {
        error_log("Falta subir la carpeta libs/PHPMailer/src/ al servidor.");
        return;
    }
    require_once $libs_path . '/Exception.php';
    require_once $libs_path . '/PHPMailer.php';
    require_once $libs_path . '/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.office365.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'help_desk@grupoaxo.com';
        $mail->Password   = 'H3lpD3sk69';
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('help_desk@grupoaxo.com', 'Tester Bot AXO');
        $mail->addAddress($correo_destino);

        $mail->isHTML(true);
        $mail->Subject = 'Solicitud de reset de password para success factor';
        $mail->Body    = $html;

        $mail->send();
    } catch (Exception $e) {
        error_log("Error enviando correo SMTP AXO Opcion A: " . $mail->ErrorInfo);
    }


}
?>
