<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
require_once 'db.php';
require_once 'funciones.php';

actualizarEstadosTecnicos($conn);

// Consulta Estado del Servicio (API)
$stmtServ = $conn->query("SELECT activo FROM configuracion_servicio WHERE id = 1");
$servicioData = $stmtServ->fetch(PDO::FETCH_ASSOC);
$servicioActivo = $servicioData ? $servicioData['activo'] : 0;

// Tickets de HOY (Navbar)
$stmtHoy = $conn->query("SELECT COUNT(*) as total FROM tickets_asignados WHERE fecha_asignacion >= CURDATE()"); 
$ticketsHoyNavbar = $stmtHoy->fetch(PDO::FETCH_ASSOC)['total'];

// Tickets TOTALES (Navbar)
$stmtTotal = $conn->query("SELECT COUNT(*) as total FROM tickets_asignados"); 
$ticketsTotalNavbar = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

// --- Filtros de Fecha ---
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-d', strtotime('-7 days'));
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');

// Condición base para los queries (rango inclusivo por día completo)
$condicion_fecha = "fecha_asignacion >= :inicio AND fecha_asignacion <= :fin_completo";

// --- Consultas para Estadísticas ---

// Tickets Hoy
$ticketsHoy = $ticketsHoyNavbar;

// Tickets esta semana
$stmtSemana = $conn->query("SELECT COUNT(*) as total FROM tickets_asignados WHERE YEARWEEK(fecha_asignacion, 1) = YEARWEEK(CURDATE(), 1)");
$ticketsSemana = $stmtSemana->fetch(PDO::FETCH_ASSOC)['total'];

// Tickets este mes
$stmtMes = $conn->query("SELECT COUNT(*) as total FROM tickets_asignados WHERE MONTH(fecha_asignacion) = MONTH(CURDATE()) AND YEAR(fecha_asignacion) = YEAR(CURDATE())");
$ticketsMes = $stmtMes->fetch(PDO::FETCH_ASSOC)['total'];

// Preparar parámetros comunes
$params = [
    ':inicio' => $fecha_inicio . ' 00:00:00',
    ':fin_completo' => $fecha_fin . ' 23:59:59'
];

// Tickets por técnico (Pie)
$stmtTecnicos = $conn->prepare("SELECT usuario_tecnico as label, COUNT(*) as total FROM tickets_asignados WHERE $condicion_fecha AND usuario_tecnico IS NOT NULL AND usuario_tecnico != '' GROUP BY usuario_tecnico ORDER BY total DESC");
$stmtTecnicos->execute($params);
$dataTecnicos = $stmtTecnicos->fetchAll(PDO::FETCH_ASSOC);

// Tickets por día en el rango (Línea)
$stmtDias = $conn->prepare("SELECT DATE(fecha_asignacion) as label, COUNT(*) as total FROM tickets_asignados WHERE $condicion_fecha AND fecha_asignacion IS NOT NULL GROUP BY DATE(fecha_asignacion) ORDER BY label ASC");
$stmtDias->execute($params);
$dataDias = $stmtDias->fetchAll(PDO::FETCH_ASSOC);

// Top 5 plantillas (Doughnut)
$stmtTempletes = $conn->prepare("SELECT templete as label, COUNT(*) as total FROM tickets_asignados WHERE $condicion_fecha AND templete IS NOT NULL AND templete != '' GROUP BY templete ORDER BY total DESC LIMIT 5");
$stmtTempletes->execute($params);
$dataTempletes = $stmtTempletes->fetchAll(PDO::FETCH_ASSOC);

// Tickets por grupo (Barra Horizontal)
$stmtGrupos = $conn->prepare("SELECT grupo as label, COUNT(*) as total FROM tickets_asignados WHERE $condicion_fecha AND grupo IS NOT NULL AND grupo != '' GROUP BY grupo ORDER BY total DESC LIMIT 15");
$stmtGrupos->execute($params);
$dataGrupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

// Total en el rango seleccionado
$stmtTotalRango = $conn->prepare("SELECT COUNT(*) as total FROM tickets_asignados WHERE $condicion_fecha");
$stmtTotalRango->execute($params);
$ticketsTotalRango = $stmtTotalRango->fetch(PDO::FETCH_ASSOC)['total'];

