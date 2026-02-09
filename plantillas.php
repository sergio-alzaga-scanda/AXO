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
                    <li class="nav-item"><a class="nav-link active" href="plantillas.php">Plantillas</a></li>
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
            <h2>Catálogo de Plantillas</h2>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPlantilla" onclick="limpiarFormulario()">
                <i class="bi bi-plus-circle"></i> Nueva Plantilla
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm shadow-sm border">
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
                            <button class="btn btn-warning btn-sm" onclick='editar(<?= json_encode($p) ?>)'><i class="bi bi-pencil"></i></button>
                            <a href="eliminar_plantilla.php?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar?')"><i class="bi bi-trash"></i></a>
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
                        <input type="hidden" name="id" id="id">
                        
                        <div class="row g-2">
                            <div class="col-md-12">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function limpiarFormulario() {
            document.getElementById('id').value = '';
            document.querySelector('form').reset();
            document.getElementById('modalTitulo').innerText = 'Nueva Plantilla';
        }

        function editar(p) {
            const modal = new bootstrap.Modal(document.getElementById('modalPlantilla'));
            document.getElementById('id').value = p.id;
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
    </script>
</body>
</html>