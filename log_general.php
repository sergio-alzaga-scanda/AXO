<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
require_once 'db.php';
require_once 'funciones.php';

actualizarEstadosTecnicos($conn);

// 1. Obtener Técnicos y Horarios
$sql = "SELECT t.*, 
        CONCAT('[', GROUP_CONCAT(
            CONCAT('{\"dia\":\"', h.dia_semana, '\",\"entrada\":\"', h.hora_entrada, '\",\"salida\":\"', h.hora_salida, '\",\"ini_comida\":\"', h.inicio_comida, '\",\"fin_comida\":\"', h.fin_comida, '\"}')
        ), ']') as horarios_json
        FROM tecnicos t
        LEFT JOIN horarios_tecnicos h ON t.id = h.id_tecnico
        WHERE t.id != 15
        GROUP BY t.id";

$stmt = $conn->query($sql);
$tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Consulta Estado del Servicio (API)
$stmtServ = $conn->query("SELECT activo FROM configuracion_servicio WHERE id = 1");
$servicioData = $stmtServ->fetch(PDO::FETCH_ASSOC);
$servicioActivo = $servicioData ? $servicioData['activo'] : 0;

// 3. Contadores de Tickets
// Tickets de HOY
$stmtHoy = $conn->query("SELECT COUNT(*) as total FROM tickets_asignados WHERE fecha_asignacion >= CURDATE()"); 
$ticketsHoy = $stmtHoy->fetch(PDO::FETCH_ASSOC)['total'];

// Tickets TOTALES (Histórico)
$stmtTotal = $conn->query("SELECT COUNT(*) as total FROM tickets_asignados"); 
$ticketsTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

?>
<?php
// log_general.php

require_once 'db.php';
require_once 'funciones.php'; // Incluir funciones

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }


// Consultar últimos 100 eventos
$sql = "SELECT * FROM historial_acciones ORDER BY fecha DESC LIMIT 100";
$stmt = $conn->query($sql);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Log General - AXO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid px-4">
            <span class="navbar-brand">Sistema AXO</span>
            
            <div class="d-flex align-items-center ms-3">
                <div class="form-check form-switch text-white">
                    <input class="form-check-input" type="checkbox" id="switchServicio" <?= $servicioActivo ? 'checked' : '' ?> style="cursor: pointer;">
                    <label class="form-check-label ms-2" for="switchServicio" id="lblServicio">
                        <?= $servicioActivo ? 'Servicio: ON' : 'Servicio: OFF' ?>
                    </label>
                </div>
            </div>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse ms-4" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Técnicos</a></li>
                    <li class="nav-item"><a class="nav-link" href="plantillas.php">Plantillas</a></li>
                    <li class="nav-item"><a class="nav-link active" href="log_general.php">Auditoría</a></li>
                </ul>
                
                <div class="d-flex text-white me-4">
                    <div class="border px-3 py-1 rounded me-2 text-center">
                        <small class="d-block text-white-50" style="font-size: 0.75rem;">HOY</small>
                        <strong><?= $ticketsHoy ?></strong> <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="border px-3 py-1 rounded text-center bg-secondary bg-opacity-25">
                        <small class="d-block text-white-50" style="font-size: 0.75rem;">TOTAL HISTÓRICO</small>
                        <strong><?= $ticketsTotal ?></strong> <i class="bi bi-database"></i>
                    </div>
                </div>

                <div class="d-flex text-white align-items-center border-start ps-3">
                    <span class="me-3">Hola, <?= $_SESSION['nombre'] ?></span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Salir</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <h2 class="mb-3">Registro de Actividades</h2>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaLogs" class="table table-striped table-hover table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha/Hora</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                            <tr>
                                <td><?= date('Y-m-d H:i:s', strtotime($log['fecha'])) ?></td>
                                <td class="fw-bold"><?= $log['usuario_nombre'] ?></td>
                                <td><span class="badge bg-secondary"><?= $log['accion'] ?></span></td>
                                <td><?= $log['descripcion'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
         // --- Tooltips ---
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // --- Switch Servicio ---
        document.getElementById('switchServicio').addEventListener('change', function() {
            let estado = this.checked ? 1 : 0;
            let label = document.getElementById('lblServicio');
            fetch('cambiar_estado.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'activo=' + estado
            })
            .then(response => response.text())
            .then(data => {
                label.innerText = estado ? 'Servicio: ON' : 'Servicio: OFF';
            });
        });
        $(document).ready(function() {
            $('#tablaLogs').DataTable({
                language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                order: [[ 0, "desc" ]] // Ordenar por fecha descendente
            });
        });
    </script>
</body>
</html>