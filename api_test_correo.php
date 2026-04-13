<?php
// Endpoint genérico de prueba para envío de correo mediante Office 365 (Preparado para Option A)
header("Content-Type: application/json");

// Importación de PHPMailer
$libs_path = __DIR__ . '/libs/PHPMailer/src';
if (!file_exists($libs_path . '/PHPMailer.php')) {
    echo json_encode(["status" => "error", "message" => "Falta subir la carpeta libs/PHPMailer/src/ al servidor."]);
    exit;
}

require_once $libs_path . '/Exception.php';
require_once $libs_path . '/PHPMailer.php';
require_once $libs_path . '/SMTP.php';

$mail = new \PHPMailer\PHPMailer\PHPMailer(true);

try {
    // Configuración del servidor SMTP (Office 365)
    $mail->isSMTP();
    $mail->Host       = 'smtp.office365.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'tester_bot@grupoaxo.com';
    $mail->Password   = '4X0_2026+';
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    $mail->CharSet = 'UTF-8';

    // Remitente y Destinatario
    $mail->setFrom('tester_bot@grupoaxo.com', 'Tester Bot AXO');
    $mail->addAddress('sergioarmandoalzagadiaz@gmail.com');

    // Contenido del Correo
    $mail->isHTML(true);
    $mail->Subject = 'Correo de Prueba - O365 (Option A)';
    $mail->Body    = "
        <div style='font-family: Arial, sans-serif; color: #333;'>
            <h2>¡Hola Sergio!</h2>
            <p>Este es un correo enviado nativamente desde tu servidor IIS hacia los servidores institucionales de <b>Grupo Axo</b> mediante SMTP de <b>Office 365</b>.</p>
            <p>Si estás leyendo esto en tu bandeja, significa que la gente de Infraestructura ya autorizó la IP en Entra ID / Conditional Access y los mensajes están pasando.</p>
            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='font-size: 12px; color: #888;'><em>Este es un sistema de prueba.</em></p>
        </div>
    ";
    
    // Activar modo debug exhaustivo para ver traza de rechazo de red y auth
    $mail->SMTPDebug = 2; // Nivel 2
    $mail->Debugoutput = 'html'; 
    
    ob_start();
    $enviado = $mail->send();
    $debug_info = ob_get_clean();
    
    if ($enviado) {
        echo json_encode([
            "status" => "exito", 
            "message" => "Correo de prueba enviado de forma correcta a sergioarmandoalzagadiaz@gmail.com",
            "debug_trace" => strip_tags($debug_info)
        ]);
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "Ocurrió un error dentro de la clase PHPMailer pero no lanzo excepción.",
            "debug_trace" => strip_tags($debug_info)
        ]);
    }

} catch (Exception $e) {
    $debug_info = ob_get_clean();
    echo json_encode([
        "status" => "error_smtp", 
        "message" => "Error enviando correo SMTP AXO.",
        "detalle_tecnico" => $mail->ErrorInfo,
        "debug_trace" => strip_tags($debug_info)
    ]);
}
?>
