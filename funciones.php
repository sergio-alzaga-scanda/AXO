<?php
// funciones.php
require_once 'db.php';

function actualizarEstadosTecnicos($conn) {
    // Día y Hora actual
    $dia_actual = date('D'); // Mon, Tue, Wed...
    $hora_actual = date('H:i:s');

    // 1. Resetear a todos a 0 primero (por seguridad)
    $conn->query("UPDATE tecnicos SET activo = 0");

    // 2. Buscar técnicos que cumplan la regla hoy
    // La regla lógica es:
    // (Hora >= Entrada AND Hora < (InicioComida - 20min)) 
    // OR 
    // (Hora >= FinComida AND Hora <= Salida)
    
    $sql = "UPDATE tecnicos t
            INNER JOIN horarios_tecnicos h ON t.id = h.id_tecnico
            SET t.activo = 1
            WHERE h.dia_semana = '$dia_actual'
            AND (
                ('$hora_actual' >= h.hora_entrada AND '$hora_actual' < SUBTIME(h.inicio_comida, '00:20:00'))
                OR
                ('$hora_actual' >= h.fin_comida AND '$hora_actual' <= h.hora_salida)
            )";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
}
// En funciones.php (al final del archivo)

function registrarAccion($conn, $usuario_id, $usuario_nombre, $accion, $descripcion) {
    try {
        $stmt = $conn->prepare("INSERT INTO historial_acciones (usuario_id, usuario_nombre, accion, descripcion) VALUES (?, ?, ?, ?)");
        $stmt->execute([$usuario_id, $usuario_nombre, $accion, $descripcion]);
    } catch (PDOException $e) {
        // Opcional: Manejar error silenciosamente para no interrumpir el flujo principal
        // error_log("Error al registrar log: " . $e->getMessage());
    }
}
?>