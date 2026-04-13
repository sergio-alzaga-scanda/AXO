<?php
require_once 'c:\wamp64\www\AXO\Sistema\config\bd.php';
try { 
    $conn->exec("ALTER TABLE log_tickets_teams ADD COLUMN tipo_solicitud VARCHAR(100) DEFAULT 'No especificada'"); 
    echo "Columna creada satisfactoriamente."; 
} catch (Exception $e) { 
    echo "OK: " . $e->getMessage(); 
}
?>
