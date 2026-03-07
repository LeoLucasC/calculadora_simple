<?php
session_start();
include 'includes/db.php';

// Seguridad
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Obtener el ID del usuario logueado
$id_usuario = $_SESSION['id_usuario'];

// --- 1. LÓGICA DE ACTUALIZACIÓN (NUEVO) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_id_multimedia'])) {
    $id_edit = intval($_POST['edit_id_multimedia']);
    $id_ubi = intval($_POST['id_ubicacion']);
    $id_gru = intval($_POST['id_grupo']);
    $tipo = $conn->real_escape_string($_POST['tipo_archivo']);
    $es360 = isset($_POST['es_360']) ? 1 : 0;
    $obs = $conn->real_escape_string($_POST['observaciones']);

    // Actualizamos el registro asegurándonos de que pertenezca al usuario
    $sql_update = "UPDATE multimedia SET 
                    id_ubicacion = $id_ubi, 
                    id_grupo = $id_gru, 
                    tipo_archivo = '$tipo', 
                    es_360 = $es360, 
                    observaciones = '$obs' 
                   WHERE id_multimedia = $id_edit AND id_usuario = $id_usuario";
    
    if($conn->query($sql_update)){
        header("Location: historial.php?status=edited");
        exit();
    }
}

// --- LÓGICA DE ELIMINACIÓN ---
if (isset($_GET['delete_id'])) {
    $id_borrar = intval($_GET['delete_id']);
    
    // Traemos también la fecha para validarla
    $sql_check = "SELECT url_archivo, fecha_hora FROM multimedia WHERE id_multimedia = $id_borrar AND id_usuario = $id_usuario";
    $check = $conn->query($sql_check);
    
    if ($row = $check->fetch_assoc()) {
        date_default_timezone_set('America/Lima');
        $fecha_archivo = date('Y-m-d', strtotime($row['fecha_hora']));
        $hoy = date('Y-m-d');
        
        // Si el rol es Supervisor (2) o cualquier rol de creador, validamos que sea de HOY
        if ($_SESSION['id_rol'] >= 2 && $fecha_archivo !== $hoy) {
            header("Location: historial.php?status=error_fecha");
            exit();
        }

        $ruta_archivo = $row['url_archivo'];
        if (file_exists($ruta_archivo)) { unlink($ruta_archivo); }
        $conn->query("DELETE FROM multimedia WHERE id_multimedia = $id_borrar");
        header("Location: historial.php?status=deleted");
        exit();
    }
}

// Paginación
// --- FILTROS DE BÚSQUEDA ---
$filtro_ubi = isset($_GET['f_ubicacion']) && $_GET['f_ubicacion'] !== '' ? intval($_GET['f_ubicacion']) : '';
$filtro_gru = isset($_GET['f_grupo']) && $_GET['f_grupo'] !== '' ? intval($_GET['f_grupo']) : '';

$where = "WHERE m.id_usuario = $id_usuario";
if ($filtro_ubi !== '') $where .= " AND m.id_ubicacion = $filtro_ubi";
if ($filtro_gru !== '') $where .= " AND m.id_grupo = $filtro_gru";

// Paginación
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Consultas actualizadas con el $where de los filtros
$total_query = "SELECT COUNT(*) as total FROM multimedia m $where";
$total_result = $conn->query($total_query);
$total_records = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

$query = "SELECT m.*, u.nombre as ubicacion_nombre, g.nombre as grupo_nombre, c.razon_social as nombre_empresa
          FROM multimedia m
          INNER JOIN ubicaciones u ON m.id_ubicacion = u.id_ubicacion
          INNER JOIN grupos_operativos g ON m.id_grupo = g.id_grupo
          INNER JOIN usuarios usr ON m.id_usuario = usr.id_usuario
          LEFT JOIN clientes c ON usr.id_cliente = c.id_cliente
          $where
          ORDER BY m.fecha_hora DESC
          LIMIT $offset, $limit";
$resultado = $conn->query($query);

// Parámetros para mantener el filtro al cambiar de página
$url_filtros = "&f_ubicacion=$filtro_ubi&f_grupo=$filtro_gru";

