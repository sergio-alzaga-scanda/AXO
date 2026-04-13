<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
require_once 'db.php';
require_once 'funciones.php';

// Estadísticas
try {
    // Init Seguro de Catálogo
    $conn->exec("CREATE TABLE IF NOT EXISTS catalogo_tipo_solicitud (
        id INT PRIMARY KEY,
        nombre VARCHAR(255) NOT NULL
    )");
    $conn->exec("INSERT IGNORE INTO catalogo_tipo_solicitud (id, nombre) VALUES 
        (1, 'Desbloqueo de correo'), (2, 'Reset de correo'), (3, 'Reset Success Factor')
    ");

    // Filtros
    $filtro_actual = $_GET['filtro'] ?? 'todos';
    $filtro_tipo = $_GET['tipo'] ?? '';
    
    // Status predeterminado inteligente
    $filtro_status_raw = $_GET['status'] ?? 'default';
    if ($filtro_status_raw === 'default') {
        $filtro_status = empty($filtro_tipo) ? 'exitosos' : 'todos';
    } else {
        $filtro_status = $filtro_status_raw;
    }

    $condiciones_base = [];
    $params = [];

    if ($filtro_actual === 'semana') {
        $condiciones_base[] = "fecha_creacion BETWEEN :inicio_fecha AND :fin_fecha";
        $params[':inicio_fecha'] = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $params[':fin_fecha'] = date('Y-m-d 23:59:59', strtotime('sunday this week'));
    } elseif ($filtro_actual === 'mes') {
        $condiciones_base[] = "fecha_creacion BETWEEN :inicio_fecha AND :fin_fecha";
        $params[':inicio_fecha'] = date('Y-m-01 00:00:00');
        $params[':fin_fecha'] = date('Y-m-t 23:59:59');
    } elseif ($filtro_actual === 'custom' && !empty($_GET['inicio']) && !empty($_GET['fin'])) {
        $condiciones_base[] = "fecha_creacion BETWEEN :inicio_fecha AND :fin_fecha";
        $params[':inicio_fecha'] = $_GET['inicio'] . ' 00:00:00';
        $params[':fin_fecha'] = $_GET['fin'] . ' 23:59:59';
    }

    if (!empty($filtro_tipo)) {
        $condiciones_base[] = "tipo_solicitud = :tipo";
        $params[':tipo'] = $filtro_tipo;
    }

    $donde_stats = "";
    if (count($condiciones_base) > 0) {
        $donde_stats = " WHERE " . implode(" AND ", $condiciones_base);
    }

    // Estadísticas
    $stmtStats = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_proceso = 'Creado y cerrado automaticamente' THEN 1 ELSE 0 END) as exito,
            SUM(CASE WHEN status_proceso = 'Generado automaticamente y resuelto por agente' THEN 1 ELSE 0 END) as error
        FROM log_api_tickets
        $donde_stats
    ");
    $stmtStats->execute($params);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    $total = $stats['total'] ?? 0;
    $exito = $stats['exito'] ?? 0;
    $error = $stats['error'] ?? 0;

    // Registros Filtro de Estado
    $condiciones_tabla = $condiciones_base;
    if ($filtro_status === 'exitosos') {
        $condiciones_tabla[] = "status_proceso = 'Creado y cerrado automaticamente'";
    } elseif ($filtro_status === 'fracasos') {
        $condiciones_tabla[] = "status_proceso != 'Creado y cerrado automaticamente'";
    }

    $donde_tabla = "";
    if (count($condiciones_tabla) > 0) {
        $condiciones_con_alias = array_map(function($cond) { return "l." . $cond; }, $condiciones_tabla);
        $donde_tabla = " WHERE " . implode(" AND ", $condiciones_con_alias);
    }

    $stmtLogs = $conn->prepare("
        SELECT l.*, COALESCE(c.nombre, l.tipo_solicitud) as tipo_solicitud_desc 
        FROM log_api_tickets l 
        LEFT JOIN catalogo_tipo_solicitud c ON l.tipo_solicitud = c.id 
        $donde_tabla
        ORDER BY l.fecha_creacion DESC
    ");
    $stmtLogs->execute($params);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // Métricas por tipo
    $condiciones_tipos = $condiciones_base;
    $condiciones_tipos[] = "tipo_solicitud IS NOT NULL AND tipo_solicitud != ''";
    $condiciones_tipos_con_alias = array_map(function($cond) { return "l." . $cond; }, $condiciones_tipos);
    $donde_tipos = " WHERE " . implode(" AND ", $condiciones_tipos_con_alias);

    $stmtTipos = $conn->prepare("
        SELECT COALESCE(c.nombre, l.tipo_solicitud) as servicio, COUNT(l.id) as cantidad 
        FROM log_api_tickets l
        LEFT JOIN catalogo_tipo_solicitud c ON l.tipo_solicitud = CAST(c.id AS CHAR)
        $donde_tipos 
        GROUP BY 1
    ");
    $stmtTipos->execute($params);
    $metricas_tipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    if (isset($_GET['debug'])) { echo $e->getMessage(); exit; }
    $total = $exito = $error = 0;
    $logs = [];
    $metricas_tipos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Generador API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .card-stat { border-left: 5px solid; transition: all 0.3s ease; border-radius: 10px; }
        .card-stat:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
        .border-primary { border-left-color: #0d6efd !important; }
        .border-success { border-left-color: #198754 !important; }
        .border-warning { border-left-color: #ffc107 !important; }
        
        .table-responsive { background: white; border-radius: 10px; padding: 15px; }
        .table th { background-color: #212529 !important; color: white !important; font-weight: 500; letter-spacing: 0.5px; }
        .badge { font-weight: 500; font-size: 0.85rem; padding: 0.4em 0.6em; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
        <div class="container-fluid px-4">
            <span class="navbar-brand fw-bold">Sistema AXO</span>
            
            <?php
            // Consultar Estado del Servicio de manera segura si no existe (Para que funcione en todas las pestañas)
            if (!isset($servicioActivo) && isset($conn)) {
                try {
                    $stmtServ = $conn->query("SELECT activo FROM configuracion_servicio WHERE id = 1");
                    $servicioData = $stmtServ->fetch(PDO::FETCH_ASSOC);
                    $servicioActivo = $servicioData ? $servicioData['activo'] : 0;
                } catch(Exception $e) { $servicioActivo = 0; }
            }
            ?>
            <div class="d-flex align-items-center ms-3">
                <div class="form-check form-switch text-white">
                    <input class="form-check-input" type="checkbox" id="switchServicio" <?= !empty($servicioActivo) ? 'checked' : '' ?> style="cursor: pointer;">
                    <label class="form-check-label ms-2 fw-bold" for="switchServicio" id="lblServicio" style="width: 105px;">
                        <?= !empty($servicioActivo) ? 'Servicio: ON <i class="bi bi-robot text-success"></i>' : 'Servicio: OFF <i class="bi bi-robot text-secondary"></i>' ?>
                    </label>
                </div>
            </div>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse ms-4" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Técnicos <i class="bi bi-people"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="plantillas.php">Plantillas <i class="bi bi-file-earmark-text"></i></a></li>
                    <li class="nav-item"><a class="nav-link " href="log_general.php">Auditoría <i class="bi bi-shield-check"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="reportes.php">Reportes <i class="bi bi-bar-chart-line"></i></a></li>
                    <li class="nav-item"><a class="nav-link" href="reporte_teams.php">Bot Teams <i class="bi bi-robot"></i></a></li>
                    <li class="nav-item"><a class="nav-link active" href="reporte_automatizados.php">Automatizados <i class="bi bi-cpu"></i></a></li>
                </ul>
                <?php
                    if(!isset($ticketsHoyBadge) && isset($conn)) {
                        try {
                            $stHoyB = $conn->query("SELECT COUNT(*) as t FROM tickets_asignados WHERE fecha_asignacion >= CURDATE()");
                            $ticketsHoyBadge = $stHoyB->fetch(PDO::FETCH_ASSOC)['t'] ?? 0;
                        } catch(Exception $e) { $ticketsHoyBadge = 0; }
                    }
                ?>
                <div class="d-flex align-items-center me-3 border-start ps-3 br-print-hide">
                    <span class="badge bg-primary border shadow-sm px-3 py-2" style="font-size: 0.85rem;" data-bs-toggle="tooltip" title="Tickets Asignados Hoy">
                        <i class="bi bi-ticket-detailed me-1"></i> Hoy: <?= $ticketsHoyBadge ?? 0 ?>
                    </span>
                </div>
                <div class="d-flex text-white align-items-center border-start ps-3">
                    <span class="me-3">Hola, <?= $_SESSION['nombre'] ?></span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Salir</a>
                </div>
            </div>
        </div>
    </nav>

    <script>
        // --- Integración Global Switch Servicio ---
        setTimeout(function() {
            var sw = document.getElementById('switchServicio');
            if(sw && !window.switchServicioLoaded) {
                window.switchServicioLoaded = true;
                sw.addEventListener('change', function() {
                    let estado = this.checked ? 1 : 0;
                    let label = document.getElementById('lblServicio');
                    fetch('cambiar_estado.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'activo=' + estado
                    })
                    .then(response => response.text())
                    .then(data => {
                        label.innerHTML = estado ? 'Servicio: ON <i class="bi bi-robot text-success"></i>' : 'Servicio: OFF <i class="bi bi-robot text-secondary"></i>';
                    });
                });
            }
        }, 100);
    </script>

    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <h2 class="text-secondary fw-bold mb-3 mb-md-0">
                <i class="bi bi-cpu text-info me-2"></i> Tickets Generados automáticamente
            </h2>
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center bg-white p-2 rounded shadow-sm border">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <select name="status" class="form-select text-secondary fw-bold" style="width: auto;">
                        <option value="default" <?= $filtro_status_raw == 'default' ? 'selected' : '' ?>>Estado Predeterminado</option>
                        <option value="exitosos" <?= $filtro_status_raw == 'exitosos' ? 'selected' : '' ?>>Sólo Exitosos</option>
                        <option value="fracasos" <?= $filtro_status_raw == 'fracasos' ? 'selected' : '' ?>>Con Errores / Mesa</option>
                        <option value="todos" <?= $filtro_status_raw == 'todos' ? 'selected' : '' ?>>Todos</option>
                    </select>

                    <select name="tipo" class="form-select text-secondary" style="width: auto;">
                        <option value="">Todos los Tipos</option>
                        <?php foreach($metricas_tipos as $mt): ?>
                            <option value="<?= htmlspecialchars($mt['servicio']) ?>" <?= $filtro_tipo == $mt['servicio'] ? 'selected' : '' ?>><?= htmlspecialchars($mt['servicio']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="filtro" class="form-select text-secondary fw-bold" onchange="toggleFechas(this.value)" style="width: auto;">
                        <option value="todos" <?= $filtro_actual == 'todos' ? 'selected' : '' ?>>Histórico Completo</option>
                        <option value="semana" <?= $filtro_actual == 'semana' ? 'selected' : '' ?>>Esta Semana</option>
                        <option value="mes" <?= $filtro_actual == 'mes' ? 'selected' : '' ?>>Este Mes</option>
                        <option value="custom" <?= $filtro_actual == 'custom' ? 'selected' : '' ?>>Por Fechas</option>
                    </select>
                </div>
                
                <div id="fechas-custom" class="<?= $filtro_actual == 'custom' ? 'd-flex' : 'd-none' ?> gap-2 align-items-center">
                    <input type="date" name="inicio" class="form-control" value="<?= htmlspecialchars($_GET['inicio'] ?? '') ?>">
                    <span class="text-muted">a</span>
                    <input type="date" name="fin" class="form-control" value="<?= htmlspecialchars($_GET['fin'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill"></i> Filtrar</button>
            </form>
        </div>

        <!-- Cards -->
        <div class="row mb-5">
            <div class="col-md-4">
                <div class="card card-stat border-primary shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size: 0.8rem; letter-spacing: 1px;">Total API POSTs</h6>
                                <h1 class="mb-0 fw-bold text-dark display-5"><?= $total ?></h1>
                            </div>
                            <div class="fs-1 text-primary opacity-50"><i class="bi bi-cloud-arrow-up"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-stat border-success shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size: 0.8rem; letter-spacing: 1px;">Tickets Creados y cerrados</h6>
                                <h1 class="mb-0 fw-bold text-success display-5"><?= $exito ?></h1>
                            </div>
                            <div class="fs-1 text-success opacity-50"><i class="bi bi-check-all"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-stat border-warning shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size: 0.8rem; letter-spacing: 1px;">TicketsCreados</h6>
                                <h1 class="mb-0 fw-bold text-warning display-5"><?= $error ?></h1>
                            </div>
                            <div class="fs-1 text-warning opacity-50"><i class="bi bi-envelope-open"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Métrica General Desglose de Servicios -->
        <?php if(!empty($metricas_tipos)): ?>
        <div class="mb-5 d-flex gap-2 flex-wrap">
            <span class="fw-bold fs-5 me-2 text-muted">Métricas de Servicios: </span>
            <?php foreach($metricas_tipos as $mt): ?>
                <span class="badge bg-dark rounded-pill fs-6 py-2 px-3 shadow-sm border border-secondary">
                    <?= htmlspecialchars($mt['servicio']) ?>: <span class="text-info fw-bold ms-1"><?= $mt['cantidad'] ?></span>
                </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Tabla -->
        <div class="card shadow-sm mb-5 border-0 rounded-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaAuto" class="table table-hover align-middle w-100">
                        <thead class="table-dark">
                            <tr>
                                <th># ID</th>
                                <th>Fecha</th>
                                <th>Remitente</th>
                                <th>Usuario</th>
                                <th class="text-center">Tipo Solicitud</th>
                                <th class="text-center">Plantilla ID</th>
                                <th>Descripción</th>
                                <th class="text-center">Acción (Req)</th>
                                <th class="text-center">TCK Servicedesk</th>
                                <th class="text-center">Resolución</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                                <tr>
                                    <td class="text-muted fw-bold" data-sort="<?= $log['id'] ?>">#<?= $log['id'] ?></td>
                                    <td data-sort="<?= strtotime($log['fecha_creacion']) ?>"><?= date('d/m/Y h:i A', strtotime($log['fecha_creacion'])) ?></td>
                                    <td><?= htmlspecialchars($log['correo'] ?? 'N/A') ?></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($log['nombre_solicitante'] ?? 'N/A') ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary border">
                                            <?= htmlspecialchars($log['tipo_solicitud_desc'] ?? 'General') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($log['id_plantilla_origen'] ?? 'None') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= nl2br(htmlspecialchars($log['descripcion'] ?? '-')) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= ($log['accion'] === '2') ? 'bg-danger' : 'bg-info' ?>">
                                            <?= ($log['accion'] === '2') ? 'Creado y Cerrado' : 'Abierto' ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if($log['ticket_creado']): ?>
                                            <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill"><i class="bi bi-ticket-fill me-1"></i> <?= $log['ticket_creado'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted fw-bold text-danger">Falla API</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if($log['status_proceso'] === 'Creado y cerrado automaticamente'): ?>
                                            <span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-circle"></i> Resulto Directo</span>
                                        <?php elseif($log['status_proceso'] === 'Generado automaticamente y resuelto por agente'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3"><i class="bi bi-hourglass-split"></i> Mesa de Ayuda</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3"><i class="bi bi-x-circle"></i> Error</span>
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
        function toggleFechas(val) {
            if(val === 'custom') {
                $('#fechas-custom').removeClass('d-none').addClass('d-flex');
            } else {
                $('#fechas-custom').removeClass('d-flex').addClass('d-none');
            }
        }

        $(document).ready(function() {
            $('#tablaAuto').DataTable({
                language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                order: [[ 0, "desc" ]],
                pageLength: 25,
                dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
            });
        });
    </script>
</body>
</html>