// Insights adicionales del Rango
$diasDiff = max(1, (strtotime($fecha_fin) - strtotime($fecha_inicio)) / (60 * 60 * 24) + 1);
$promedioDiario = round($ticketsTotalRango / $diasDiff, 1);
$tecnicoTop = count($dataTecnicos) > 0 ? $dataTecnicos[0]['label'] : 'N/A';
$grupoTop = count($dataGrupos) > 0 ? $dataGrupos[0]['label'] : 'N/A';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes y Estadísticas - AXO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
    <style>
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        /* Personalizar scrollbar de la página si es necesario */
        body { background-color: #f8f9fa; }

        /* --- Optimizaciones para Impresión --- */
        @media print {
            body { 
                background-color: white !important; 
                -webkit-print-color-adjust: exact; 
                margin: 0 !important;
                padding: 0 !important;
            }
            /* Ocultar elementos no deseados en el PDF */
            .navbar, form, .btn, .br-print-hide { 
                display: none !important; 
            }
            /* Ajustar salto de página y márgenes */
            .card { 
                border: 1px solid #dee2e6 !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-bottom: 1.5rem !important;
                width: 100% !important;
            }
            .container-fluid {
                padding: 0 !important;
                max-width: 100% !important;
            }
            /* Convertir filas a bloques para evitar que Flexbox intente apretar el ancho */
            .row {
                display: block !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            /* Forzar el ancho de TODAS las columnas a 100% en impresión para que caigan verticalmente sin cortarse ni encimarse */
            .col-md-3, .col-md-4, .col-md-5, .col-md-7, .col-md-8 { 
                width: 100% !important; 
                max-width: 100% !important;
                flex: none !important;
                display: block !important;
                padding: 0 !important;
                margin-bottom: 1rem !important;
            }
            /* Ajustar los contenedores de los gráficos */
            .chart-container {
                height: 350px !important; 
                width: 100% !important;
            }
            
            @page {
                size: portrait; /* Retrato asegura que el contenido estructurado caiga hacia abajo orgánicamente */
                margin: 15mm;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
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
                    <li class="nav-item"><a class="nav-link" href="log_general.php">Auditoría</a></li>
                    <li class="nav-item"><a class="nav-link active" href="reportes.php">Reportes <i class="bi bi-bar-chart-line"></i></a></li>
                </ul>
                
                <!-- Reloj del Sistema -->
                <div class="d-flex align-items-center text-white me-4 br-print-hide">
                    <i class="bi bi-clock me-2 text-info"></i>
                    <span id="relojSistema" class="fw-bold" style="font-family: monospace; font-size: 1.1rem; letter-spacing: 1px;">00:00:00</span>
                </div>
                
                <div class="d-flex text-white me-4">
                    <div class="border px-3 py-1 rounded me-2 text-center">
                        <small class="d-block text-white-50" style="font-size: 0.75rem;">HOY</small>
                        <strong><?= $ticketsHoyNavbar ?></strong> <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="border px-3 py-1 rounded text-center bg-secondary bg-opacity-25">
                        <small class="d-block text-white-50" style="font-size: 0.75rem;">TOTAL HISTÓRICO</small>
                        <strong><?= $ticketsTotalNavbar ?></strong> <i class="bi bi-database"></i>
                    </div>
                </div>

                <div class="d-flex text-white align-items-center border-start ps-3">
                    <span class="me-3">Hola, <?= $_SESSION['nombre'] ?></span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Salir</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-graph-up-arrow text-primary"></i> Módulo de Reportes y Estadísticas</h2>
            <div>
                <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir Reporte</button>
            </div>
        </div>

        <!-- Filtro de Fechas -->
        <div class="card shadow-sm border-0 mb-4 bg-white">
            <div class="card-body py-2">
                <form method="GET" action="reportes.php" class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label for="fecha_inicio" class="col-form-label fw-bold text-muted small">Desde:</label>
                    </div>
                    <div class="col-auto">
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control form-control-sm" value="<?= $fecha_inicio ?>">
                    </div>
                    <div class="col-auto">
                        <label for="fecha_fin" class="col-form-label fw-bold text-muted small">Hasta:</label>
                    </div>
                    <div class="col-auto">
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control form-control-sm" value="<?= $fecha_fin ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filtrar</button>
                        <a href="reportes.php" class="btn btn-outline-secondary btn-sm ms-1">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tarjetas de Resumen Globales -->
        <h5 class="fw-bold text-secondary mb-3 mt-4"><i class="bi bi-globe"></i> Resumen Global General</h5>
        <div class="row mb-5 g-3">
            <div class="col-md-3">
                <div class="card text-white bg-primary bg-gradient shadow rounded-4 border-0 h-100">
                    <div class="card-body position-relative overflow-hidden p-4">
                        <h6 class="card-title text-uppercase fw-semibold mb-1 opacity-75">Tickets Hoy</h6>
                        <h2 class="display-5 fw-bold mb-0"><?= $ticketsHoy ?></h2>
                        <i class="bi bi-calendar-day position-absolute top-50 start-100 translate-middle opacity-25" style="font-size: 6rem; margin-left: -30px;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success bg-gradient shadow rounded-4 border-0 h-100">
                    <div class="card-body position-relative overflow-hidden p-4">
                        <h6 class="card-title text-uppercase fw-semibold mb-1 opacity-75">Tickets Esta Semana</h6>
                        <h2 class="display-5 fw-bold mb-0"><?= $ticketsSemana ?></h2>
                        <i class="bi bi-calendar-week position-absolute top-50 start-100 translate-middle opacity-25" style="font-size: 6rem; margin-left: -30px;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-dark bg-warning bg-gradient shadow rounded-4 border-0 h-100">
                    <div class="card-body position-relative overflow-hidden p-4">
                        <h6 class="card-title text-uppercase fw-semibold mb-1 opacity-75">Tickets Este Mes</h6>
                        <h2 class="display-5 fw-bold mb-0"><?= $ticketsMes ?></h2>
                        <i class="bi bi-calendar-month position-absolute top-50 start-100 translate-middle opacity-25" style="font-size: 6rem; margin-left: -30px;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-dark bg-gradient shadow rounded-4 border-0 h-100">
                    <div class="card-body position-relative overflow-hidden p-4">
                        <h6 class="card-title text-uppercase fw-semibold mb-1 opacity-75">Total Histórico</h6>
                        <h2 class="display-5 fw-bold mb-0"><?= $ticketsTotalNavbar ?></h2>
                        <i class="bi bi-database position-absolute top-50 start-100 translate-middle opacity-25" style="font-size: 6rem; margin-left: -30px;"></i>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4 opacity-10">

        <!-- Insights de Rango Seleccionado -->
        <h5 class="fw-bold text-secondary mb-3"><i class="bi bi-search"></i> Insights del Rango Seleccionado <span class="badge bg-light text-dark fw-normal border ms-2"><?= $fecha_inicio ?> al <?= $fecha_fin ?></span></h5>
        <div class="row mb-4 g-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-info">
                    <div class="card-body d-flex align-items-center">
                        <div class="flex-shrink-0 me-3 text-info">
                            <i class="bi bi-funnel-fill pb-1" style="font-size: 2.2rem;"></i>
                        </div>
                        <div>
                            <p class="text-muted mb-0 fw-semibold text-uppercase" style="font-size: 0.75rem;">Tickets en Rango</p>
                            <h3 class="fw-bold mb-0 text-dark"><?= $ticketsTotalRango ?></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-danger">
                    <div class="card-body d-flex align-items-center">
                        <div class="flex-shrink-0 me-3 text-danger">
                            <i class="bi bi-speedometer2 pb-1" style="font-size: 2.2rem;"></i>
                        </div>
                        <div>
                            <p class="text-muted mb-0 fw-semibold text-uppercase" style="font-size: 0.75rem;">Promedio Diario</p>
                            <h3 class="fw-bold mb-0 text-dark"><?= $promedioDiario ?> <small class="text-muted fs-6 fw-normal">/día</small></h3>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-primary">
                    <div class="card-body d-flex align-items-center">
                        <div class="flex-shrink-0 me-3 text-primary">
                            <i class="bi bi-star-fill pb-1" style="font-size: 2.2rem;"></i>
                        </div>
                        <div style="min-width: 0;">
                            <p class="text-muted mb-0 fw-semibold text-uppercase" style="font-size: 0.75rem;">Top Técnico</p>
                            <h5 class="fw-bold mb-0 text-dark text-truncate pt-1" title="<?= htmlspecialchars($tecnicoTop) ?>"><?= $tecnicoTop ?></h5>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 h-100 border-start border-4 border-success">
                    <div class="card-body d-flex align-items-center">
                        <div class="flex-shrink-0 me-3 text-success">
                            <i class="bi bi-collection-fill pb-1" style="font-size: 2.2rem;"></i>
                        </div>
                        <div style="min-width: 0;">
                            <p class="text-muted mb-0 fw-semibold text-uppercase" style="font-size: 0.75rem;">Grupo Frecuente</p>
                            <h5 class="fw-bold mb-0 text-dark text-truncate pt-1" title="<?= htmlspecialchars($grupoTop) ?>"><?= $grupoTop ?></h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4 g-4">
            <!-- Gráfico de Línea: Últimos 7 Días -->
            <div class="col-md-8">
                <div class="card shadow rounded-4 border-0 h-100">
                    <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-activity text-primary me-2"></i> Evolución de Asignaciones <span class="text-muted fw-normal fs-6">(Rango)</span></h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="chart-container">
                            <canvas id="chartDias"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Gráfico de Pastel: Por Técnico -->
            <div class="col-md-4">
                <div class="card shadow rounded-4 border-0 h-100">
                    <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-person-badge text-info me-2"></i> Tickets por Técnico</h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="chart-container">
                            <canvas id="chartTecnicos"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Gráfico de Barras: Por Grupo -->
            <div class="col-md-7">
                <div class="card shadow rounded-4 border-0 h-100">
                    <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-collection text-success me-2"></i> Top Grupos</h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="chart-container">
                            <canvas id="chartGrupos"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Gráfico Doughnut: Top Plantillas -->
            <div class="col-md-5">
                <div class="card shadow rounded-4 border-0 h-100">
                    <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4">
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-text text-warning me-2"></i> Top Plantillas Usadas</h5>
                    </div>
                    <div class="card-body px-4 pb-4">
                        <div class="chart-container">
                            <canvas id="chartTempletes"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detalles del Día -->
    <div class="modal fade" id="modalTicketsDia" tabindex="-1" aria-labelledby="modalTicketsDiaLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
          <div class="modal-header bg-primary text-white br-print-hide">
            <h5 class="modal-title" id="modalTicketsDiaLabel"><i class="bi bi-calendar2-day text-white me-2"></i> Tickets del <span id="spanFechaDetalle" class="fw-bold"></span></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body bg-light p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 text-center align-middle" style="font-size: 0.9rem;">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th>Ticket ID</th>
                            <th>Técnico</th>
                            <th>Grupo</th>
                            <th>Plantilla</th>
                            <th>Fecha/Hora Asignación</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyTicketsDia">
                        <!-- Llenado dinámico -->
                    </tbody>
                </table>
            </div>
          </div>
          <div class="modal-footer br-print-hide bg-white">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pasar datos a JavaScript de manera segura -->
    <script>
        const dataDias = <?= json_encode($dataDias) ?>;
        const dataTecnicos = <?= json_encode($dataTecnicos) ?>;
        const dataGrupos = <?= json_encode($dataGrupos) ?>;
        const dataTempletes = <?= json_encode($dataTempletes) ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Switch Servicio (Navbar) ---
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

        // --- Configuración Global de Chart.js ---
        Chart.register(ChartDataLabels);
        Chart.defaults.font.family = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
        Chart.defaults.color = '#6c757d';
        Chart.defaults.plugins.datalabels.display = false; // Ocultos por defecto

        // --- Eventos de Impresión (Mostrar números exactos solo al imprimir) ---
        window.addEventListener('beforeprint', () => {
            Chart.defaults.plugins.datalabels.display = true;
            for(let id in Chart.instances) { Chart.instances[id].update(); }
        });
        window.addEventListener('afterprint', () => {
            Chart.defaults.plugins.datalabels.display = false;
            for(let id in Chart.instances) { Chart.instances[id].update(); }
        });

        // --- Paleta de Colores ---
        const colors = [
            'rgba(13, 110, 253, 0.7)',  // Primary
            'rgba(25, 135, 84, 0.7)',   // Success
            'rgba(13, 202, 240, 0.7)',  // Info
            'rgba(255, 193, 7, 0.7)',   // Warning
            'rgba(220, 53, 69, 0.7)',   // Danger
            'rgba(102, 16, 242, 0.7)',  // Purple
            'rgba(214, 51, 132, 0.7)',  // Pink
            'rgba(253, 126, 20, 0.7)',  // Orange
            'rgba(32, 201, 151, 0.7)',  // Teal
            'rgba(108, 117, 125, 0.7)'  // Secondary
        ];
        const borderColors = colors.map(c => c.replace('0.7', '1'));

        // 1. Gráfico de Línea (Días)
        const ctxDias = document.getElementById('chartDias').getContext('2d');
        // Crear un gradiente para la línea
        let gradientLine = ctxDias.createLinearGradient(0, 0, 0, 400);
        gradientLine.addColorStop(0, 'rgba(13, 110, 253, 0.5)'); // primary
        gradientLine.addColorStop(1, 'rgba(13, 110, 253, 0.05)');

        new Chart(ctxDias, {
            type: 'line',
            data: {
                labels: dataDias.map(d => d.label),
                datasets: [{
                    label: 'Tickets',
                    data: dataDias.map(d => d.total),
                    borderColor: 'rgba(13, 110, 253, 1)',
                    backgroundColor: gradientLine,
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgba(13, 110, 253, 1)',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4 // Curva más suave
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false },
                    datalabels: { display: false } // Ocultar en gráfica de línea para no saturar
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { borderDash: [5, 5] } },
                    x: { grid: { display: false } }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                onClick: (e, activeElements) => {
                    if (activeElements.length > 0) {
                        const idx = activeElements[0].index;
                        const fechaClick = dataDias[idx].label;
                        mostrarTicketsDia(fechaClick);
                    }
                }
            }
        });

        // 2. Gráfico de Pastel (Técnicos)
        new Chart(document.getElementById('chartTecnicos'), {
            type: 'pie',
            data: {
                labels: dataTecnicos.map(d => d.label || 'Sin asignar'),
                datasets: [{
                    data: dataTecnicos.map(d => d.total),
                    backgroundColor: colors,
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'right', labels: { boxWidth: 12 } },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold', size: 14 },
                        formatter: (value) => value > 0 ? value : ''
                    }
                }
            }
        });

        // 3. Gráfico de Barras (Grupos) - Horizontal
        new Chart(document.getElementById('chartGrupos'), {
            type: 'bar',
            data: {
                labels: dataGrupos.map(d => d.label || 'Sin Grupo'),
                datasets: [{
                    label: 'Tickets por Grupo',
                    data: dataGrupos.map(d => d.total),
                    backgroundColor: 'rgba(13, 202, 240, 0.8)', // Info color
                    borderColor: 'rgba(13, 202, 240, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y', // Barra horizontal
                responsive: true,
                maintainAspectRatio: false,
                scales: { 
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { borderDash: [5, 5] } },
                    y: { grid: { display: false } }
                },
                plugins: { 
                    legend: { display: false },
                    datalabels: {
                        color: '#343a40', // Color oscuro para que se lea en gráficas horizontales si el fondo claro
                        anchor: 'end',
                        align: 'end',
                        padding: { left: 5 },
                        font: { weight: 'bold', size: 12 },
                        formatter: (value) => value > 0 ? value : ''
                    }
                }
            }
        });

        // 4. Gráfico Doughnut (Plantillas)
        new Chart(document.getElementById('chartTempletes'), {
            type: 'doughnut',
            data: {
                labels: dataTempletes.map(d => d.label || 'N/A'),
                datasets: [{
                    data: dataTempletes.map(d => d.total),
                    backgroundColor: [colors[1], colors[3], colors[4], colors[6], colors[8]],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 4,
                    cutout: '65%' // Hace el anillo más delgado
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { position: 'bottom', labels: { boxWidth: 12 } },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold', size: 14 },
                        formatter: (value) => value > 0 ? value : ''
                    }
                }
            }
        });

        // --- Lógica del Modal (Tickets por Día) ---
        function mostrarTicketsDia(fecha) {
            document.getElementById('spanFechaDetalle').innerText = fecha;
            const tbody = document.getElementById('tbodyTicketsDia');
            tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando tickets...</td></tr>';
            
            const modal = new bootstrap.Modal(document.getElementById('modalTicketsDia'));
            modal.show();

            fetch(`obtener_tickets_dia.php?fecha=${fecha}`)
                .then(response => response.json())
                .then(res => {
                    tbody.innerHTML = '';
                    if(res.status === 'success' && res.data.length > 0) {
                        res.data.forEach(t => {
                            tbody.innerHTML += `
                                <tr>
                                    <td class="fw-bold text-primary">#${t.id_ticket}</td>
                                    <td>${t.usuario_tecnico || '<span class="text-muted fst-italic">Sin asignar</span>'}</td>
                                    <td><span class="badge bg-secondary">${t.grupo || 'N/A'}</span></td>
                                    <td class="text-truncate" style="max-width: 200px;" title="${t.templete}">${t.templete || 'N/A'}</td>
                                    <td>${t.fecha_asignacion}</td>
                                </tr>
                            `;
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-muted">No se encontraron tickets asignados este día.</td></tr>';
                    }
                })
                .catch(err => {
                    tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-danger"><i class="bi bi-exclamation-triangle"></i> Error al cargar datos.</td></tr>';
                });
        }
        // --- Reloj del Sistema ---
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
    </script>
</body>
</html>
