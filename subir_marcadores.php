<?php
session_start();
include 'includes/db.php';
include 'includes/seguridad.php'; 

// Validar que no sea Admin
if ($_SESSION['id_rol'] == 1) { header("Location: dashboard_admin.php"); exit(); }

$id_user = $_SESSION['id_usuario'];
$mensaje = '';

// --- PROCESAR FORMULARIO BACKEND ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_ubicacion = intval($_POST['id_ubicacion']);
    $id_grupo     = intval($_POST['id_grupo']);
    $id_modelo_3d = intval($_POST['id_modelo_3d']);
    $escala_x     = floatval($_POST['escala_x']);
    $escala_y     = floatval($_POST['escala_y']);
    $escala_z     = floatval($_POST['escala_z']);
    $pos_x        = floatval($_POST['pos_x']);
    $pos_y        = floatval($_POST['pos_y']);
    $pos_z        = floatval($_POST['pos_z']);
    $texto_modelo = $conn->real_escape_string($_POST['texto_modelo']);
    $ancho_texto  = floatval($_POST['ancho_texto']);
    $alineacion   = $conn->real_escape_string($_POST['alineacion']);
    $color        = $conn->real_escape_string($_POST['color']);

    $directorio = 'uploads/';
    if (!file_exists($directorio)) { mkdir($directorio, 0777, true); }

    // Subir Marcador .patt (Obligatorio)
    $ruta_patt = '';
    if (isset($_FILES['archivo_patt']) && $_FILES['archivo_patt']['error'] == 0) {
        $nombre_patt = time() . '_marcador_' . basename($_FILES['archivo_patt']['name']);
        $ruta_patt = $directorio . $nombre_patt;
        move_uploaded_file($_FILES['archivo_patt']['tmp_name'], $ruta_patt);
    }

    // Subir Audio .mp3 (Opcional)
    $ruta_audio = NULL;
    if (isset($_FILES['archivo_audio']) && $_FILES['archivo_audio']['error'] == 0) {
        $nombre_audio = time() . '_audio_' . basename($_FILES['archivo_audio']['name']);
        $ruta_dest_audio = $directorio . $nombre_audio;
        if (move_uploaded_file($_FILES['archivo_audio']['tmp_name'], $ruta_dest_audio)) {
            $ruta_audio = "'$ruta_dest_audio'"; 
        }
    } else {
        $ruta_audio = "NULL"; 
    }

    if (!empty($ruta_patt)) {
        $sql_insert = "INSERT INTO marcadores_ar 
            (id_usuario, id_ubicacion, id_grupo, id_modelo_3d, archivo_patt, escala_x, escala_y, escala_z, pos_x, pos_y, pos_z, texto_modelo, ancho_texto, alineacion, color, archivo_audio) 
            VALUES 
            ($id_user, $id_ubicacion, $id_grupo, $id_modelo_3d, '$ruta_patt', $escala_x, $escala_y, $escala_z, $pos_x, $pos_y, $pos_z, '$texto_modelo', $ancho_texto, '$alineacion', '$color', $ruta_audio)";

        if ($conn->query($sql_insert)) {
            $mensaje = '<div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #10b981;"><i class="fas fa-check-circle"></i> ¡Marcador AR configurado exitosamente!</div>';
        } else {
            $mensaje = '<div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #ef4444;"><i class="fas fa-times-circle"></i> Error de base de datos.</div>';
        }
    } else {
        $mensaje = '<div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #ef4444;"><i class="fas fa-times-circle"></i> Debe subir un archivo .patt válido.</div>';
    }
}

// --- OBTENER DATOS PARA LOS SELECTS ---
$sql_ubi = "
    SELECT DISTINCT u.id_ubicacion, u.nombre 
    FROM ubicaciones u 
    INNER JOIN permisos_usuarios pu ON u.id_ubicacion = pu.id_ubicacion 
    WHERE u.estado=1 AND pu.id_usuario = $id_user AND pu.nivel_acceso = 'subir' 
    ORDER BY u.nombre
";
$ubicaciones = $conn->query($sql_ubi);

