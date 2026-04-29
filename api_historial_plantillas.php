<?php
session_start();
if (!isset($_SESSION['user_id'])) { 
    http_response_code(401);
    echo json_encode(["error" => "No autorizado"]);
    exit;
}

require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Mexico_City');

$id_plantilla = $_GET['id'] ?? null;

try {
    if ($id_plantilla) {
        $stmt = $conn->prepare("SELECT * FROM historial_plantillas WHERE id_plantilla = ? ORDER BY fecha_modificacion ASC");
        $stmt->execute([$id_plantilla]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM historial_plantillas ORDER BY id_plantilla ASC, fecha_modificacion ASC");
        $stmt->execute();
    }
    
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separar por plantilla
    $historial_por_plantilla = [];
    foreach ($historial as $row) {
        $historial_por_plantilla[$row['id_plantilla']][] = $row;
    }
    
    // Campos a comparar
    $campos_comparar = [
        'plantilla_incidente' => 'Plantilla',
        'categoria' => 'Categoría',
        'subcategoria' => 'Subcategoría',
        'articulo' => 'Artículo',
        'grupo' => 'Grupo Resolutor',
        'sitio' => 'Sitio',
        'origen' => 'Origen',
        'id_grupo' => 'ID Grupo',
        'descripcion' => 'Descripción',
        'tipo_solicitud' => 'Tipo Solicitud',
        'asigna_tenico' => 'Asignación Automática',
        'tencifo_default' => 'Técnico Default'
    ];
    
    $grupos = [];
    
    foreach ($historial_por_plantilla as $id_p => $registros) {
        $grupo_actual = null;

        foreach ($registros as $index => $row) {
            $fecha_row = new DateTime($row['fecha_modificacion']);
            
            $estado_inicial_esp = $index > 0 ? $registros[$index - 1] : null;
            $cambios_esp = [];
            if ($estado_inicial_esp === null) {
                $cambios_esp[] = "<i>Creación inicial de la plantilla.</i>";
            } else {
                foreach ($campos_comparar as $campo_db => $nombre_amigable) {
                    if ($estado_inicial_esp[$campo_db] != $row[$campo_db]) {
                        $val_ant = htmlspecialchars((string)$estado_inicial_esp[$campo_db]);
                        $val_nuv = htmlspecialchars((string)$row[$campo_db]);
                        if (strlen($val_ant) > 50 || strlen($val_nuv) > 50) {
                            $cambios_esp[] = "<b>$nombre_amigable:</b><br><span class='text-danger text-decoration-line-through d-block mt-1 p-2 bg-light border rounded' style='max-height: 100px; overflow-y: auto; font-size: 0.85em;'>" . nl2br($val_ant) . "</span><span class='text-success fw-bold d-block mt-1 p-2 bg-light border rounded' style='max-height: 100px; overflow-y: auto; font-size: 0.85em;'>" . nl2br($val_nuv) . "</span>";
                        } else {
                            if($campo_db == 'asigna_tenico') {
                                $val_ant = $val_ant == '1' ? 'Sí' : 'No';
                                $val_nuv = $val_nuv == '1' ? 'Sí' : 'No';
                            }
                            $cambios_esp[] = "<b>$nombre_amigable:</b> De <span class='text-danger text-decoration-line-through'>$val_ant</span> a <span class='text-success fw-bold'>$val_nuv</span>";
                        }
                    }
                }
                if (empty($cambios_esp)) $cambios_esp[] = "<i>Se guardó sin realizar modificaciones a los campos base.</i>";
            }
            $cambios_html_esp = "<ul class='text-start mb-0 ps-3'><li>" . implode("</li><li class='mt-2'>", $cambios_esp) . "</li></ul>";
            
            $detalle_guardado = [
                'hora' => $fecha_row->format('h:i:s A'),
                'cambios_html' => $cambios_html_esp
            ];

            if ($grupo_actual === null) {
                $grupo_actual = [
                    'id_plantilla' => $id_p,
                    'inicio_sesion' => $fecha_row,
                    'fin_sesion' => $fecha_row,
                    'usuario' => $row['usuario_modificacion'],
                    'estado_inicial' => $estado_inicial_esp,
                    'estado_inicial_sesion' => $row,

                    'estado_final' => $row,
                    'cantidad_guardados' => 1,
                    'fechas_guardados' => [$detalle_guardado]
                ];
            } else {
                $diff_hours = ($fecha_row->getTimestamp() - $grupo_actual['inicio_sesion']->getTimestamp()) / 3600;
                
                if ($diff_hours <= 2) {
                    // Agregamos al grupo actual
                    $grupo_actual['fin_sesion'] = $fecha_row;
                    $grupo_actual['estado_final'] = $row;
                    $grupo_actual['cantidad_guardados']++;
                    $grupo_actual['fechas_guardados'][] = $detalle_guardado;
                    if (strpos($grupo_actual['usuario'], $row['usuario_modificacion']) === false) {
                        $grupo_actual['usuario'] .= ', ' . $row['usuario_modificacion'];
                    }
                } else {
                    // Cerramos grupo y creamos uno nuevo
                    $grupos[] = $grupo_actual;
                    $grupo_actual = [
                        'id_plantilla' => $id_p,
                        'inicio_sesion' => $fecha_row,
                        'fin_sesion' => $fecha_row,
                        'usuario' => $row['usuario_modificacion'],
                        'estado_inicial' => $registros[$index - 1],
                        'estado_inicial_sesion' => $row,
                        'estado_final' => $row,
                        'cantidad_guardados' => 1,
                        'fechas_guardados' => [$detalle_guardado]
                    ];
                }
            }
        }
        
        if ($grupo_actual !== null) {
            $grupos[] = $grupo_actual;
        }
    }

    // Ordenar todos los grupos generados por fecha fin (más recientes primero)
    usort($grupos, function($a, $b) {
        return $b['fin_sesion'] <=> $a['fin_sesion'];
    });

    // Ahora formateamos la respuesta para el frontend, calculando las diferencias (del lapso total)
    $respuesta = [];

    foreach ($grupos as $g) {
        $cambios = [];
        $estado_inicial = $g['estado_inicial'];
        $estado_inicial_sesion = $g['estado_inicial_sesion'];
        $estado_final = $g['estado_final'];
        $es_creacion = ($estado_inicial_sesion['accion'] ?? 'EDICION') === 'CREACION';
        
        $referencia_comparativa = ($estado_inicial === null) ? $estado_inicial_sesion : $estado_inicial;
        
        foreach ($campos_comparar as $campo_db => $nombre_amigable) {
            if ($referencia_comparativa[$campo_db] != $estado_final[$campo_db]) {
                $val_ant = htmlspecialchars((string)$referencia_comparativa[$campo_db]);
                $val_nuv = htmlspecialchars((string)$estado_final[$campo_db]);
                
                if (strlen($val_ant) > 50 || strlen($val_nuv) > 50) {
                     $val_ant_html = nl2br($val_ant);
                     $val_nuv_html = nl2br($val_nuv);
                     $cambios[] = "<b>$nombre_amigable:</b><br><span class='text-danger text-decoration-line-through d-block mt-1 p-2 bg-light border rounded' style='max-height: 100px; overflow-y: auto; font-size: 0.85em;'>$val_ant_html</span><span class='text-success fw-bold d-block mt-1 p-2 bg-light border rounded' style='max-height: 100px; overflow-y: auto; font-size: 0.85em;'>$val_nuv_html</span>";
                } else {
                    if($campo_db == 'asigna_tenico') {
                        $val_ant = $val_ant == '1' ? 'Sí' : 'No';
                        $val_nuv = $val_nuv == '1' ? 'Sí' : 'No';
                    }
                    $cambios[] = "<b>$nombre_amigable:</b> De <span class='text-danger text-decoration-line-through'>$val_ant</span> a <span class='text-success fw-bold'>$val_nuv</span>";
                }
            }
        }
        
        if ($estado_inicial === null) {
            if ($es_creacion) {
                if (empty($cambios)) {
                    $cambios[] = "<i>Creación inicial de la plantilla.</i>";
                } else {
                    array_unshift($cambios, "<i class='text-primary'>Creación inicial de la plantilla. Cambios en esta misma sesión:</i>");
                }
            } else {
                if (empty($cambios)) {
                    $cambios[] = "<i>Primer registro en historial (Plantilla ya existía previamente).</i>";
                } else {
                    array_unshift($cambios, "<i class='text-primary'>Primer registro en historial (Plantilla ya existía). Cambios en esta misma sesión:</i>");
                }
            }
        } else {
            if (empty($cambios)) {
                $cambios[] = "<i>Se guardó sin realizar modificaciones a los campos base.</i>";
            }
        }

        $rango_fechas = $g['inicio_sesion']->format('d/m/Y h:i A');
        if ($g['inicio_sesion'] != $g['fin_sesion']) {
            $rango_fechas .= " - " . $g['fin_sesion']->format('h:i A');
        }

        $botones_guardados = '';
        foreach ($g['fechas_guardados'] as $fg) {
            $html_escaped = htmlspecialchars($fg['cambios_html'], ENT_QUOTES, 'UTF-8');
            $botones_guardados .= "<button class='btn btn-sm btn-outline-secondary d-block w-100 mb-1' onclick='verCambioEspecifico(\"{$fg['hora']}\", \"{$html_escaped}\")'><i class='bi bi-clock-history me-1'></i>{$fg['hora']}</button>";
        }

        $respuesta[] = [
            'plantilla_nombre' => $estado_final['plantilla_incidente'], // Para el listado general
            'fecha_rango' => $rango_fechas,
            'usuario' => $g['usuario'],
            'cantidad_guardados' => $g['cantidad_guardados'],
            'detalle_guardados_html' => $botones_guardados,
            'cambios_html' => "<ul class='text-start mb-0 ps-3'><li>" . implode("</li><li class='mt-2'>", $cambios) . "</li></ul>",
            'estado_final' => $estado_final
        ];
    }

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>
