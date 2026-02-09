<?php
session_start();
require_once 'db.php';
require_once 'funciones.php'; // Incluir funciones

if (isset($_POST['nombre'])) {
    $nombre_tecnico = $_POST['nombre'];
    
    // Consulta ordenada del más reciente al más antiguo
    $sql = "SELECT * FROM tickets_asignados WHERE usuario_tecnico = ? ORDER BY fecha_asignacion DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$nombre_tecnico]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($tickets) > 0) {
        foreach ($tickets as $ticket) {
            // Formato ordenable para DataTables (YYYY-MM-DD HH:mm:ss) o legible
            $fecha = date('Y-m-d H:i', strtotime($ticket['fecha_asignacion'])); 
            echo "<tr>
                    <td>{$ticket['id_ticket']}</td>
                    <td>{$ticket['grupo']}</td>
                    <td>{$ticket['templete']}</td>
                    <td>{$fecha}</td>
                  </tr>";
        }
    }
    // No hacemos echo de "No hay tickets" aquí para no romper el formato de la tabla, 
    // DataTables mostrará "No data available" automáticamente si el body está vacío.
}
?>