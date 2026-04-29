<?php
session_start();
require_once 'db.php';
require_once 'funciones.php'; // Incluir funciones

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Recolección de variables (Mapeo exacto con los 'name' del HTML)
    $id = $_POST['id'] ?? null;
    $id_original = $_POST['id_original'] ?? null;
    $status = $_POST['status'] ?? 1;
    $plantilla = $_POST['plantilla_incidente'];
    $categoria = $_POST['categoria'];
    $subcategoria = $_POST['subcategoria'];
    $articulo = $_POST['articulo'];
    $grupo = $_POST['grupo'];
    $sitio = $_POST['sitio'];
    $origen = $_POST['origen'];
    $id_grupo = $_POST['id_grupo'];
    $descripcion = $_POST['descripcion'];
    $tipo = $_POST['tipo_solicitud'];
    
    // Select e Input final
    $asigna = $_POST['asigna_tenico'] ?? 0; // Viene del Select (0 o 1)
    $tec_default = $_POST['tencifo_default']; // Nota: Mantenemos tu nombre de columna 'tencifo_default'

    try {
        // Asegurar que la tabla y columna existan siempre al inicio
        $conn->exec("CREATE TABLE IF NOT EXISTS historial_plantillas (
            id_historial INT AUTO_INCREMENT PRIMARY KEY,
            id_plantilla INT,
            status TINYINT,
            plantilla_incidente VARCHAR(255),
            categoria VARCHAR(255),
            subcategoria VARCHAR(255),
            articulo VARCHAR(255),
            grupo VARCHAR(255),
            sitio VARCHAR(255),
            origen VARCHAR(255),
            id_grupo VARCHAR(255),
            descripcion TEXT,
            tipo_solicitud VARCHAR(255),
            asigna_tenico TINYINT,
            tencifo_default VARCHAR(255),
            fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            usuario_modificacion VARCHAR(255),
            accion VARCHAR(50) DEFAULT 'EDICION'
        )");

        try {
            $conn->exec("ALTER TABLE historial_plantillas ADD COLUMN accion VARCHAR(50) DEFAULT 'EDICION'");
        } catch (PDOException $e) { }

        if (empty($id_original)) {
            // --- INSERTAR (14 campos: ID + 13 + status) ---
            $sql = "INSERT INTO plantillas_incidentes 
                    (id, status, plantilla_incidente, categoria, subcategoria, articulo, grupo, sitio, origen, id_grupo, descripcion, tipo_solicitud, asigna_tenico, tencifo_default) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $id,
                $status, 
                $plantilla, 
                $categoria, 
                $subcategoria, 
                $articulo, 
                $grupo, 
                $sitio, 
                $origen, 
                $id_grupo, 
                $descripcion, 
                $tipo, 
                $asigna, 
                $tec_default
            ]);
            // NUEVO: Log
        registrarAccion($conn, $_SESSION['user_id'], $_SESSION['nombre'], 'CREAR_PLANTILLA', "Creó plantilla: $plantilla");
        } else {
            // --- ACTUALIZAR ---
            // NUEVO: Antes de actualizar, guardar el estado BASE si es la primera vez que se edita en el historial
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM historial_plantillas WHERE id_plantilla = ?");
            $stmtCheck->execute([$id_original]);
            if ($stmtCheck->fetchColumn() == 0) {
                $stmtOld = $conn->prepare("SELECT * FROM plantillas_incidentes WHERE id = ?");
                $stmtOld->execute([$id_original]);
                if ($oldState = $stmtOld->fetch(PDO::FETCH_ASSOC)) {
                    $sqlBase = "INSERT INTO historial_plantillas 
                            (id_plantilla, status, plantilla_incidente, categoria, subcategoria, articulo, grupo, sitio, origen, id_grupo, descripcion, tipo_solicitud, asigna_tenico, tencifo_default, usuario_modificacion, accion) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'BASE_HISTORICO')";
                    $stmtBase = $conn->prepare($sqlBase);
                    $stmtBase->execute([
                        $id_original, $oldState['status'], $oldState['plantilla_incidente'], $oldState['categoria'], $oldState['subcategoria'], $oldState['articulo'], $oldState['grupo'], $oldState['sitio'], $oldState['origen'], $oldState['id_grupo'], $oldState['descripcion'], $oldState['tipo_solicitud'], $oldState['asigna_tenico'], $oldState['tencifo_default'], 'Sistema_Base'
                    ]);
                }
            }

            $sql = "UPDATE plantillas_incidentes SET 
                    id=?,
                    status=?, 
                    plantilla_incidente=?, 
                    categoria=?, 
                    subcategoria=?, 
                    articulo=?, 
                    grupo=?, 
                    sitio=?, 
                    origen=?, 
                    id_grupo=?, 
                    descripcion=?, 
                    tipo_solicitud=?, 
                    asigna_tenico=?, 
                    tencifo_default=?
                    WHERE id=?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $id,
                $status, 
                $plantilla, 
                $categoria, 
                $subcategoria, 
                $articulo, 
                $grupo, 
                $sitio, 
                $origen, 
                $id_grupo, 
                $descripcion, 
                $tipo, 
                $asigna, 
                $tec_default, 
                $id_original // El ID original va al final para el WHERE
            ]);
        // NUEVO: Log
        registrarAccion($conn, $_SESSION['user_id'], $_SESSION['nombre'], 'EDITAR_PLANTILLA', "Editó plantilla ID: $id ($plantilla)");
    }
        
        // --- NUEVO: GUARDAR EN HISTORIAL (El nuevo estado de la Creación o Edición) ---
        $usuarioLogueado = $_SESSION['nombre'] ?? 'Sistema';
        $accion_historial = empty($id_original) ? 'CREACION' : 'EDICION';

        // Insertar siempre un nuevo registro histórico
        $sqlHistorial = "INSERT INTO historial_plantillas 
                (id_plantilla, status, plantilla_incidente, categoria, subcategoria, articulo, grupo, sitio, origen, id_grupo, descripcion, tipo_solicitud, asigna_tenico, tencifo_default, usuario_modificacion, accion) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtHist = $conn->prepare($sqlHistorial);
        $stmtHist->execute([
            $id, $status, $plantilla, $categoria, $subcategoria, $articulo, $grupo, $sitio, $origen, $id_grupo, $descripcion, $tipo, $asigna, $tec_default, $usuarioLogueado, $accion_historial
        ]);

        header("Location: plantillas.php");
        exit;

    } catch (PDOException $e) {
        // En producción, es mejor registrar el error en un log y mostrar un mensaje genérico
        die("Error en base de datos: " . $e->getMessage());
    }
}
?>