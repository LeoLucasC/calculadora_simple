<?php
include 'includes/db.php';
include 'includes/seguridad.php'; 

// Validar que no sea Admin
if ($_SESSION['id_rol'] == 1) { header("Location: dashboard_admin.php"); exit(); }

$id_user = $_SESSION['id_usuario'];
$mensaje = '';

// ==========================================
// 1. PROCESAR ACCIONES (ELIMINAR Y EDITAR)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- ACCIÓN: ELIMINAR ---
    if (isset($_POST['action']) && $_POST['action'] == 'delete') {
        $id_del = intval($_POST['id_marcador']);
        
        // Primero buscamos las rutas de los archivos para borrarlos del servidor y no ocupar espacio basura
        $sql_files = "SELECT archivo_patt, archivo_audio FROM marcadores_ar WHERE id_marcador = $id_del AND id_usuario = $id_user";
        $res_files = $conn->query($sql_files);
        
        if ($res_files && $row_files = $res_files->fetch_assoc()) {
            if (file_exists($row_files['archivo_patt'])) { unlink($row_files['archivo_patt']); }
            
            $audio = trim($row_files['archivo_audio'], "'");
            if ($audio && $audio != 'NULL' && file_exists($audio)) { unlink($audio); }
            
            // Borramos de la BD
            $conn->query("DELETE FROM marcadores_ar WHERE id_marcador = $id_del AND id_usuario = $id_user");
            $mensaje = '<div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #10b981;"><i class="fas fa-check-circle"></i> Marcador eliminado correctamente.</div>';
        }
    }

    // --- ACCIÓN: EDITAR (Parámetros visuales y audio) ---
    if (isset($_POST['action']) && $_POST['action'] == 'edit') {
        $id_edit      = intval($_POST['id_marcador']);
        $texto_modelo = $conn->real_escape_string($_POST['texto_modelo']);
        $color        = $conn->real_escape_string($_POST['color']);
        $alineacion   = $conn->real_escape_string($_POST['alineacion']);
        $escala_x     = floatval($_POST['escala_x']);
        $escala_y     = floatval($_POST['escala_y']);
        $escala_z     = floatval($_POST['escala_z']);
        $pos_x        = floatval($_POST['pos_x']);
        $pos_y        = floatval($_POST['pos_y']);
        $pos_z        = floatval($_POST['pos_z']);
        
        // Procesar nuevo audio si se subió
        $audio_actual = '';
        $sql_audio = "SELECT archivo_audio FROM marcadores_ar WHERE id_marcador = $id_edit AND id_usuario = $id_user";
        $res_audio = $conn->query($sql_audio);
        if ($res_audio && $row_audio = $res_audio->fetch_assoc()) {
            $audio_actual = $row_audio['archivo_audio'];
        }
        
        $archivo_audio = $audio_actual; // Por defecto mantener el actual
        
        if (isset($_FILES['nuevo_audio']) && $_FILES['nuevo_audio']['error'] == 0) {
            $audio_tmp = $_FILES['nuevo_audio']['tmp_name'];
            $audio_nombre = $_FILES['nuevo_audio']['name'];
            $audio_ext = strtolower(pathinfo($audio_nombre, PATHINFO_EXTENSION));
            
            // Validar extensión
            $allowed = ['mp3', 'wav', 'ogg', 'm4a'];
            if (in_array($audio_ext, $allowed)) {
                // Generar nombre único
                $nuevo_nombre = 'uploads/' . time() . '_' . rand(1000, 9999) . '_audio.' . $audio_ext;
                
                if (move_uploaded_file($audio_tmp, $nuevo_nombre)) {
                    // Eliminar audio anterior si existe
                    if ($audio_actual && file_exists($audio_actual)) {
                        unlink($audio_actual);
                    }
                    $archivo_audio = $nuevo_nombre;
                }
            }
        }

        $sql_update = "UPDATE marcadores_ar SET 
                        texto_modelo='$texto_modelo', color='$color', alineacion='$alineacion',
                        escala_x=$escala_x, escala_y=$escala_y, escala_z=$escala_z,
                        pos_x=$pos_x, pos_y=$pos_y, pos_z=$pos_z,
                        archivo_audio='$archivo_audio'
                       WHERE id_marcador = $id_edit AND id_usuario = $id_user";
                       
        if ($conn->query($sql_update)) {
            $mensaje = '<div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #10b981;"><i class="fas fa-check-circle"></i> Configuración actualizada.</div>';
        } else {
            $mensaje = '<div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #ef4444;"><i class="fas fa-times-circle"></i> Error al actualizar.</div>';
        }
    }
}

