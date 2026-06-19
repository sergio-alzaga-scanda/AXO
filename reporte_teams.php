<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
require_once 'db.php';
require_once 'funciones.php';

// Manejo de actualización de contraseña RPA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_rpa_password') {
    $nueva_pw = $_POST['pw_rpa'] ?? '';
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS configuracion_rpa (id INT PRIMARY KEY, rpa_password VARCHAR(255))");
        $stmt = $conn->prepare("INSERT INTO configuracion_rpa (id, rpa_password) VALUES (1, ?) ON DUPLICATE KEY UPDATE rpa_password = ?");
        $stmt->execute([$nueva_pw, $nueva_pw]);
        header("Location: reporte_teams.php?msg=rpa_pw_updated");
        exit;
    } catch (Exception $e) {}
}

// Obtener contraseña RPA actual
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS configuracion_rpa (id INT PRIMARY KEY, rpa_password VARCHAR(255))");
    $stmtRpa = $conn->query("SELECT rpa_password FROM configuracion_rpa WHERE id = 1");
    $rpaData = $stmtRpa->fetch(PDO::FETCH_ASSOC);
    $rpa_password_actual = $rpaData ? $rpaData['rpa_password'] : '';
} catch (Exception $e) {
    $rpa_password_actual = '';
}


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

    // Filtros Inteligentes
    $filtro_tipo = $_GET['tipo'] ?? '';
    // Status predeterminado: Si no hay tipo, mostrar 'exitosos'. Si busca por tipo, mostrar 'todos' para que vea los errores asociados.
    $filtro_status_raw = $_GET['status'] ?? 'todos';
    
    if ($filtro_status_raw === 'default') {
        $filtro_status = empty($filtro_tipo) ? 'exitosos' : 'todos';
    } else {
        $filtro_status = $filtro_status_raw;
    }

    // Filtros de Fecha
    $filtro_fecha = $_GET['fecha_filtro'] ?? 'todos';
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

    $condiciones_base = [];
    $params = [];

    if (!empty($filtro_tipo)) {
        $condiciones_base[] = "tipo_solicitud = :tipo";
        $params[':tipo'] = $filtro_tipo;
    }

    if ($filtro_fecha === 'semana') {
        $d = new DateTime();
        $d->setISODate((int)$d->format('o'), (int)$d->format('W'), 1); // Monday
        $fi = $d->format('Y-m-d') . ' 00:00:00';
        $d->modify('+6 days'); // Sunday
        $ff = $d->format('Y-m-d') . ' 23:59:59';
        
        $condiciones_base[] = "fecha_creacion >= :fecha_ini";
        $condiciones_base[] = "fecha_creacion <= :fecha_fin";
        $params[':fecha_ini'] = $fi;
        $params[':fecha_fin'] = $ff;
    } elseif ($filtro_fecha === 'mes') {
        $fi = date('Y-m-01') . ' 00:00:00';
        $ff = date('Y-m-t') . ' 23:59:59';
        
        $condiciones_base[] = "fecha_creacion >= :fecha_ini";
        $condiciones_base[] = "fecha_creacion <= :fecha_fin";
        $params[':fecha_ini'] = $fi;
        $params[':fecha_fin'] = $ff;
    } elseif ($filtro_fecha === 'rango') {
        if (!empty($fecha_inicio)) {
            $condiciones_base[] = "fecha_creacion >= :fecha_ini";
            $params[':fecha_ini'] = $fecha_inicio . ' 00:00:00';
        }
        if (!empty($fecha_fin)) {
            $condiciones_base[] = "fecha_creacion <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
    }

    // Registros para la tabla SÍ aplican el filtro de status
    $condiciones_tabla = $condiciones_base;
    if ($filtro_status === 'exitosos') {
        $condiciones_tabla[] = "status_proceso IN ('Éxito', 'Correcto')";
    } elseif ($filtro_status === 'transferidos') {
        $condiciones_tabla[] = "error_detalle = 'Falló el RPA porque los datos no corresponden. Se asignó la atención manual.'";
    } elseif ($filtro_status === 'errores_usuario') {
        $condiciones_tabla[] = "status_proceso = 'Error' AND (error_detalle IS NULL OR error_detalle != 'Falló el RPA porque los datos no corresponden. Se asignó la atención manual.')";
    } elseif ($filtro_status === 'en_espera') {
        $condiciones_tabla[] = "status_proceso = 'en espera'";
    } elseif ($filtro_status === 'fracasos') {
        $condiciones_tabla[] = "status_proceso NOT IN ('Éxito', 'Correcto')";
    }

    $donde_stats = "";
    if (count($condiciones_tabla) > 0) {
        $donde_stats = " WHERE " . implode(" AND ", $condiciones_tabla);
    }

    // Los Stats (tarjetas numéricas superiores) respectan el filtro_status
    $stmtStats = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status_proceso IN ('Éxito', 'Correcto') THEN 1 ELSE 0 END) as exito,
            SUM(CASE WHEN error_detalle = 'Falló el RPA porque los datos no corresponden. Se asignó la atención manual.' THEN 1 ELSE 0 END) as transferido,
            SUM(CASE WHEN status_proceso NOT IN ('Éxito', 'Correcto') AND status_proceso != 'en espera' AND (error_detalle IS NULL OR error_detalle != 'Falló el RPA porque los datos no corresponden. Se asignó la atención manual.') THEN 1 ELSE 0 END) as error_usr
        FROM log_tickets_teams
        $donde_stats
    ");
    $stmtStats->execute($params);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    $total = $stats['total'] ?? 0;
    $exito = $stats['exito'] ?? 0;
    $transferido = $stats['transferido'] ?? 0;
    $error_usr = $stats['error_usr'] ?? 0;

    $donde_tabla = "";
    if (count($condiciones_tabla) > 0) {
        // En el select l.* usamos el alias 'l.' para las columnas de condición
        $condiciones_con_alias = array_map(function($cond) { return "l." . $cond; }, $condiciones_tabla);
        $donde_tabla = " WHERE " . implode(" AND ", $condiciones_con_alias);
    }

    $stmtLogs = $conn->prepare("
        SELECT l.*, COALESCE(c.nombre, l.tipo_solicitud) as tipo_solicitud_desc 
        FROM log_tickets_teams l 
        LEFT JOIN catalogo_tipo_solicitud c ON l.tipo_solicitud = c.id 
        $donde_tabla
        ORDER BY l.fecha_creacion DESC
    ");
    $stmtLogs->execute($params);
    $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // Métricas por tipo de servicio
    $condiciones_tipos = $condiciones_tabla;
    $condiciones_tipos[] = "tipo_solicitud IS NOT NULL AND tipo_solicitud != ''";
    $condiciones_tipos_con_alias = array_map(function($cond) { return "l." . $cond; }, $condiciones_tipos);
    $donde_tipos = " WHERE " . implode(" AND ", $condiciones_tipos_con_alias);

    $stmtTipos = $conn->prepare("
        SELECT COALESCE(c.nombre, l.tipo_solicitud) as servicio, COUNT(l.id) as cantidad 
        FROM log_tickets_teams l
        LEFT JOIN catalogo_tipo_solicitud c ON l.tipo_solicitud = CAST(c.id AS CHAR)
        $donde_tipos 
        GROUP BY 1
    ");
    $stmtTipos->execute($params);
    $metricas_tipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

    // Métricas por canal de origen
    $condiciones_canales = $condiciones_tabla;
    $condiciones_canales[] = "canal IS NOT NULL AND canal != ''";
    $condiciones_canales_con_alias = array_map(function($cond) { return "l." . $cond; }, $condiciones_canales);
    $donde_canales = " WHERE " . implode(" AND ", $condiciones_canales_con_alias);

    $stmtCanales = $conn->prepare("
        SELECT 
            CASE 
                WHEN l.canal = '1' OR l.canal = '11' THEN 'Whatsapp' 
                WHEN l.canal = '2' THEN 'Teams' 
                WHEN l.canal = '3' THEN 'API'
                ELSE l.canal 
            END as canal_desc, 
            COUNT(l.id) as cantidad 
        FROM log_tickets_teams l
        $donde_canales 
        GROUP BY 1
    ");
    $stmtCanales->execute($params);
    $metricas_canales = $stmtCanales->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $total = $exito = $transferido = $error_usr = 0;
    $logs = [];
    $metricas_tipos = [];
    $metricas_canales = [];
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
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .card-stat { border-left: 5px solid; transition: all 0.3s ease; border-radius: 10px; }
        .card-stat:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
        .border-primary { border-left-color: #0d6efd !important; }
        .border-success { border-left-color: #198754 !important; }
        .border-warning { border-left-color: #ffc107 !important; }
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
                    <li class="nav-item"><a class="nav-link " href="dashboard.php">Técnicos <i class="bi bi-people"></i></a></li>
                    <li class="nav-item"><a class="nav-link " href="plantillas.php">Plantillas <i class="bi bi-file-earmark-text"></i></a></li>
                    <li class="nav-item"><a class="nav-link " href="log_general.php">Auditoría <i class="bi bi-shield-check"></i></a></li>
                    <li class="nav-item"><a class="nav-link " href="reportes.php">Reportes <i class="bi bi-bar-chart-line"></i></a></li>
                    <li class="nav-item"><a class="nav-link active" href="reporte_teams.php">Bot Teams <i class="bi bi-robot"></i></a></li>
                    <li class="nav-item"><a class="nav-link " href="reporte_automatizados.php">Automatizados <i class="bi bi-cpu"></i></a></li>
                </ul>
                
                <!-- Reloj del Sistema Global -->
                <div class="d-flex align-items-center text-white me-4 br-print-hide">
                    <i class="bi bi-clock me-2 text-info"></i>
                    <span id="relojSistema" class="fw-bold" style="font-family: monospace; font-size: 1.1rem; letter-spacing: 1px;">00:00:00</span>
                </div>

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
                    <span class="me-3">Hola, <?= $_SESSION['nombre'] ?? 'Usuario' ?></span>
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

            // --- Integración Global Reloj ---
            if(!window.relojLoaded) {
                window.relojLoaded = true;
                function actualizarReloj() {
                    const ahora = new Date();
                    const horas = String(ahora.getHours()).padStart(2, '0');
                    const minutos = String(ahora.getMinutes()).padStart(2, '0');
                    const segundos = String(ahora.getSeconds()).padStart(2, '0');
                    const spanReloj = document.getElementById('relojSistema');
                    if(spanReloj) spanReloj.innerText = `${horas}:${minutos}:${segundos}`;
                }
                setInterval(actualizarReloj, 1000);
                actualizarReloj();
            }
        }, 100);
    </script>

    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-4 gap-3">
            <div class="d-flex align-items-center gap-3">
                <h2 class="text-secondary fw-bold mb-0">
                    <i class="bi bi-robot text-primary me-2"></i> Peticiones Automatizadas (Teams)
                </h2>
                <button class="btn btn-outline-dark btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalRpaConfig">
                    <i class="bi bi-key-fill text-warning"></i> Configurar Contraseña RPA
                </button>
            </div>
            
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center bg-white p-2 rounded shadow-sm border">
                <div>
                    <select name="status" class="form-select text-secondary fw-bold">
                        <option value="default" <?= $filtro_status_raw == 'default' ? 'selected' : '' ?>>Estado Predeterminado (Inteligente)</option>
                        <option value="exitosos" <?= $filtro_status_raw == 'exitosos' ? 'selected' : '' ?>>Sólo Exitosos</option>
                        <option value="transferidos" <?= $filtro_status_raw == 'transferidos' ? 'selected' : '' ?>>Sólo Transferidos a Agente</option>
                        <option value="errores_usuario" <?= $filtro_status_raw == 'errores_usuario' ? 'selected' : '' ?>>Sólo Errores de Usuario</option>
                        <option value="en_espera" <?= $filtro_status_raw == 'en_espera' ? 'selected' : '' ?>>Sólo Pendientes (En Espera)</option>
                        <option value="fracasos" <?= $filtro_status_raw == 'fracasos' ? 'selected' : '' ?>>Cualquier Fallo (Traspaso / Error)</option>
                        <option value="todos" <?= $filtro_status_raw == 'todos' ? 'selected' : '' ?>>Ver Todos los Estados</option>
                    </select>
                </div>
                <div>
                    <select name="tipo" class="form-select text-secondary">
                        <option value="">Cualquier Tipo de Servicio</option>
                        <?php foreach($metricas_tipos as $mt): ?>
                            <option value="<?= htmlspecialchars($mt['servicio']) ?>" <?= $filtro_tipo == $mt['servicio'] || $filtro_tipo == array_search($mt, $metricas_tipos) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mt['servicio']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <select name="fecha_filtro" id="fecha_filtro" class="form-select text-secondary">
                        <option value="todos" <?= $filtro_fecha == 'todos' ? 'selected' : '' ?>>Todas las fechas</option>
                        <option value="semana" <?= $filtro_fecha == 'semana' ? 'selected' : '' ?>>Esta semana</option>
                        <option value="mes" <?= $filtro_fecha == 'mes' ? 'selected' : '' ?>>Este mes</option>
                        <option value="rango" <?= $filtro_fecha == 'rango' ? 'selected' : '' ?>>Rango de fechas</option>
                    </select>
                </div>
                <div id="rango_fechas_container" class="d-none gap-2 align-items-center">
                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control text-secondary" value="<?= htmlspecialchars($fecha_inicio) ?>">
                    <span class="text-secondary">a</span>
                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control text-secondary" value="<?= htmlspecialchars($fecha_fin) ?>">
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel-fill"></i> Filtrar</button>
                <a href="reporte_teams.php" class="btn btn-outline-secondary"><i class="bi bi-x-circle"></i> Limpiar</a>
            </form>
        </div>

        <!-- Cards -->
        <div class="row mb-5 g-3">
            <div class="col-md-3">
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
            <div class="col-md-3">
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
            <div class="col-md-3">
                <div class="card card-stat border-warning shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size: 0.8rem; letter-spacing: 1px;">Traspaso a Agente</h6>
                                <h1 class="mb-0 fw-bold text-warning display-5"><?= $transferido ?></h1>
                                <small class="text-muted fw-bold">Atención manual</small>
                            </div>
                            <div class="fs-1 text-warning opacity-50"><i class="bi bi-headset"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat border-danger shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1 fw-bold" style="font-size: 0.8rem; letter-spacing: 1px;">Errores de Creación</h6>
                                <h1 class="mb-0 fw-bold text-danger display-5"><?= $error_usr ?></h1>
                                <small class="text-muted fw-bold">Datos inválidos / otros</small>
                            </div>
                            <div class="fs-1 text-danger opacity-50"><i class="bi bi-exclamation-triangle"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Métrica General Desglose de Servicios y Canales -->
        <div class="d-flex flex-column gap-3 mb-5 bg-white p-4 rounded-4 shadow-sm border">
            <?php if(!empty($metricas_tipos)): ?>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="fw-bold fs-5 me-2 text-secondary"><i class="bi bi-graph-up-arrow me-1"></i> Servicios: </span>
                <?php foreach($metricas_tipos as $mt): ?>
                    <span class="badge bg-dark rounded-pill fs-6 py-2 px-3 shadow-sm border border-secondary">
                        <?= htmlspecialchars($mt['servicio']) ?>: <span class="text-info fw-bold ms-1"><?= $mt['cantidad'] ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if(!empty($metricas_canales)): ?>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="fw-bold fs-5 me-2 text-secondary"><i class="bi bi-share-fill me-1"></i> Canales de Origen: </span>
                <?php foreach($metricas_canales as $mc): ?>
                    <?php 
                        $canal_name = $mc['canal_desc'];
                        $canal_lower = strtolower($canal_name);
                        $badge_class = 'bg-info text-dark';
                        $icon = 'chat-left-text';
                        if ($canal_lower === 'whatsapp') {
                            $badge_class = 'bg-success text-white';
                            $icon = 'whatsapp';
                        } elseif ($canal_lower === 'teams') {
                            $badge_class = 'bg-primary text-white';
                            $icon = 'microsoft-teams';
                        } elseif ($canal_lower === 'api') {
                            $badge_class = 'bg-secondary text-white';
                            $icon = 'code-slash';
                        }
                    ?>
                    <span class="badge <?= $badge_class ?> rounded-pill fs-6 py-2 px-3 shadow border">
                        <i class="bi bi-<?= $icon ?>"></i> <?= htmlspecialchars($canal_name) ?>: <span class="fw-bold ms-1"><?= $mc['cantidad'] ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
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
                                <th class="text-center">Canal</th>
                                <th class="text-center">Tipo</th>
                                <th class="text-center">Ticket Generado</th>
                                <th class="text-center">Estado del Proceso</th>
                                <th>Detalle de Atención</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                                <tr>
                                    <td class="text-muted fw-bold" data-sort="<?= $log['id'] ?>">#<?= $log['id'] ?></td>
                                    <td data-sort="<?= strtotime($log['fecha_creacion']) ?>"><?= date('d/m/Y h:i A', strtotime($log['fecha_creacion'])) ?></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($log['numero_usuario'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($log['correo'] ?? 'N/A') ?></td>
                                    <td class="text-center">
                                        <?php if(!empty($log['canal'])): ?>
                                            <?php 
                                                $canal_name = $log['canal'];
                                                if ($canal_name === '1' || $canal_name === '11') { $canal_name = 'Whatsapp'; }
                                                elseif ($canal_name === '2') { $canal_name = 'Teams'; }
                                                elseif ($canal_name === '3') { $canal_name = 'API'; }
                                                $canal_lower = strtolower($canal_name);
                                                $icon = 'chat-left-text';
                                                $badge_class = 'bg-info text-dark';
                                                if ($canal_lower === 'whatsapp') {
                                                    $icon = 'whatsapp';
                                                    $badge_class = 'bg-success text-white';
                                                } elseif ($canal_lower === 'teams') {
                                                    $icon = 'microsoft-teams';
                                                    $badge_class = 'bg-primary text-white';
                                                } elseif ($canal_lower === 'api') {
                                                    $icon = 'code-slash';
                                                    $badge_class = 'bg-secondary text-white';
                                                }
                                            ?>
                                            <span class="badge <?= $badge_class ?> border">
                                                <i class="bi bi-<?= $icon ?>"></i> <?= htmlspecialchars($canal_name) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary border">
                                            <?= htmlspecialchars($log['tipo_solicitud_desc'] ?? 'N/A') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if($log['ticket_creado']): ?>
                                            <span class="badge bg-primary px-3 py-2 fs-6 rounded-pill"><i class="bi bi-ticket-fill me-1"></i> <?= $log['ticket_creado'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php 
                                            $raw_status = trim($log['status_proceso'] ?? '');
                                            $lower_estatus = mb_strtolower($raw_status, 'UTF-8'); 
                                            $error_det = trim($log['error_detalle'] ?? '');
                                        ?>
                                        <?php if($lower_estatus === 'éxito' || $lower_estatus === 'correcto' || $lower_estatus === 'exito'): ?>
                                            <span class="badge bg-success rounded-pill px-3"><i class="bi bi-check-circle"></i> Éxito</span>
                                        <?php elseif($lower_estatus === 'en espera'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3"><i class="bi bi-hourglass-split"></i> En Espera</span>
                                        <?php elseif($error_det === 'Falló el RPA porque los datos no corresponden. Se asignó la atención manual.'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3"><i class="bi bi-headset"></i> Transferido con un agente</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3"><i class="bi bi-exclamation-triangle"></i> Error</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($log['error_detalle']): ?>
                                            <small class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($log['error_detalle']) ?></small>
                                        <?php elseif(strtolower($log['status_proceso']) === 'en espera'): ?>
                                            <small class="text-warning text-dark fw-bold"><i class="bi bi-clock-history"></i> Pendiente de resolución...</small>
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
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tablaTeams').DataTable({
                language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                order: [[ 0, "desc" ]],
                pageLength: 25,
                dom: '<"d-flex justify-content-between align-items-center mb-3"lBf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="bi bi-file-earmark-excel"></i> Exportar a Excel',
                        className: 'btn btn-success btn-sm'
                    }
                ]
            });

            // Lógica para mostrar/ocultar rango de fechas
            function toggleFechaRango() {
                if ($('#fecha_filtro').val() === 'rango') {
                    $('#rango_fechas_container').removeClass('d-none').addClass('d-flex');
                } else {
                    $('#rango_fechas_container').removeClass('d-flex').addClass('d-none');
                }
            }
            $('#fecha_filtro').on('change', toggleFechaRango);
            toggleFechaRango(); // Inicializar al cargar la página
        });
    </script>

    <!-- Modal RPA Password -->
    <div class="modal fade" id="modalRpaConfig" tabindex="-1" aria-labelledby="modalRpaConfigLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-dark text-white">
            <h5 class="modal-title" id="modalRpaConfigLabel"><i class="bi bi-key-fill text-warning"></i> Contraseña Única del Robot RPA</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form method="POST" action="reporte_teams.php">
              <div class="modal-body">
                <input type="hidden" name="action" value="update_rpa_password">
                <div class="mb-3">
                  <label for="pw_rpa" class="form-label fw-bold">Contraseña Actual del RPA</label>
                  <input type="text" class="form-control" id="pw_rpa" name="pw_rpa" value="<?= htmlspecialchars($rpa_password_actual) ?>" placeholder="Ingresa la contraseña del bot RPA">
                  <div class="form-text">Esta contraseña se enviará intacta a la API en el campo <code>pw_rpa</code> para que el robot la utilice. Es una contraseña única global para el bot. No afecta al password temporal de los tickets de los usuarios.</div>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Guardar Contraseña</button>
              </div>
          </form>
        </div>
      </div>
    </div>
</body>
</html>
