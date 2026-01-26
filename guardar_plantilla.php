<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $plantilla = $_POST['plantilla_incidente'];
    $categoria = $_POST['categoria'];
    $subcategoria = $_POST['subcategoria'];
    $articulo = $_POST['articulo'];
    $grupo = $_POST['grupo'];
    $origen = $_POST['origen'];
    $id_grupo = $_POST['id_grupo'];

    if (empty($id)) {
        // INSERTAR
        $sql = "INSERT INTO plantillas_incidentes (plantilla_incidente, categoria, subcategoria, articulo, grupo, origen, id_grupo) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$plantilla, $categoria, $subcategoria, $articulo, $grupo, $origen, $id_grupo]);
    } else {
        // ACTUALIZAR
        $sql = "UPDATE plantillas_incidentes 
                SET plantilla_incidente=?, categoria=?, subcategoria=?, articulo=?, grupo=?, origen=?, id_grupo=? 
                WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$plantilla, $categoria, $subcategoria, $articulo, $grupo, $origen, $id_grupo, $id]);
    }
    
    header("Location: plantillas.php");
}
?>