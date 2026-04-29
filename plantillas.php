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
$ticketsTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] + 217;

?>
<?php
// plantillas.php

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
require_once 'db.php';

// Obtener plantillas
$stmt = $conn->query("SELECT * FROM plantillas_incidentes WHERE status = 1");
$plantillas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Plantillas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
                    <li class="nav-item"><a class="nav-link active" href="plantillas.php">Plantillas <i class="bi bi-file-earmark-text"></i></a></li>
                    <li class="nav-item"><a class="nav-link " href="log_general.php">Auditoría <i class="bi bi-shield-check"></i></a></li>
                    <li class="nav-item"><a class="nav-link " href="reportes.php">Reportes <i class="bi bi-bar-chart-line"></i></a></li>
                    <li class="nav-item"><a class="nav-link " href="reporte_teams.php">Bot Teams <i class="bi bi-robot"></i></a></li>
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Catálogo de Plantillas</h2>
            <div>
                <button class="btn btn-info text-white me-2" onclick="verHistorial('')">
                    <i class="bi bi-clock-history"></i> Histórico General
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPlantilla" onclick="limpiarFormulario()">
                    <i class="bi bi-plus-circle"></i> Nueva Plantilla
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="tablaPlantillas" class="table table-striped table-hover table-sm shadow-sm border w-100">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Plantilla</th>
                        <th>Sitio</th>
                        <th>Tipo</th>
                        <th>Técnico Default</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($plantillas as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= $p['plantilla_incidente'] ?></td>
                        <td><?= $p['sitio'] ?></td>
                        <td><?= $p['tipo_solicitud'] ?></td>
                        <td><?= $p['tencifo_default'] ?></td>
                        <td>
                            <button class="btn btn-info btn-sm text-white shadow-sm" onclick='verHistorial(<?= $p['id'] ?>)' data-bs-toggle="tooltip" title="Ver Historial de Versiones"><i class="bi bi-clock-history"></i></button>
                            <button class="btn btn-warning btn-sm shadow-sm" onclick='editar(<?= json_encode($p) ?>)'><i class="bi bi-pencil"></i></button>
                            <a href="eliminar_plantilla.php?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm shadow-sm" onclick="return confirm('¿Eliminar?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modalPlantilla" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="guardar_plantilla.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitulo">Nueva Plantilla</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id_original" id="id_original">
                        
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="fw-bold text-primary">ID en servicedesk</label>
                                <input type="number" name="id" id="id" class="form-control border-primary" required>
                            </div>
                            <div class="col-md-9">
                                <label>Plantilla Incidente</label>
                                <input type="text" name="plantilla_incidente" id="plantilla_incidente" class="form-control" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label>Categoría</label>
                                <input type="text" name="categoria" id="categoria" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label>Subcategoría</label>
                                <input type="text" name="subcategoria" id="subcategoria" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label>Artículo</label>
                                <input type="text" name="articulo" id="articulo" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label>Sitio</label>
                                <input type="text" name="sitio" id="sitio" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label>Grupo</label>
                                <input type="text" name="grupo" id="grupo" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label>ID Grupo</label>
                                <input type="number" name="id_grupo" id="id_grupo" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label>Origen</label>
                                <input type="text" name="origen" id="origen" class="form-control" required>
                            </div>

                            <div class="col-md-12">
                                <label>Descripción (Palabras clave)</label>
                                <textarea name="descripcion" id="descripcion" class="form-control" required rows="2"></textarea>
                            </div>
                            

                            <div class="col-md-4">
                                <label>Tipo Solicitud</label>
                                <input type="text" name="tipo_solicitud" id="tipo_solicitud" required class="form-control">
                            </div>
                            <div class="col-md-4">
    <label>Tipo de Asignación</label>
    <select name="asigna_tenico" id="asigna_tenico" class="form-select" required>
        <option value="0" selected>Usar Técnico Default</option>
        <option value="1">Asignación Automática</option>
    </select>
</div>
                            <div class="col-md-4">
                                <label>Técnico Default</label>
                                <input type="text" name="tencifo_default" id="tencifo_default" class="form-control">
                            </div>
                            
                            <input type="hidden" name="status" id="status" value="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tablaPlantillas').DataTable({
                language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                order: [[ 0, "desc" ]]
            });
        });

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
        function limpiarFormulario() {
            document.getElementById('id').value = '';
            document.getElementById('id_original').value = '';
            document.querySelector('form').reset();
            document.getElementById('modalTitulo').innerText = 'Nueva Plantilla';
        }

        function editar(p) {
            const modal = new bootstrap.Modal(document.getElementById('modalPlantilla'));
            document.getElementById('id').value = p.id;
            document.getElementById('id_original').value = p.id;
            document.getElementById('status').value = p.status;
            document.getElementById('plantilla_incidente').value = p.plantilla_incidente;
            document.getElementById('categoria').value = p.categoria;
            document.getElementById('subcategoria').value = p.subcategoria;
            document.getElementById('articulo').value = p.articulo;
            document.getElementById('grupo').value = p.grupo;
            document.getElementById('sitio').value = p.sitio;
            document.getElementById('origen').value = p.origen;
            document.getElementById('id_grupo').value = p.id_grupo;
            document.getElementById('descripcion').value = p.descripcion;
            document.getElementById('tipo_solicitud').value = p.tipo_solicitud;
            document.getElementById('asigna_tenico').value = p.asigna_tenico;
            document.getElementById('tencifo_default').value = p.tencifo_default;

            document.getElementById('modalTitulo').innerText = 'Editar Plantilla';
            modal.show();
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
        
        // --- Historial Agrupado ---
        function verHistorial(id) {
            const isGeneral = !id;
            const modal = new bootstrap.Modal(document.getElementById('modalHistorial'));
            
            // Ajustar encabezados según si es general o específico
            const thead = document.getElementById('historialCabecera');
            if (isGeneral) {
                document.getElementById('modalHistorialTitulo').innerHTML = '<i class="bi bi-clock-history text-info me-2"></i>Historial General de Plantillas';
                thead.innerHTML = `
                    <tr>
                        <th>Plantilla</th>
                        <th>Sesión (Lapso 2 Hrs)</th>
                        <th>Modificado Por</th>
                        <th class="text-center">Guardados</th>
                        <th>Resumen de Cambios</th>
                        <th class="text-center">Ver Datos Finales</th>
                    </tr>
                `;
            } else {
                document.getElementById('modalHistorialTitulo').innerHTML = '<i class="bi bi-clock-history text-info me-2"></i>Historial de Versiones (Plantilla Específica)';
                thead.innerHTML = `
                    <tr>
                        <th>Sesión (Lapso 2 Hrs)</th>
                        <th>Modificado Por</th>
                        <th class="text-center">Guardados</th>
                        <th>Resumen de Cambios</th>
                        <th class="text-center">Ver Datos Finales</th>
                    </tr>
                `;
            }

            // Destruir DataTable previo si existe
            if ($.fn.DataTable.isDataTable('#tablaHistorialModal')) {
                $('#tablaHistorialModal').DataTable().clear().destroy();
            }

            const colSpan = isGeneral ? 6 : 5;
            document.getElementById('historialCuerpo').innerHTML = `<tr><td colspan="${colSpan}" class="text-center">Cargando historial... <div class="spinner-border spinner-border-sm text-primary"></div></td></tr>`;
            document.getElementById('filtroFechaHistorial').value = ''; // Limpiar filtro
            modal.show();

            const url = isGeneral ? 'api_historial_plantillas.php' : 'api_historial_plantillas.php?id=' + id;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    let html = '';
                    if(data.length === 0) {
                        html = `<tr><td colspan="${colSpan}" class="text-center text-muted">No hay versiones históricas.</td></tr>`;
                    } else {
                        data.forEach(item => {
                            html += `<tr>`;
                            if (isGeneral) {
                                html += `<td class="fw-bold text-primary">${item.plantilla_nombre}</td>`;
                            }
                            html += `
                                <td class="fw-bold" style="font-size: 0.9rem;">${item.fecha_rango}</td>
                                <td style="font-size: 0.9rem;">${item.usuario}</td>
                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill mb-1">${item.cantidad_guardados}</span>
                                    <div class="mt-2 text-start">
                                        ${item.detalle_guardados_html}
                                    </div>
                                </td>
                                <td><small>${item.cambios_html}</small></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary shadow-sm" onclick='verDetalleCompleto(${JSON.stringify(item.estado_final).replace(/'/g, "&#39;")})'><i class="bi bi-eye"></i></button>
                                </td>
                            </tr>`;
                        });
                    }
                    document.getElementById('historialCuerpo').innerHTML = html;

                    if(data.length > 0) {
                        let dtHistorial = $('#tablaHistorialModal').DataTable({
                            language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                            order: [], // Orden predeterminado desde el servidor
                            pageLength: 10,
                            lengthMenu: [5, 10, 25, 50],
                            dom: '<"row mb-2"<"col-md-6"l><"col-md-6"f>>rt<"row mt-2"<"col-md-6"i><"col-md-6"p>>'
                        });

                        $('#filtroFechaHistorial').off('change').on('change', function() {
                            let selectedDate = this.value;
                            if (selectedDate) {
                                let parts = selectedDate.split('-');
                                let formattedDate = parts[2] + '/' + parts[1] + '/' + parts[0];
                                let dateColIdx = isGeneral ? 1 : 0;
                                dtHistorial.column(dateColIdx).search(formattedDate).draw();
                            } else {
                                dtHistorial.search('').columns().search('').draw();
                            }
                        });
                    }
                })
                .catch(error => {
                    document.getElementById('historialCuerpo').innerHTML = `<tr><td colspan="${colSpan}" class="text-center text-danger">Error al cargar el historial.</td></tr>`;
                });
        }

        function verDetalleCompleto(item) {
            let infoHtml = `
                <div class="text-start" style="font-size: 0.9rem;">
                    <p><b>Plantilla:</b> ${item.plantilla_incidente}</p>
                    <p><b>Categoría:</b> ${item.categoria}</p>
                    <p><b>Subcategoría:</b> ${item.subcategoria}</p>
                    <p><b>Artículo:</b> ${item.articulo}</p>
                    <p><b>Sitio:</b> ${item.sitio}</p>
                    <p><b>Grupo:</b> ${item.grupo} (ID: ${item.id_grupo})</p>
                    <p><b>Tipo Solicitud:</b> ${item.tipo_solicitud}</p>
                    <p><b>Asignación Auto:</b> ${item.asigna_tenico == '1' ? 'Sí' : 'No'}</p>
                    <p><b>Técnico Default:</b> ${item.tencifo_default}</p>
                    <hr>
                    <p class="mb-1"><b>Descripción / Palabras clave:</b></p>
                    <div class="bg-light p-2 border rounded text-muted">${item.descripcion}</div>
                </div>
            `;
            
            Swal.fire({
                title: 'Estado Final de la Plantilla',
                html: infoHtml,
                icon: 'info',
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#0d6efd',
                width: '600px'
            });
        }

        function verCambioEspecifico(hora, cambiosHtml) {
            Swal.fire({
                title: 'Cambio a las ' + hora,
                html: '<div style="font-size: 0.9rem;">' + cambiosHtml + '</div>',
                icon: 'info',
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#0d6efd',
                width: '600px'
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Modal Historial -->
    <div class="modal fade" id="modalHistorial" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="modalHistorialTitulo"><i class="bi bi-clock-history text-info me-2"></i>Historial de Versiones</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3 d-flex align-items-center bg-light p-2 rounded border">
                        <label class="me-2 fw-bold text-secondary"><i class="bi bi-filter"></i> Filtrar por Día:</label>
                        <input type="date" id="filtroFechaHistorial" class="form-control form-control-sm w-auto shadow-sm">
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="document.getElementById('filtroFechaHistorial').value=''; $('#filtroFechaHistorial').trigger('change');" title="Limpiar filtro"><i class="bi bi-x-circle"></i> Limpiar</button>
                    </div>
                    <div class="table-responsive">
                        <table id="tablaHistorialModal" class="table table-hover table-striped mb-0 w-100">
                            <thead class="table-secondary" id="historialCabecera">
                                <!-- Llenado por JS -->
                            </thead>
                            <tbody id="historialCuerpo" class="align-middle">
                                <!-- Llenado por JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>