// --- INICIO DE LOGS PARA CONSOLA ---
$datos_log = [];
if ($ubicaciones && $ubicaciones->num_rows > 0) {
    while($r = $ubicaciones->fetch_assoc()) {
        $datos_log[] = $r;
    }
    $ubicaciones->data_seek(0); // Reiniciamos el puntero para que el <select> HTML funcione bien abajo
}

echo "<script>
    console.log('--- DEBUG DE UBICACIONES ---');
    console.log('ID Usuario actual:', $id_user);
    console.log('SQL Ejecutado:', `" . trim(preg_replace('/\s+/', ' ', $sql_ubi)) . "`);
    console.log('Total encontradas:', " . ($ubicaciones ? $ubicaciones->num_rows : 0) . ");
    console.log('Datos:', " . json_encode($datos_log) . ");
</script>";
// --- FIN DE LOGS ---

$grupos = $conn->query("
    SELECT g.id_grupo, g.nombre, g.id_ubicacion 
    FROM grupos_operativos g 
    INNER JOIN permisos_usuarios pu ON g.id_grupo = pu.id_grupo 
    WHERE g.estado=1 AND pu.id_usuario = $id_user AND pu.nivel_acceso = 'subir' 
    ORDER BY g.nombre
");

// Extraer los modelos 3D que ya existen en la tabla multimedia
$modelos_3d = $conn->query("
    SELECT id_multimedia, observaciones 
    FROM multimedia 
    WHERE tipo_archivo = 'MODELO_3D' AND id_usuario = $id_user
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Subir Marcador</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* (Conserva tus estilos CSS originales exactamente iguales aquí) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1e293b; display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        @media (max-width: 768px) { .main-content { padding: 80px 20px 30px; } }
        .form-wrapper { max-width: 800px; margin: 0 auto; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 2rem; font-weight: 600; color: #0f172a; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .page-header h1 i { color: #023675; }
        .page-header p { color: #64748b; font-size: 1rem; }
        .form-container { background: white; border-radius: 20px; padding: 35px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); border: 1px solid #e9eef2; }
        .form-title { font-size: 1.5rem; font-weight: 600; color: #0f172a; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; padding-bottom: 15px; border-bottom: 1px solid #e9eef2; }
        .form-title i { color: #023675; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #1e293b; font-size: 0.95rem; }
        .form-group label i { color: #023675; margin-right: 8px; width: 18px; }
        .required::after { content: " *"; color: #ef4444; font-weight: 600; }
        .form-control { width: 100%; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.95rem; color: #1e293b; transition: 0.2s; font-family: 'Inter', sans-serif; }
        .form-control:focus { outline: none; border-color: #023675; background: white; }
        .form-control:disabled { background: #e2e8f0; cursor: not-allowed; opacity: 0.7; }
        select.form-control { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 16px center; background-size: 16px; }
        .file-input-wrapper { position: relative; margin-top: 5px; }
        .file-input-wrapper input[type="file"] { width: 100%; padding: 35px 20px; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px; cursor: pointer; color: transparent; position: relative; }
        
        .file-input-wrapper::before { content: var(--file-name, attr(data-text)); position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #64748b; pointer-events: none; text-align: center; width: 100%; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 20px; z-index: 10; }
        .file-info { font-size: 0.8rem; color: #94a3b8; margin-top: 6px; display: flex; align-items: center; gap: 5px; }
        .file-info i { font-size: 0.75rem; color: #94a3b8; }
        .btn-submit { width: 100%; padding: 16px; background: #023675; color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 30px; transition: 0.2s; font-family: 'Inter', sans-serif; letter-spacing: 0.5px; }
        .btn-submit:hover { background: #0347a3; }
        .btn-submit i { font-size: 1.1rem; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; margin-top: 20px; font-size: 0.95rem; transition: 0.2s; }
        .back-link:hover { color: #023675; }
        .help-text { font-size: 0.8rem; color: #94a3b8; margin-top: 5px; }
        @media (max-width: 640px) { .grid-2, .grid-3 { grid-template-columns: 1fr; gap: 15px; } .form-container { padding: 25px; } .file-input-wrapper::before { font-size: 0.85rem; white-space: normal; } }
    </style>
</head>
<body>

    <?php include 'includes/navbar_cliente.php'; ?>

    <div class="main-content">
        <div class="form-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-qrcode"></i> Subir Marcador AR</h1>
                <p>Configure los detalles del marcador y modelo 3D</p>
            </div>
            
            <?= $mensaje ?>

            <div class="form-container">
                <div class="form-title">
                    <i class="fas fa-cog"></i> Configuración del Marcador
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="required"><i class="fas fa-map-marker-alt"></i>Ubicación</label>
                            <select name="id_ubicacion" id="select_ubicacion" class="form-control" required>
                                <option value="">Seleccione una zona...</option>
                                <?php while($row = $ubicaciones->fetch_assoc()): ?>
                                    <option value="<?= $row['id_ubicacion'] ?>"><?= htmlspecialchars($row['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="required"><i class="fas fa-users"></i>Grupo Operativo</label>
                            <select name="id_grupo" id="select_grupo" class="form-control" required disabled>
                                <option value="">Primero seleccione una ubicación...</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="required"><i class="fas fa-cube"></i>Modelo 3D (Subidos previamente)</label>
                            <select name="id_modelo_3d" class="form-control" required>
                                <option value="">Seleccione un modelo 3D...</option>
                                <?php 
                                // Consultamos los modelos reales del usuario actual
                                $modelos_3d = $conn->query("SELECT id_multimedia, observaciones FROM multimedia WHERE tipo_archivo = 'MODELO_3D' AND id_usuario = $id_user");
                                
                                if($modelos_3d && $modelos_3d->num_rows > 0):
                                    while($m3d = $modelos_3d->fetch_assoc()): 
                                ?>
                                        <option value="<?= $m3d['id_multimedia'] ?>">
                                            🧊 <?= htmlspecialchars($m3d['observaciones'] ?: 'Modelo sin descripción (ID: '.$m3d['id_multimedia'].')') ?>
                                        </option>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                    <option value="" disabled>No ha subido ningún Modelo 3D aún</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="required"><i class="fas fa-file-image"></i>Marcador (.patt)</label>
                            <div class="file-input-wrapper" data-text="📁 Haz clic o arrastra archivo .patt aquí">
                                <input type="file" name="archivo_patt" id="input_patt" class="form-control" accept=".patt" required>
                            </div>
                            <div class="file-info"><i class="fas fa-info-circle"></i> Archivo patrón .patt (máx. 2MB)</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="required"><i class="fas fa-expand"></i>Escala (X Y Z)</label>
                        <div class="grid-3">
                            <input type="number" name="escala_x" class="form-control" step="0.01" value="0.2" placeholder="X" required>
                            <input type="number" name="escala_y" class="form-control" step="0.01" value="0.2" placeholder="Y" required>
                            <input type="number" name="escala_z" class="form-control" step="0.01" value="0.2" placeholder="Z" required>
                        </div>
                        <div class="help-text">Valores predeterminados: 0.2 0.2 0.2</div>
                    </div>

                    <div class="form-group">
                        <label class="required"><i class="fas fa-arrows-alt"></i>Posición del texto (X Y Z)</label>
                        <div class="grid-3">
                            <input type="number" name="pos_x" class="form-control" step="0.1" value="0" placeholder="X" required>
                            <input type="number" name="pos_y" class="form-control" step="0.1" value="5" placeholder="Y" required>
                            <input type="number" name="pos_z" class="form-control" step="0.1" value="0" placeholder="Z" required>
                        </div>
                        <div class="help-text">Valores predeterminados: 0 5 0</div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="required"><i class="fas fa-font"></i>Texto del modelo</label>
                            <input type="text" name="texto_modelo" class="form-control" value="ESCAVADORA 2026" placeholder="Ej: ESCAVADORA 2026" required>
                        </div>

                        <div class="form-group">
                            <label class="required"><i class="fas fa-arrows-alt-h"></i>Ancho (width)</label>
                            <input type="number" name="ancho_texto" class="form-control" step="0.1" value="4" placeholder="Ancho del texto" required>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="required"><i class="fas fa-align-left"></i>Alineación</label>
                            <select name="alineacion" class="form-control" required>
                                <option value="center" selected>Centro</option>
                                <option value="left">Izquierda</option>
                                <option value="right">Derecha</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="required"><i class="fas fa-palette"></i>Color</label>
                            <select name="color" class="form-control" required>
                                <option value="yellow" selected>🟡 Amarillo</option>
                                <option value="red">🔴 Rojo</option>
                                <option value="blue">🔵 Azul</option>
                                <option value="green">🟢 Verde</option>
                                <option value="white">⚪ Blanco</option>
                                <option value="black">⚫ Negro</option>
                                <option value="orange">🟠 Naranja</option>
                                <option value="purple">🟣 Púrpura</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-music"></i>Sonido (opcional - .mp3)</label>
                        <div class="file-input-wrapper" data-text="📁 Subir sonido MP3 (opcional)">
                            <input type="file" name="archivo_audio" id="input_audio" class="form-control" accept=".mp3,audio/mpeg">
                        </div>
                        <div class="file-info"><i class="fas fa-info-circle"></i> Archivo MP3 (máx. 10MB) - Opcional</div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> GUARDAR MARCADOR
                    </button>
                </form>
                
                <a href="dashboard_cliente.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Volver al Panel
                </a>
            </div>
        </div>
    </div>

    <script>
        
        // Lógica de ubicaciones a grupos
        const todosLosGrupos = [
            <?php 
            if($grupos && $grupos->num_rows > 0) {
                $grupos->data_seek(0);
                while($row = $grupos->fetch_assoc()) {
                    echo "{ id: " . $row['id_grupo'] . ", nombre: '" . addslashes($row['nombre']) . "', id_ubicacion: " . $row['id_ubicacion'] . " },\n";
                }
            }
            ?>
        ];

        document.getElementById('select_ubicacion').addEventListener('change', function() {
            const ubicacionSeleccionada = this.value;
            const selectGrupo = document.getElementById('select_grupo');
            selectGrupo.innerHTML = '';

            if (ubicacionSeleccionada) {
                selectGrupo.disabled = false;
                const optionDefault = document.createElement('option');
                optionDefault.value = "";
                optionDefault.textContent = "Seleccione un grupo...";
                selectGrupo.appendChild(optionDefault);

                const gruposFiltrados = todosLosGrupos.filter(grupo => grupo.id_ubicacion == ubicacionSeleccionada);
                
                if (gruposFiltrados.length > 0) {
                    gruposFiltrados.forEach(grupo => {
                        const option = document.createElement('option');
                        option.value = grupo.id;
                        option.textContent = grupo.nombre;
                        selectGrupo.appendChild(option);
                    });
                } else {
                    optionDefault.textContent = "No hay grupos asignados a esta zona";
                    selectGrupo.disabled = true;
                }
            } else {
                selectGrupo.disabled = true;
                const optionDefault = document.createElement('option');
                optionDefault.value = "";
                optionDefault.textContent = "Primero seleccione una ubicación...";
                selectGrupo.appendChild(optionDefault);
            }
        });

       // Cambiar texto al seleccionar archivo PATT y Audio
       // Cambiar texto al seleccionar archivo PATT
        document.getElementById('input_patt').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                this.parentElement.style.setProperty('--file-name', `"✅ ${fileName}"`);
                this.style.borderColor = "#023675";
                this.style.borderStyle = "solid";
            } else {
                this.parentElement.style.setProperty('--file-name', '""');
                this.style.borderColor = "#e2e8f0";
                this.style.borderStyle = "dashed";
            }
        });

        // Cambiar texto al seleccionar archivo Audio
        document.getElementById('input_audio').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                this.parentElement.style.setProperty('--file-name', `"🎵 ${fileName}"`);
                this.style.borderColor = "#10b981";
                this.style.borderStyle = "solid";
            } else {
                this.parentElement.style.setProperty('--file-name', '"📁 Subir sonido MP3 (opcional)"');
                this.style.borderColor = "#e2e8f0";
                this.style.borderStyle = "dashed";
            }
        });
    </script>
</body>
</html>