// ==========================================
// 2. OBTENER LISTA DE MARCADORES
// ==========================================
$sql = "SELECT ma.*, u.nombre as ubicacion_nombre, g.nombre as grupo_nombre, m.observaciones as modelo_nombre 
        FROM marcadores_ar ma
        INNER JOIN ubicaciones u ON ma.id_ubicacion = u.id_ubicacion
        INNER JOIN grupos_operativos g ON ma.id_grupo = g.id_grupo
        INNER JOIN multimedia m ON ma.id_modelo_3d = m.id_multimedia
        WHERE ma.id_usuario = $id_user
        ORDER BY ma.fecha_creacion DESC";
$marcadores = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Mis Marcadores AR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1e293b; display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px;}
        .page-header h1 { font-size: 2rem; font-weight: 600; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: #023675; }
        .page-header p { color: #64748b; font-size: 1rem; margin-top: 5px; }
        
        .btn-crear { background: #023675; color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-crear:hover { background: #0347a3; transform: translateY(-2px); }

        /* Estilos de tabla */
        .table-container { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { background: #f8fafc; color: #64748b; font-weight: 600; text-align: left; padding: 15px; border-bottom: 2px solid #e2e8f0; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; color: #334155; vertical-align: middle; }
        tr:hover td { background: #f8fafc; }
        
        /* Badges y visuales */
        .badge-color { width: 15px; height: 15px; border-radius: 50%; display: inline-block; border: 1px solid rgba(0,0,0,0.2); vertical-align: middle; margin-right: 5px; }
        .texto-ar { font-family: monospace; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; color: #023675; font-size: 0.85rem;}
        
        /* Botones de Acción */
        .action-btns { display: flex; gap: 8px; }
        .btn-action { width: 35px; height: 35px; border-radius: 8px; border: none; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; transition: 0.2s; font-size: 0.9rem; }
        .btn-edit { background: #f59e0b; } .btn-edit:hover { background: #d97706; }
        .btn-delete { background: #ef4444; } .btn-delete:hover { background: #dc2626; }
        .btn-view { background: #10b981; } .btn-view:hover { background: #059669; }

        /* Modal (Popup) */
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; width: 90%; max-width: 600px; border-radius: 16px; padding: 30px; position: relative; max-height: 90vh; overflow-y: auto; }
        .close-modal { position: absolute; top: 20px; right: 20px; font-size: 1.5rem; color: #64748b; cursor: pointer; border: none; background: none; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
        .btn-save { background: #023675; color: white; border: none; padding: 12px; width: 100%; border-radius: 8px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .audio-info { font-size: 0.85rem; color: #64748b; margin-top: 5px; }

        @media (max-width: 768px) { .main-content { padding: 80px 20px 30px; } }
    </style>
</head>
<body>

    <?php include 'includes/navbar_cliente.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-list-ul"></i> Listado de Marcadores AR</h1>
                <p>Gestiona, edita o elimina los marcadores que has creado.</p>
            </div>
            <a href="subir_marcadores.php" class="btn-crear">
                <i class="fas fa-plus"></i> Nuevo Marcador
            </a>
        </div>

        <?= $mensaje ?>

        <div class="table-container">
            <?php if($marcadores && $marcadores->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ubicación / Grupo</th>
                            <th>Modelo 3D</th>
                            <th>Texto Configurado</th>
                            <th>Parámetros (Escala / Color)</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $marcadores->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= $row['id_marcador'] ?></strong></td>
                                <td>
                                    <div style="font-weight: 600; color: #023675;"><?= htmlspecialchars($row['ubicacion_nombre']) ?></div>
                                    <div style="font-size: 0.85rem; color: #64748b;"><?= htmlspecialchars($row['grupo_nombre']) ?></div>
                                </td>
                                <td><i class="fas fa-cube" style="color: #64748b;"></i> <?= htmlspecialchars($row['modelo_nombre'] ?: 'Modelo sin nombre') ?></td>
                                <td><span class="texto-ar"><?= htmlspecialchars($row['texto_modelo']) ?></span></td>
                                <td>
                                    <span class="badge-color" style="background: <?= $row['color'] ?>;"></span> <?= ucfirst($row['color']) ?><br>
                                    <small style="color:#64748b;">Escala: <?= $row['escala_x'] ?> | Pos: <?= $row['pos_y'] ?></small>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="entorno_ar.php" class="btn-action btn-view" title="Abrir cámara AR">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <button class="btn-action btn-edit" title="Editar Visuales y Audio" 
                                            onclick="openEditModal(
                                                <?= $row['id_marcador'] ?>, 
                                                '<?= addslashes($row['texto_modelo']) ?>', 
                                                '<?= $row['color'] ?>', 
                                                '<?= $row['alineacion'] ?>',
                                                <?= $row['escala_x'] ?>, <?= $row['escala_y'] ?>, <?= $row['escala_z'] ?>,
                                                <?= $row['pos_x'] ?>, <?= $row['pos_y'] ?>, <?= $row['pos_z'] ?>,
                                                '<?= addslashes($row['archivo_audio']) ?>'
                                            )">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este marcador? Se borrará permanentemente.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_marcador" value="<?= $row['id_marcador'] ?>">
                                            <button type="submit" class="btn-action btn-delete" title="Eliminar"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #64748b;">
                    <i class="fas fa-folder-open" style="font-size: 3rem; margin-bottom: 10px; color: #cbd5e1;"></i>
                    <h3>Aún no tienes marcadores</h3>
                    <p>Haz clic en "Nuevo Marcador" para empezar a crear experiencias AR.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">&times;</button>
            <h2 style="margin-bottom: 20px; color: #023675;"><i class="fas fa-pen"></i> Editar Marcador</h2>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id_marcador" id="edit_id">
                
                <div class="form-group">
                    <label>Texto Flotante</label>
                    <input type="text" name="texto_modelo" id="edit_texto" class="form-control" required>
                </div>
                
                <div class="grid-3" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label>Color del Texto</label>
                        <select name="color" id="edit_color" class="form-control">
                            <option value="yellow">Amarillo</option>
                            <option value="red">Rojo</option>
                            <option value="blue">Azul</option>
                            <option value="green">Verde</option>
                            <option value="white">Blanco</option>
                            <option value="black">Negro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Alineación</label>
                        <select name="alineacion" id="edit_align" class="form-control">
                            <option value="center">Centro</option>
                            <option value="left">Izquierda</option>
                            <option value="right">Derecha</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Escala (X Y Z)</label>
                    <div class="grid-3">
                        <input type="number" name="escala_x" id="edit_sx" class="form-control" step="0.01" required>
                        <input type="number" name="escala_y" id="edit_sy" class="form-control" step="0.01" required>
                        <input type="number" name="escala_z" id="edit_sz" class="form-control" step="0.01" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Posición Texto (X Y Z)</label>
                    <div class="grid-3">
                        <input type="number" name="pos_x" id="edit_px" class="form-control" step="0.1" required>
                        <input type="number" name="pos_y" id="edit_py" class="form-control" step="0.1" required>
                        <input type="number" name="pos_z" id="edit_pz" class="form-control" step="0.1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Audio Actual</label>
                    <div id="audio_actual_info" class="audio-info"></div>
                </div>

                <div class="form-group">
                    <label>Cambiar Audio (opcional)</label>
                    <input type="file" name="nuevo_audio" class="form-control" accept=".mp3,.wav,.ogg,.m4a">
                    <small style="color:#64748b;">Formatos permitidos: MP3, WAV, OGG, M4A</small>
                </div>

                <button type="submit" class="btn-save">GUARDAR CAMBIOS</button>
            </form>
        </div>
    </div>

    <script>
        // Abrir modal y llenar datos
        function openEditModal(id, txt, col, align, sx, sy, sz, px, py, pz, audio) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_texto').value = txt;
            document.getElementById('edit_color').value = col;
            document.getElementById('edit_align').value = align;
            document.getElementById('edit_sx').value = sx;
            document.getElementById('edit_sy').value = sy;
            document.getElementById('edit_sz').value = sz;
            document.getElementById('edit_px').value = px;
            document.getElementById('edit_py').value = py;
            document.getElementById('edit_pz').value = pz;
            
            // Mostrar información del audio actual
            const audioInfo = document.getElementById('audio_actual_info');
            if (audio && audio != 'NULL' && audio != '') {
                const audioName = audio.split('/').pop();
                audioInfo.innerHTML = '<i class="fas fa-music"></i> ' + audioName;
            } else {
                audioInfo.innerHTML = '<i class="fas fa-music"></i> Sin audio';
            }
            
            document.getElementById('editModal').classList.add('show');
        }

        // Cerrar modal
        function closeModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        // Cerrar modal haciendo clic afuera
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>