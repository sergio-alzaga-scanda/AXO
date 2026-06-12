<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
require_once 'db.php';

$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-7 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

// Preparar las fechas para la consulta SQL
$inicio_fecha = $fecha_inicio . ' 00:00:00';
$fin_fecha = $fecha_fin . ' 23:59:59';

try {
    $stmt = $conn->prepare("
        SELECT id_ticket, usuario_tecnico, grupo, templete, fecha_asignacion, descripcion_limpia, palabras_clave, confianza, asunto_ticket 
        FROM tickets_asignados 
        WHERE fecha_asignacion >= :inicio AND fecha_asignacion <= :fin
        ORDER BY fecha_asignacion DESC
    ");
    $stmt->execute([':inicio' => $inicio_fecha, ':fin' => $fin_fecha]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Configurar headers para descarga de archivo CSV compatible con Excel
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="detalle_tickets_' . $fecha_inicio . '_a_' . $fecha_fin . '.csv"');
    
    // Imprimir el BOM de UTF-8 para compatibilidad directa con Excel (caracteres con acentos, eñes, etc.)
    echo "\xEF\xBB\xBF";
    
    // Declarar el separador explícito para Excel
    echo "sep=;\r\n";
    
    $output = fopen('php://output', 'w');
    
    // Escribir cabeceras en español para claridad
    fputcsv($output, [
        'ID Ticket',
        'Técnico Asignado',
        'Grupo',
        'Plantilla / Templete',
        'Fecha Asignación',
        'Asunto',
        'Palabras Clave',
        'Confianza %',
        'Descripción Limpia'
    ], ';');
    
    foreach ($tickets as $row) {
        fputcsv($output, [
            $row['id_ticket'],
            $row['usuario_tecnico'] ? $row['usuario_tecnico'] : 'Sin asignar',
            $row['grupo'] ? $row['grupo'] : 'N/A',
            $row['templete'] ? $row['templete'] : 'N/A',
            $row['fecha_asignacion'],
            $row['asunto_ticket'] ? $row['asunto_ticket'] : 'N/A',
            $row['palabras_clave'] ? $row['palabras_clave'] : 'N/A',
            $row['confianza'] !== null ? $row['confianza'] . '%' : 'N/A',
            $row['descripcion_limpia'] ? $row['descripcion_limpia'] : 'N/A'
        ], ';');
    }
    
    fclose($output);
    exit;
} catch (Exception $e) {
    echo "Error al generar el archivo de exportación: " . $e->getMessage();
}
?>
