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

$diasSemana = ['Mon'=>'Lunes', 'Tue'=>'Martes', 'Wed'=>'Miércoles', 'Thu'=>'Jueves', 'Fri'=>'Viernes', 'Sat'=>'Sábado', 'Sun'=>'Domingo'];
$diasCortos = ['Mon'=>'Lun', 'Tue'=>'Mar', 'Wed'=>'Mié', 'Thu'=>'Jue', 'Fri'=>'Vie', 'Sat'=>'Sáb', 'Sun'=>'Dom'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Técnicos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Técnicos</a></li>
                    <li class="nav-item"><a class="nav-link" href="plantillas.php">Plantillas</a></li>
                    <li class="nav-item"><a class="nav-link " href="log_general.php">Auditoría</a></li>
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
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Listado de Técnicos</h2>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTecnico" onclick="limpiarFormulario()">
                <i class="bi bi-plus-circle"></i> Nuevo Técnico
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover shadow-sm align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Nombre</th>
                        <th>Días Laborales (Resumen)</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tecnicos as $t): ?>
                    <tr>
                        <td>
                            <a href="#" class="text-decoration-none fw-bold" onclick="verHistorial('<?= $t['nombre'] ?>')">
                                <?= $t['nombre'] ?> <i class="bi bi-clock-history small"></i>
                            </a>
                            <br>
                            <small class="text-muted"><?= $t['usuario_login'] ?></small>
                        </td>
                        <td>
                            <?php 
                                $horarios = json_decode($t['horarios_json'] ?? '[]', true);
                                if (is_array($horarios) && count($horarios) > 0) {
                                    $ordenDias = ['Mon'=>1, 'Tue'=>2, 'Wed'=>3, 'Thu'=>4, 'Fri'=>5, 'Sat'=>6, 'Sun'=>7];
                                    usort($horarios, function($a, $b) use ($ordenDias) {
                                        return $ordenDias[$a['dia']] <=> $ordenDias[$b['dia']];
                                    });

                                    foreach ($horarios as $h) {
                                        if (isset($h['dia']) && isset($diasCortos[$h['dia']])) {
                                            $ent = substr($h['entrada'], 0, 5);
                                            $sal = substr($h['salida'], 0, 5);
                                            $iniC = substr($h['ini_comida'], 0, 5);
                                            $finC = substr($h['fin_comida'], 0, 5);
                                            $info = "<b>Entrada:</b> $ent - $sal<br><b>Comida:</b> $iniC - $finC";

                                            echo "<span class='badge bg-info text-dark me-1 mb-1' 
                                                        data-bs-toggle='tooltip' 
                                                        data-bs-html='true' 
                                                        title='$info' 
                                                        style='cursor: pointer;'>
                                                    " . $diasCortos[$h['dia']] . "
                                                  </span>";
                                        }
                                    }
                                } else {
                                    echo "<span class='text-muted small'>Sin horario</span>";
                                }
                            ?>
                        </td>
                        <td>
                            <?php if($t['activo'] == 1): ?>
                                <span class="badge bg-success">Activo (En turno)</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-warning btn-sm" onclick='editar(<?= json_encode($t) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="eliminar.php?id=<?= $t['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modalTecnico" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="guardar.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitulo">Nuevo Técnico</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="id">
                        
                        <div class="mb-2">
                            <label>Nombre Completo</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label>Usuario</label>
                                <input type="text" name="usuario_login" id="usuario_login" class="form-control" required>
                            </div>
                            <div class="col-6 mb-2">
                                <label>ID Sistema</label>
                                <input type="text" name="id_sistema" id="id_sistema" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label>Contraseña</label>
                            <input type="password" name="password" class="form-control" placeholder="Dejar vacío si no cambia">
                        </div>
                        <div class="mb-2">
                            <label>Correo</label>
                            <input type="email" name="correo" id="correo" class="form-control" required>
                        </div>

                        <hr>
                        <h6>Configuración de Horarios</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless align-middle">
                                <thead>
                                    <tr class="text-center small">
                                        <th width="5%"></th><th width="10%">Día</th><th>Entrada</th><th>Salida</th><th>Ini. Comida</th><th>Fin Comida</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($diasSemana as $key => $label): ?>
                                    <tr>
                                        <td><input type="checkbox" class="form-check-input dia-toggle" name="dias[<?= $key ?>]" value="1" data-dia="<?= $key ?>"></td>
                                        <td><strong><?= $label ?></strong></td>
                                        <td><input type="time" name="h_entrada[<?= $key ?>]" id="entrada_<?= $key ?>" class="form-control form-control-sm inputs-<?= $key ?>" disabled required></td>
                                        <td><input type="time" name="h_salida[<?= $key ?>]" id="salida_<?= $key ?>" class="form-control form-control-sm inputs-<?= $key ?>" disabled required></td>
                                        <td><input type="time" name="h_ini_comida[<?= $key ?>]" id="ini_comida_<?= $key ?>" class="form-control form-control-sm inputs-<?= $key ?>" disabled required></td>
                                        <td><input type="time" name="h_fin_comida[<?= $key ?>]" id="fin_comida_<?= $key ?>" class="form-control form-control-sm inputs-<?= $key ?>" disabled required></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalHistorial" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Historial: <span id="historialNombre" class="fw-bold"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table id="tablaHistorial" class="table table-striped table-bordered" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>ID Ticket</th>
                                    <th>Grupo</th>
                                    <th>Plantilla / Tema</th>
                                    <th>Fecha Asignación</th>
                                </tr>
                            </thead>
                            <tbody id="tablaHistorialBody">
                                </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

        // --- Historial DataTable ---
        function verHistorial(nombreTecnico) {
            const modal = new bootstrap.Modal(document.getElementById('modalHistorial'));
            document.getElementById('historialNombre').innerText = nombreTecnico;
            modal.show();

            // Limpiar tabla antes de cargar
            if ($.fn.DataTable.isDataTable('#tablaHistorial')) {
                $('#tablaHistorial').DataTable().clear().destroy();
            }
            document.getElementById('tablaHistorialBody').innerHTML = '<tr><td colspan="4" class="text-center">Cargando...</td></tr>';

            const formData = new FormData();
            formData.append('nombre', nombreTecnico);

            fetch('obtener_historial.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                document.getElementById('tablaHistorialBody').innerHTML = html;
                // Inicializar DataTable
                $('#tablaHistorial').DataTable({
                    language: { url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" },
                    order: [[ 3, "desc" ]],
                    pageLength: 5,
                    lengthMenu: [5, 10, 25]
                });
            });
        }

        // --- Formulario Técnico ---
        document.querySelectorAll('.dia-toggle').forEach(chk => {
            chk.addEventListener('change', function() {
                const dia = this.getAttribute('data-dia');
                const inputs = document.querySelectorAll('.inputs-' + dia);
                inputs.forEach(inp => inp.disabled = !this.checked);
            });
        });

        function limpiarFormulario() {
            document.getElementById('id').value = '';
            document.querySelector('form').reset();
            document.querySelectorAll('.dia-toggle').forEach(chk => {
                chk.checked = false;
                chk.dispatchEvent(new Event('change'));
            });
            document.getElementById('modalTitulo').innerText = 'Nuevo Técnico';
        }

        function editar(t) {
            limpiarFormulario();
            const modal = new bootstrap.Modal(document.getElementById('modalTecnico'));
            
            document.getElementById('id').value = t.id;
            document.getElementById('nombre').value = t.nombre;
            document.getElementById('usuario_login').value = t.usuario_login;
            document.getElementById('correo').value = t.correo;
            document.getElementById('id_sistema').value = t.id_sistema;

            if (t.horarios_json) {
                try {
                    const horarios = JSON.parse(t.horarios_json);
                    horarios.forEach(h => {
                        if(h && h.dia) {
                            const chk = document.querySelector(`.dia-toggle[data-dia="${h.dia}"]`);
                            if(chk) {
                                chk.checked = true;
                                chk.dispatchEvent(new Event('change'));
                                document.getElementById(`entrada_${h.dia}`).value = h.entrada;
                                document.getElementById(`salida_${h.dia}`).value = h.salida;
                                document.getElementById(`ini_comida_${h.dia}`).value = h.ini_comida;
                                document.getElementById(`fin_comida_${h.dia}`).value = h.fin_comida;
                            }
                        }
                    });
                } catch(e) {}
            }
            document.getElementById('modalTitulo').innerText = 'Editar Técnico';
            modal.show();
        }
    </script>
</body>
</html>