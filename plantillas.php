<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }
require_once 'db.php';

// Obtener plantillas
$stmt = $conn->query("SELECT * FROM plantillas_incidentes");
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
        <div class="container">
            <span class="navbar-brand">Sistema AXO</span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Técnicos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="plantillas.php">Plantillas</a>
                    </li>
                </ul>
                <div class="d-flex text-white align-items-center">
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
                        <th>Categoría</th>
                        <th>Subcat.</th>
                        <th>Artículo</th>
                        <th>Grupo</th>
                        <th>Origen</th>
                        <th>ID Grp</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($plantillas as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= $p['plantilla_incidente'] ?></td>
                        <td><?= $p['categoria'] ?></td>
                        <td><?= $p['subcategoria'] ?></td>
                        <td><?= $p['articulo'] ?></td>
                        <td><?= $p['grupo'] ?></td>
                        <td><?= $p['origen'] ?></td>
                        <td><?= $p['id_grupo'] ?></td>
                        <td style="width: 100px;">
                            <button class="btn btn-warning btn-sm py-0" onclick='editar(<?= json_encode($p) ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="eliminar_plantilla.php?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm py-0" onclick="return confirm('¿Eliminar plantilla?')">
                                <i class="bi bi-trash"></i>
                            </a>
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
                        
                        <div class="mb-2">
                            <label>Nombre Plantilla</label>
                            <input type="text" name="plantilla_incidente" id="plantilla_incidente" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <label>Categoría</label>
                                <input type="text" name="categoria" id="categoria" class="form-control">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label>Subcategoría</label>
                                <input type="text" name="subcategoria" id="subcategoria" class="form-control">
                            </div>
                        </div>

                        <div class="mb-2">
                            <label>Artículo</label>
                            <input type="text" name="articulo" id="articulo" class="form-control">
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-2">
                                <label>Grupo</label>
                                <input type="text" name="grupo" id="grupo" class="form-control">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label>Origen</label>
                                <input type="text" name="origen" id="origen" class="form-control">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label>ID Grupo (Num)</label>
                                <input type="number" name="id_grupo" id="id_grupo" class="form-control" required>
                            </div>
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

        function editar(datos) {
            const modal = new bootstrap.Modal(document.getElementById('modalPlantilla'));
            document.getElementById('id').value = datos.id;
            document.getElementById('plantilla_incidente').value = datos.plantilla_incidente;
            document.getElementById('categoria').value = datos.categoria;
            document.getElementById('subcategoria').value = datos.subcategoria;
            document.getElementById('articulo').value = datos.articulo;
            document.getElementById('grupo').value = datos.grupo;
            document.getElementById('origen').value = datos.origen;
            document.getElementById('id_grupo').value = datos.id_grupo;

            document.getElementById('modalTitulo').innerText = 'Editar Plantilla';
            modal.show();
        }
    </script>
</body>
</html>