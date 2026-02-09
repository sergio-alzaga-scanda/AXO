<?php
session_start();
require_once 'db.php';
require_once 'funciones.php'; // Incluir funciones

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Recolección de variables (Mapeo exacto con los 'name' del HTML)
    $id = $_POST['id'] ?? null;
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
        if (empty($id)) {
            // --- INSERTAR (13 campos + status) ---
            $sql = "INSERT INTO plantillas_incidentes 
                    (status, plantilla_incidente, categoria, subcategoria, articulo, grupo, sitio, origen, id_grupo, descripcion, tipo_solicitud, asigna_tenico, tencifo_default) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
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
            $sql = "UPDATE plantillas_incidentes SET 
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
                $id // El ID va al final para el WHERE
            ]);
        // NUEVO: Log
        registrarAccion($conn, $_SESSION['user_id'], $_SESSION['nombre'], 'EDITAR_PLANTILLA', "Editó plantilla ID: $id ($plantilla)");
    }
        
        header("Location: plantillas.php");
        exit;

    } catch (PDOException $e) {
        // En producción, es mejor registrar el error en un log y mostrar un mensaje genérico
        die("Error en base de datos: " . $e->getMessage());
    }
}
?>