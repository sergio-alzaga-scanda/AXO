<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
require_once 'db.php';
require_once 'funciones.php';

// Estadísticas
try {
    $stmtStats = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_proceso = 'Éxito' THEN 1 ELSE 0 END) as exito,
            SUM(CASE WHEN status_proceso = 'Error' THEN 1 ELSE 0 END) as error
        FROM log_tickets_teams
    ");
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    $total = $stats['total'] ?? 0;
    $exito = $stats['exito'] ?? 0;
    $error = $stats['error'] ?? 0;

    // Registros
    $stmtLogs = $conn->query("SELECT * FROM log_tickets_teams ORDER BY fecha_creacion DESC");
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $total = $exito = $error = 0;
    $logs = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Bot Teams</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .card-stat { border-left: 5px solid; transition: all 0.3s ease; border-radius: 10px; }
        .card-stat:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
        .border-primary { border-left-color: #0d6efd !important; }
        .border-success { border-left-color: #198754 !important; }
        .border-danger { border-left-color: #dc3545 !important; }
        
        .table-responsive { background: white; border-radius: 10px; padding: 15px; }
        .table th { background-color: #212529 !important; color: white !important; font-weight: 500; letter-spacing: 0.5px; }
        .table tbody tr { transition: background 0.2s; }
        .badge { font-weight: 500; font-size: 0.85rem; padding: 0.4em 0.6em; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container-fluid px-4">
            <span class="navbar-brand fw-bold">Sistema AXO</span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse ms-4" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Técnicos <i class="bi bi-people"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="plantillas.php">Plantillas <i class="bi bi-file-earmark-text"></i></a></li>
                    <li class="nav-item"><a class="nav-link " href="log_general.php">Auditoría <i class="bi bi-shield-check"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="reportes.php">Reportes <i class="bi bi-bar-chart-line"></i></a></li>
                    <li class="nav-item"><a class="nav-link active" href="reporte_teams.php">Bot Teams <i class="bi bi-robot"></i></a></li>
                </ul>
                <div class="d-flex text-white align-items-center border-start ps-3">
                    <span class="me-3">Hola, <?= $_SESSION['nombre'] ?></span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Salir</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-secondary fw-bold mb-0">
                <i class="bi bi-robot text-primary me-2"></i> Peticiones Automatizadas (Teams)
            </h2>
        </div>

        <!-- Cards -->
        <div class="row mb-5">
            <div class="col-md-4">
                <div class="card card-stat border-primary shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size: 0.8rem; letter-spacing: 1px;">Total Peticiones</h6>
                                <h1 class="mb-0 fw-bold text-dark display-5"><?= $total ?></h1>
                            </div>
                            <div class="fs-1 text-primary opacity-50"><i class="bi bi-list-task"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-stat border-success shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size: 0.8rem; letter-spacing: 1px;">Creación Exitosa</h6>
                                <h1 class="mb-0 fw-bold text-success display-5"><?= $exito ?></h1>
                            </div>
                            <div class="fs-1 text-success opacity-50"><i class="bi bi-check-circle"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-stat border-danger shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size: 0.8rem; letter-spacing: 1px;">Con Errores / Fallos</h6>
                                <h1 class="mb-0 fw-bold text-danger display-5"><?= $error ?></h1>
                            </div>
                            <div class="fs-1 text-danger opacity-50"><i class="bi bi-x-circle"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card shadow-sm mb-5 border-0 rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaTeams" class="table table-hover align-middle w-100">
                        <thead class="table-dark">
                            <tr>
                                <th># ID</th>
                                <th>Fecha</th>
                                <th>Usr (Núm. Empleado)</th>
                                <th>Correo Remitente</th>
                                <th class="text-center">Ticket Generado</th>
                                <th class="text-center">Estado del Proceso</th>
                                <th>Detalle / Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                                <tr>
                                    <td class="text-muted fw-bold">#<?= $log['id'] ?></td>
                                    <td><?= date('d/m/Y h:i A', strtotime($log['fecha_creacion'])) ?></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($log['numero_usuario'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($log['correo'] ?? 'N/A') ?></td>
                                    <td class="text-center">
                                        <?php if($log['ticket_creado']): ?>
                                            <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill"><i class="bi bi-ticket-fill me-1"></i> <?= $log['ticket_creado'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if($log['status_proceso'] === 'Éxito'): ?>
                                            <span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-circle"></i> Éxito</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3"><i class="bi bi-x-circle"></i> Error</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($log['error_detalle']): ?>
                                            <small class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($log['error_detalle']) ?></small>
                                        <?php else: ?>
                                            <small class="text-success"><i class="bi bi-check2"></i> Petición Resuelta Auto.</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tablaTeams').DataTable({
                language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                order: [[ 0, "desc" ]],
                pageLength: 25,
                dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
            });
        });
    </script>
</body>
</html>
