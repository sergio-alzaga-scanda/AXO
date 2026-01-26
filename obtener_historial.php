<?php
// obtener_historial.php
require_once 'db.php';

if (isset($_POST['nombre'])) {
    $nombre_tecnico = $_POST['nombre'];

    // Buscamos en la tabla tickets_asignados coincidiendo el nombre
    $sql = "SELECT * FROM tickets_asignados 
            WHERE usuario_tecnico = ? 
            ORDER BY fecha_asignacion DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$nombre_tecnico]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($tickets) > 0) {
        foreach ($tickets as $ticket) {
            $fecha = date('d/m/Y H:i', strtotime($ticket['fecha_asignacion']));
            echo "<tr>
                    <td>{$ticket['id_ticket']}</td>
                    <td>{$ticket['grupo']}</td>
                    <td>{$ticket['templete']}</td>
                    <td>{$fecha}</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='4' class='text-center text-muted'>No hay tickets asignados a este técnico.</td></tr>";
    }
}
?>