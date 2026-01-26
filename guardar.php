<?php
// guardar.php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $usuario = $_POST['usuario_login'];
    $correo = $_POST['correo'];
    $id_sis = $_POST['id_sistema'];
    $pass = $_POST['password'];

    // Iniciar transacción para asegurar que se guarden datos y horarios juntos
    $conn->beginTransaction();

    try {
        if (empty($id)) {
            // INSERTAR TÉCNICO
            $sql = "INSERT INTO tecnicos (nombre, usuario_login, password, correo, id_sistema, activo, modo_asignacion) 
                    VALUES (?, ?, ?, ?, ?, 0, 1)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nombre, $usuario, $pass, $correo, $id_sis]);
            $id = $conn->lastInsertId();
        } else {
            // ACTUALIZAR TÉCNICO
            if (empty($pass)) {
                $sql = "UPDATE tecnicos SET nombre=?, usuario_login=?, correo=?, id_sistema=? WHERE id=?";
                $params = [$nombre, $usuario, $correo, $id_sis, $id];
            } else {
                $sql = "UPDATE tecnicos SET nombre=?, usuario_login=?, password=?, correo=?, id_sistema=? WHERE id=?";
                $params = [$nombre, $usuario, $pass, $correo, $id_sis, $id];
            }
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }

        // --- MANEJO DE HORARIOS ---
        
        // 1. Borrar horarios anteriores de este técnico
        $stmtDel = $conn->prepare("DELETE FROM horarios_tecnicos WHERE id_tecnico = ?");
        $stmtDel->execute([$id]);

        // 2. Insertar los nuevos horarios seleccionados
        if (isset($_POST['dias'])) {
            $sqlHorario = "INSERT INTO horarios_tecnicos (id_tecnico, dia_semana, hora_entrada, hora_salida, inicio_comida, fin_comida) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmtHorario = $conn->prepare($sqlHorario);

            foreach ($_POST['dias'] as $dia => $activado) {
                // $dia viene como 'Mon', 'Tue'. Verificamos que se hayan enviado los tiempos
                if (isset($_POST['h_entrada'][$dia])) {
                    $entrada = $_POST['h_entrada'][$dia];
                    $salida  = $_POST['h_salida'][$dia];
                    $ini_comida = $_POST['h_ini_comida'][$dia];
                    $fin_comida = $_POST['h_fin_comida'][$dia];

                    $stmtHorario->execute([$id, $dia, $entrada, $salida, $ini_comida, $fin_comida]);
                }
            }
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollBack();
        die("Error al guardar: " . $e->getMessage());
    }
    
    header("Location: dashboard.php");
}
?>