// Obtener listas para el Modal de Edición
$ubicaciones = $conn->query("SELECT id_ubicacion, nombre FROM ubicaciones WHERE estado=1 ORDER BY nombre");
$grupos = $conn->query("SELECT id_grupo, nombre, id_ubicacion FROM grupos_operativos WHERE estado=1 ORDER BY nombre");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Cargas | KunturVR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1e293b; display: flex; min-height: 100vh; }
        
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 2rem; font-weight: 600; color: #0f172a; margin-bottom: 8px; }
        .page-header p { color: #64748b; font-size: 1rem; }

        .table-container { background: white; border-radius: 20px; padding: 25px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05); border: 1px solid #e9eef2; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 12px; font-weight: 600; font-size: 0.9rem; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e9eef2; }
        td { padding: 16px 12px; border-bottom: 1px solid #e9eef2; color: #1e293b; }
        tr:hover { background: #f8fafc; }

        .badge { padding: 6px 12px; border-radius: 30px; font-size: 0.8rem; font-weight: 500; display: inline-block; }
        .badge-foto { background: #d4edda; color: #155724; }
        .badge-video { background: #cce5ff; color: #004085; }

        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 30px; }
        .pagination a, .pagination span { padding: 10px 15px; border-radius: 8px; background: white; color: #023675; text-decoration: none; border: 1px solid #e9eef2; transition: all 0.3s ease; }
        .pagination a:hover, .pagination .active { background: #023675; color: white; border-color: #023675; }

        /* Botones de acción */
        .btn-view { padding: 8px 12px; background: #e8f0fe; color: #023675; border-radius: 8px; text-decoration: none; display: inline-flex; font-size: 0.9rem; transition: 0.3s; }
        .btn-view:hover { background: #023675; color: white; }
        
        .btn-edit { padding: 8px 12px; background: #fef3c7; color: #d97706; border-radius: 8px; text-decoration: none; display: inline-flex; font-size: 0.9rem; border: none; cursor: pointer; transition: 0.3s; margin-left: 5px; }
        .btn-edit:hover { background: #d97706; color: white; }
        
        .btn-delete { padding: 8px 12px; background: #fee2e2; color: #dc2626; border-radius: 8px; text-decoration: none; display: inline-flex; font-size: 0.9rem; transition: 0.3s; margin-left: 5px; }
        .btn-delete:hover { background: #dc2626; color: white; }

        .empty-state { text-align: center; padding: 50px; color: #64748b; }
        .empty-state i { font-size: 4rem; margin-bottom: 20px; opacity: 0.5; }

        /* Estilos del Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal.show { display: flex; }
        .modal-content { background: white; width: 90%; max-width: 500px; border-radius: 15px; padding: 25px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
        .modal-header h2 { font-size: 1.2rem; color: #0f172a; }
        .close-modal { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; }
        .btn-primary { background: #023675; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; width: 100%; font-weight: 600; margin-top: 10px; }

        @media (max-width: 768px) { body { flex-direction: column; } .main-content { padding: 20px; } }
    </style>
</head>
<body>

    <?php include 'includes/navbar_cliente.php'; ?>

    <div class="main-content">
        <?php if(isset($_GET['status'])): ?>
            <?php if($_GET['status'] == 'edited'): ?>
                <div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:10px; margin-bottom:20px;">
                    <i class="fas fa-check-circle"></i> Registro actualizado correctamente.
                </div>
            <?php elseif($_GET['status'] == 'deleted'): ?>
                <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:10px; margin-bottom:20px;">
                    <i class="fas fa-trash-alt"></i> Archivo eliminado.
                </div>
            <?php elseif($_GET['status'] == 'error_fecha'): ?>
                <div style="background:#fef3c7; color:#92400e; padding:15px; border-radius:10px; margin-bottom:20px;">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Acción denegada:</strong> Solo puedes eliminar archivos que hayan sido subidos el día de hoy.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="page-header">
            <h1>Historial de Cargas</h1>
            <p>Todos los archivos que has subido al sistema</p>
        </div>
        
        <form method="GET" style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; background: white; padding: 15px; border-radius: 15px; border: 1px solid #e9eef2;">
            <select name="f_ubicacion" id="filtro_ubicacion" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; flex: 1; min-width: 200px;">
                <option value="">Todas las ubicaciones...</option>
                <?php 
                $ubicaciones->data_seek(0);
                while($u = $ubicaciones->fetch_assoc()): 
                    $sel = ($filtro_ubi === (int)$u['id_ubicacion']) ? 'selected' : '';
                ?>
                    <option value="<?= $u['id_ubicacion'] ?>" <?= $sel ?>><?= htmlspecialchars($u['nombre']) ?></option>
                <?php endwhile; ?>
            </select>
            
            <select name="f_grupo" id="filtro_grupo" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; flex: 1; min-width: 200px;">
                <option value="">Todos los grupos...</option>
                <?php 
                if ($filtro_ubi !== '') {
                    $grupos->data_seek(0);
                    while($g = $grupos->fetch_assoc()): 
                        if ($g['id_ubicacion'] == $filtro_ubi):
                            $sel = ($filtro_gru === (int)$g['id_grupo']) ? 'selected' : '';
                ?>
                        <option value="<?= $g['id_grupo'] ?>" <?= $sel ?>><?= htmlspecialchars($g['nombre']) ?></option>
                <?php 
                        endif;
                    endwhile; 
                } else {
                    // Si no hay ubicación seleccionada, cargar todos inicialmente
                    $grupos->data_seek(0);
                    while($g = $grupos->fetch_assoc()): 
                        $sel = ($filtro_gru === (int)$g['id_grupo']) ? 'selected' : '';
                ?>
                        <option value="<?= $g['id_grupo'] ?>" <?= $sel ?>><?= htmlspecialchars($g['nombre']) ?></option>
                <?php 
                    endwhile; 
                }
                ?>
            </select>
            
            <button type="submit" style="padding: 10px 20px; background: #023675; color: white; border: none; border-radius: 8px; cursor: pointer;">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="historial.php" style="padding: 10px 20px; background: #e2e8f0; color: #1e293b; text-decoration: none; border-radius: 8px; display: flex; align-items: center;">
                Limpiar
            </a>
        </form>
        
        <div class="table-container">
            <?php if ($resultado->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Preview</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Ubicación</th>
                            <th>Grupo</th>
                            <th>Tipo</th>
                            <th>360° / 3D</th>
                            <th>Observaciones</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $resultado->fetch_assoc()): 
                            $ext = strtolower(pathinfo($row['url_archivo'], PATHINFO_EXTENSION));
                            $es_3d = ($ext == 'glb' || $row['tipo_archivo'] == 'MODELO_3D');
                        ?>
                        <tr>
                            <td>
                                <?php if($es_3d): ?>
                                    <div style="width: 50px; height: 50px; background: #e0f2fe; color: #0284c7; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; border: 1px solid #7dd3fc;" title="Modelo 3D">
                                        <i class="fas fa-cube"></i>
                                    </div>
                                <?php elseif($row['tipo_archivo'] == 'VIDEO'): ?>
                                    <div style="width: 50px; height: 50px; background: #f1f5f9; color: #64748b; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; border: 1px solid #e2e8f0;" title="Video">
                                        <i class="fas fa-file-video"></i>
                                    </div>
                                <?php else: ?>
                                    <img src="miniatura.php?ruta=<?php echo urlencode($row['url_archivo']); ?>" loading="lazy" decoding="async" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc;">
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['fecha_hora'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['nombre_empresa'] ?: 'KUNTUR VR'); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['ubicacion_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($row['grupo_nombre']); ?></td>
                            <td>
                                <?php if ($es_3d): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0284c7; border: 1px solid #7dd3fc;">MODELO 3D</span>
                                <?php else: ?>
                                    <span class="badge badge-<?php echo strtolower($row['tipo_archivo']); ?>">
                                        <?php echo htmlspecialchars($row['tipo_archivo']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo ($row['es_360'] || $es_3d) ? 'Sí' : 'No'; ?></td>
                            <td><?php echo substr(htmlspecialchars($row['observaciones']), 0, 40) . (strlen($row['observaciones']) > 40 ? '...' : ''); ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <button class="btn-view" style="background: #10b981; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer;" title="Ver archivo" 
                                        onclick="abrirVistaPrevia(
                                            '<?= htmlspecialchars(addslashes($row['url_archivo'])) ?>', 
                                            '<?= $row['tipo_archivo'] ?>',
                                            '<?= htmlspecialchars(addslashes($row['nombre_empresa'] ?: 'KUNTUR VR')) ?>',
                                            '<?= htmlspecialchars(addslashes($row['grupo_nombre'])) ?>',
                                            '<?= htmlspecialchars(addslashes($row['ubicacion_nombre'])) ?>',
                                            '<?= date('d/m/Y H:i', strtotime($row['fecha_hora'])) ?>'
                                        )">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <a href="<?= htmlspecialchars($row['url_archivo']) ?>" download class="btn-download" style="background: #3b82f6; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none;" title="Descargar">
                                        <i class="fas fa-download"></i>
                                    </a>

                                    <button class="btn-edit" style="background: #f59e0b; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer;" title="Editar detalles" onclick="openEditModal(
                                        <?= $row['id_multimedia'] ?>, 
                                        <?= $row['id_ubicacion'] ?>, 
                                        <?= $row['id_grupo'] ?>, 
                                        '<?= $row['tipo_archivo'] ?>', 
                                        <?= $row['es_360'] ?>, 
                                        '<?= htmlspecialchars(str_replace(array("\r", "\n"), '', $row['observaciones']), ENT_QUOTES) ?>'
                                    )">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <?php 
                                    // Comprobar si el archivo es de hoy
                                    date_default_timezone_set('America/Lima');
                                    $es_de_hoy = (date('Y-m-d', strtotime($row['fecha_hora'])) === date('Y-m-d'));
                                    
                                    // Mostrar el botón de borrar SOLO si es de hoy
                                    if ($es_de_hoy): 
                                    ?>
                                        <a href="?page=<?php echo $page . $url_filtros; ?>&delete_id=<?php echo $row['id_multimedia']; ?>" 
                                           class="btn-delete" style="background: #ef4444; color: white; padding: 8px 12px; border-radius: 6px; text-decoration: none;" title="Eliminar"
                                           onclick="return confirm('¿Estás seguro de eliminar este archivo permanentemente?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    <?php else: ?>
                                        <button disabled style="background: #cbd5e1; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: not-allowed;" title="Ya no se puede eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($row['fecha_hora'])); ?></td>
                            <td><?php echo htmlspecialchars($row['ubicacion_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($row['grupo_nombre']); ?></td>
                            <td>
                                <?php if ($es_3d): ?>
                                    <span class="badge" style="background: #e0f2fe; color: #0284c7; border: 1px solid #7dd3fc;">MODELO 3D</span>
                                <?php else: ?>
                                    <span class="badge badge-<?php echo strtolower($row['tipo_archivo']); ?>">
                                        <?php echo htmlspecialchars($row['tipo_archivo']); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo ($row['es_360'] || $es_3d) ? 'Sí' : 'No'; ?></td>
                            <td><?php echo substr(htmlspecialchars($row['observaciones']), 0, 40) . (strlen($row['observaciones']) > 40 ? '...' : ''); ?></td>
                            <td>
                                <button class="btn-edit" title="Editar detalles" onclick="openEditModal(
                                    <?= $row['id_multimedia'] ?>, 
                                    <?= $row['id_ubicacion'] ?>, 
                                    <?= $row['id_grupo'] ?>, 
                                    '<?= $row['tipo_archivo'] ?>', 
                                    <?= $row['es_360'] ?>, 
                                    '<?= htmlspecialchars(str_replace(array("\r", "\n"), '', $row['observaciones']), ENT_QUOTES) ?>'
                                )">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <a href="?page=<?php echo $page . $url_filtros; ?>&delete_id=<?php echo $row['id_multimedia']; ?>" 
                                   class="btn-delete" title="Eliminar"
                                   onclick="return confirm('¿Estás seguro de eliminar este archivo permanentemente?');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?page=<?php echo $page-1 . $url_filtros; ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i . $url_filtros; ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page+1 . $url_filtros; ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>No hay cargas registradas</h3>
                    <p>Comienza subiendo tu primera evidencia.</p>
                    <a href="subir.php" class="btn-view" style="margin-top: 20px;">Subir ahora</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Detalles del Archivo</h2>
                <button type="button" class="close-modal" onclick="closeEditModal()">&times;</button>
            </div>
            <form action="historial.php" method="POST">
                <input type="hidden" name="edit_id_multimedia" id="edit_id_multimedia">
                
                <div class="form-group">
                    <label>Ubicación</label>
                    <select name="id_ubicacion" id="edit_ubicacion" class="form-control" required>
                        <option value="">Seleccione...</option>
                        <?php 
                        $ubicaciones->data_seek(0);
                        while($u = $ubicaciones->fetch_assoc()): ?>
                            <option value="<?= $u['id_ubicacion'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Grupo Operativo</label>
                    <select name="id_grupo" id="edit_grupo" class="form-control" required>
                        <option value="">Primero seleccione ubicación...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo de Archivo</label>
                    <select name="tipo_archivo" id="edit_tipo" class="form-control">
                        <option value="FOTO">Fotografía Estándar</option>
                        <option value="VIDEO">Video de Inspección</option>
                        <option value="MODELO_3D">Modelo 3D (.GLB)</option> </select>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 10px; padding: 10px 0;">
                    <input type="checkbox" name="es_360" id="edit_360" value="1" style="width: 18px; height: 18px;">
                    <label for="edit_360" style="margin: 0; cursor: pointer;">¿Es contenido 360° / VR?</label>
                </div>

                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" id="edit_obs" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" class="btn-primary">Guardar Cambios</button>
            </form>
        </div>
    </div>





    <div class="modal" id="modalVistaPrevia" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center;">
        <div class="modal-content" style="background:white; padding:20px; border-radius:10px; width:90%; max-width:800px; position:relative; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display:flex; justify-content:space-between; border-bottom:1px solid #ccc; padding-bottom:10px; margin-bottom:15px;">
                <h2 style="margin:0; font-size:1.5rem; color:#023675;">Vista Previa del Archivo</h2>
                <button class="close-modal" onclick="cerrarVistaPrevia()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            
            <div id="previewContent" class="preview-container" style="text-align:center; min-height: 200px; margin-bottom: 20px;">
                </div>
            
            <div id="previewInfo" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; text-align: left;">
                </div>
        </div>
    </div>

    <script>
        // Arreglo de grupos para el filtro dinámico en el modal
        const todosLosGrupos = [
            <?php 
            if($grupos->num_rows > 0) {
                $grupos->data_seek(0);
                while($row = $grupos->fetch_assoc()) {
                    echo "{ id: " . $row['id_grupo'] . ", nombre: '" . addslashes($row['nombre']) . "', id_ubicacion: " . $row['id_ubicacion'] . " },\n";
                }
            }
            ?>
        ];

        // --- NUEVO: Lógica del selector en cascada para la BARRA DE FILTROS ---
        document.getElementById('filtro_ubicacion').addEventListener('change', function() {
            const idUbiSeleccionada = this.value;
            const selectFiltroGrupo = document.getElementById('filtro_grupo');
            
            // Limpiamos el selector de grupos
            selectFiltroGrupo.innerHTML = '<option value="">Todos los grupos...</option>';
            
            let gruposParaMostrar = [];
            
            if (idUbiSeleccionada) {
                // Si seleccionó una ubicación, filtramos los grupos de esa ubicación
                gruposParaMostrar = todosLosGrupos.filter(g => g.id_ubicacion == idUbiSeleccionada);
            } else {
                // Si volvió a elegir "Todas las ubicaciones", mostramos todos los grupos de nuevo
                gruposParaMostrar = todosLosGrupos;
            }
            
            // Llenamos el select
            gruposParaMostrar.forEach(g => {
                const opt = document.createElement('option');
                opt.value = g.id;
                opt.textContent = g.nombre;
                selectFiltroGrupo.appendChild(opt);
            });
        });

        // Lógica del selector en cascada para el modal
        document.getElementById('edit_ubicacion').addEventListener('change', function() {
            const idUbicacion = this.value;
            const selectGrupo = document.getElementById('edit_grupo');
            selectGrupo.innerHTML = '<option value="">Seleccione un grupo...</option>';
            
            const gruposFiltrados = todosLosGrupos.filter(g => g.id_ubicacion == idUbicacion);
            gruposFiltrados.forEach(g => {
                const opt = document.createElement('option');
                opt.value = g.id;
                opt.textContent = g.nombre;
                selectGrupo.appendChild(opt);
            });
        });

        // Función para abrir el modal y llenarlo de datos
        function openEditModal(id, idUbi, idGrupo, tipo, es360, obs) {
            document.getElementById('edit_id_multimedia').value = id;
            document.getElementById('edit_ubicacion').value = idUbi;
            
            // Disparamos el cambio de ubicación para cargar los grupos correspondientes
            const event = new Event('change');
            document.getElementById('edit_ubicacion').dispatchEvent(event);
            
            // Esperamos un milisegundo para que el DOM se actualice antes de setear el grupo
            setTimeout(() => {
                document.getElementById('edit_grupo').value = idGrupo;
            }, 10);
            
            document.getElementById('edit_tipo').value = tipo;
            document.getElementById('edit_360').checked = (es360 == 1);
            document.getElementById('edit_obs').value = obs;
            
            document.getElementById('editModal').classList.add('show');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        // Cerrar modal si hacen click fuera
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) { closeEditModal(); }
        }



       function abrirVistaPrevia(url, tipo, cliente, operacion, ubicacion, fecha) {
            const container = document.getElementById('previewContent');
            const infoDiv = document.getElementById('previewInfo');
            
            container.innerHTML = '<i class="fas fa-spinner fa-spin fa-3x" style="color:#023675;"></i>'; 
            document.getElementById('modalVistaPrevia').style.display = 'flex';

            // Inyectar la información en la cuadrícula inferior con estilos limpios
            infoDiv.innerHTML = `
                <div class="info-item">
                    <div class="label" style="font-size:0.85rem; color:#64748b; font-weight:600; text-transform:uppercase;">Cliente</div>
                    <div class="value" style="font-size:1rem; color:#0f172a; font-weight:500;">${cliente}</div>
                </div>
                <div class="info-item">
                    <div class="label" style="font-size:0.85rem; color:#64748b; font-weight:600; text-transform:uppercase;">Operación</div>
                    <div class="value" style="font-size:1rem; color:#0f172a; font-weight:500;">${operacion}</div>
                </div>
                <div class="info-item">
                    <div class="label" style="font-size:0.85rem; color:#64748b; font-weight:600; text-transform:uppercase;">Ubicación / Zona</div>
                    <div class="value" style="font-size:1rem; color:#0f172a; font-weight:500;">${ubicacion}</div>
                </div>
                <div class="info-item">
                    <div class="label" style="font-size:0.85rem; color:#64748b; font-weight:600; text-transform:uppercase;">Fecha de Carga</div>
                    <div class="value" style="font-size:1rem; color:#0f172a; font-weight:500;">${fecha}</div>
                </div>
            `;

            // Cargar el contenido multimedia
            setTimeout(() => {
                let html = '';
                if (tipo === 'FOTO') {
                    // max-height reducido a 50vh para que la info de abajo no quede escondida
                    html = `<img src="${url}" style="max-width:100%; max-height:50vh; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,0.2);">`;
                } else if (tipo === 'VIDEO') {
                    html = `<video controls style="max-width:100%; max-height:50vh; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,0.2);"><source src="${url}" type="video/mp4">Tu navegador no soporta videos.</video>`;
                } else if (tipo === 'MODELO_3D') {
                    html = `
                    <div style="padding: 30px; background:#f1f5f9; border-radius:10px;">
                        <i class="fas fa-cube fa-4x" style="color:#0284c7; margin-bottom:15px;"></i>
                        <h3>Modelo 3D (.glb)</h3>
                        <p style="color:#64748b;">Para visualizar este archivo en 3D, utiliza el entorno AR o descárgalo a tu computadora.</p>
                        <a href="${url}" download class="btn-primary" style="display:inline-block; margin-top:15px; padding:10px 20px; text-decoration:none; background:#0284c7; color:white; border-radius:8px;">Descargar Modelo</a>
                    </div>`;
                } else {
                    html = `<p>Formato no compatible para vista previa directa.</p><a href="${url}" download>Descargar Archivo</a>`;
                }
                container.innerHTML = html;
            }, 300);
        }


        function cerrarVistaPrevia() {
            const container = document.getElementById('previewContent');
            container.innerHTML = ''; // Limpiar el contenido para que los videos dejen de sonar
            document.getElementById('modalVistaPrevia').style.display = 'none';
        }
    </script>
</body>
</html>