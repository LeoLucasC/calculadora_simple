<?php
include 'includes/db.php';
include 'includes/seguridad.php'; 

// Validar que no sea Admin
if ($_SESSION['id_rol'] == 1) { header("Location: dashboard_admin.php"); exit(); }
// Cargar selectores (FILTRADOS: Solo donde el usuario tenga nivel_acceso = 'subir')
$id_user = $_SESSION['id_usuario'];

$ubicaciones = $conn->query("
    SELECT DISTINCT u.id_ubicacion, u.nombre 
    FROM ubicaciones u 
    INNER JOIN permisos_usuarios pu ON u.id_ubicacion = pu.id_ubicacion 
    WHERE u.estado=1 AND pu.id_usuario = $id_user AND pu.nivel_acceso = 'subir' 
    ORDER BY u.nombre
");

$grupos = $conn->query("
    SELECT g.id_grupo, g.nombre, g.id_ubicacion 
    FROM grupos_operativos g 
    INNER JOIN permisos_usuarios pu ON g.id_grupo = pu.id_grupo 
    WHERE g.estado=1 AND pu.id_usuario = $id_user AND pu.nivel_acceso = 'subir' 
    ORDER BY g.nombre
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Subir Evidencia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Estilos generales */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1e293b; display: flex; min-height: 100vh; }
        
        /* Contenido principal */
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        @media (max-width: 768px) { .main-content { padding: 80px 20px 30px; } }

        /* Header */
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 2rem; font-weight: 600; color: #0f172a; margin-bottom: 8px; }
        .page-header p { color: #64748b; font-size: 1rem; }

        /* Formulario */
        .form-container { max-width: 700px; margin: 0 auto; background: white; border-radius: 20px; padding: 35px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); border: 1px solid #e9eef2; }
        .form-container h2 { font-size: 1.8rem; font-weight: 600; color: #0f172a; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .form-container h2 i { color: #023675; }
        .form-subtitle { color: #64748b; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e9eef2; }
        
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #1e293b; }
        .form-group label i { color: #023675; margin-right: 8px; width: 18px; }
        .required::after { content: " *"; color: #ef4444; font-weight: 600; }

        .form-control { width: 100%; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.95rem; color: #1e293b; transition: 0.3s; }
        .form-control:focus { outline: none; border-color: #023675; background: white; }
        .form-control:disabled { background: #e2e8f0; cursor: not-allowed; opacity: 0.7; }
        
        /* Checkbox bonito */
        .checkbox-group { display: flex; align-items: center; gap: 12px; padding: 10px 0; }
        .checkbox-group input { width: 18px; height: 18px; accent-color: #023675; cursor: pointer; }
        .checkbox-group label { margin: 0; cursor: pointer; }

        /* Input File */
        .file-input-wrapper { position: relative; margin-top: 8px; }
        .file-input-wrapper input[type="file"] { width: 100%; padding: 40px 20px; background: #f8fafc; border: 2px dashed #e2e8f0; border-radius: 12px; cursor: pointer; color: transparent; }
        .file-input-wrapper::before { content: var(--file-name, "📁 Haz clic o arrastra archivos aquí"); position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #64748b; pointer-events: none; }
        .file-info { font-size: 0.85rem; color: #94a3b8; margin-top: 8px; }

        /* NUEVO: BARRA DE PROGRESO */
        #progressContainer { display: none; margin-top: 25px; }
        .progress-bar-bg { width: 100%; background: #e2e8f0; border-radius: 10px; height: 15px; overflow: hidden; margin-top: 5px; }
        .progress-bar-fill { height: 100%; background: #10b981; width: 0%; transition: width 0.2s; }
        .progress-text { display: flex; justify-content: space-between; font-size: 0.85rem; color: #64748b; font-weight: 500; }

        /* Botones */
        .btn-submit { width: 100%; padding: 16px; background: #023675; color: white; border: none; border-radius: 12px; font-size: 1rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 30px; transition: 0.3s; }
        .btn-submit:hover { background: #0347a3; transform: translateY(-2px); }
        .btn-submit:disabled { background: #94a3b8; cursor: not-allowed; transform: none; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #64748b; text-decoration: none; margin-top: 20px; font-size: 0.95rem; }
        .back-link:hover { color: #023675; }
    </style>
</head>
<body>

    <?php include 'includes/navbar_cliente.php'; ?>

    <div class="main-content">
        <div id="alertContainer">
            <?php if(isset($_GET['status'])): ?>
                <?php if($_GET['status'] == 'ok'): ?>
                    <div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #10b981;">
                        <i class="fas fa-check-circle"></i> ¡Archivo subido exitosamente!
                    </div>
                <?php else: ?>
                    <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #ef4444;">
                        <i class="fas fa-times-circle"></i> Error: <?php echo htmlspecialchars($_GET['msg'] ?? 'Ocurrió un problema'); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="form-container">
            <h2><i class="fas fa-cloud-upload-alt"></i> Registro Multimedia</h2>
            <div class="form-subtitle">Complete los detalles de la evidencia a subir</div>
            
            <form id="uploadForm" enctype="multipart/form-data">
                
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

                <div class="form-group">
                    <label><i class="fas fa-file"></i>Tipo de Archivo</label>
                    <select name="tipo_archivo" id="select_tipo_archivo" class="form-control">
                        <option value="FOTO">📸 Fotografía Estándar</option>
                        <option value="VIDEO">🎥 Video de Inspección</option>
                        <option value="MODELO_3D">🧊 Modelo 3D (.GLB)</option>
                    </select>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="es_360" value="1" id="es_360">
                        <label for="es_360"><i class="fas fa-vr-cardboard"></i> ¿Es contenido 360° / VR?</label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="required"><i class="fas fa-camera"></i>Archivo</label>
                    <div class="file-input-wrapper">
                        <input type="file" name="archivo" id="archivoInput" class="form-control" required accept="image/*,video/*,.pdf,.glb">
                    </div>
                    <div class="file-info">Máx. 50MB. Formatos: JPG, PNG, MP4.</div>
                </div>

                <div class="form-group">
                    <label id="label_obs"><i class="fas fa-pencil-alt"></i>Observaciones</label>
                    <textarea name="observaciones" id="input_obs" class="form-control" rows="3" placeholder="Detalles adicionales..."></textarea>
                </div>

                <div id="progressContainer">
                    <div class="progress-text">
                        <span id="progressStatus">Subiendo archivo...</span>
                        <span id="progressPercent">0%</span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" id="progressBarFill"></div>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="btnSubmit">
                    <i class="fas fa-cloud-upload-alt"></i> SUBIR A LA NUBE
                </button>
            </form>
            
            <a href="dashboard_cliente.php" class="back-link"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
        </div>
    </div>

    <script>
        // 1. Mostrar nombre del archivo
        document.querySelector('input[type="file"]').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                this.parentElement.style.setProperty('--file-name', `"✅ ${fileName}"`);
                this.parentElement.style.borderColor = "#023675";
            }
        });


       
        // NUEVO: Auto-marcar 360 y requerir Observaciones si es Modelo 3D
        document.getElementById('select_tipo_archivo').addEventListener('change', function() {
            const checkbox360 = document.getElementById('es_360');
            const labelObs = document.getElementById('label_obs');
            const inputObs = document.getElementById('input_obs');
            
            if (this.value === 'MODELO_3D') {
                checkbox360.checked = true; // Marca el 360
                
                // Hace obligatoria la descripción
                inputObs.required = true;
                labelObs.classList.add('required'); // Le pone el asterisco rojo al título
                inputObs.placeholder = "Para Modelos 3D, la descripción es obligatoria...";
                
            } else {
                checkbox360.checked = false; // Desmarca el 360
                
                // Vuelve a hacer opcional la descripción
                inputObs.required = false;
                labelObs.classList.remove('required'); // Le quita el asterisco rojo
                inputObs.placeholder = "Detalles adicionales...";
            }
        });

        // 2. Lógica del selector en cascada (Grupos por Ubicación)
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

        // ==========================================
        // NUEVA LÓGICA AJAX CON BARRA DE PROGRESO
        // ==========================================
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Evitamos que la página se recargue
            
            // Validar que hay archivo
            const fileInput = document.getElementById('archivoInput');
            if (fileInput.files.length === 0) return;

            // Elementos de la UI
            const btnSubmit = document.getElementById('btnSubmit');
            const progressContainer = document.getElementById('progressContainer');
            const progressBarFill = document.getElementById('progressBarFill');
            const progressPercent = document.getElementById('progressPercent');
            const progressStatus = document.getElementById('progressStatus');
            const alertContainer = document.getElementById('alertContainer');

            // Preparamos los datos del formulario
            const formData = new FormData(this);

            // Configuramos la petición AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'guardar_media.php', true);

            // Escuchamos el progreso de la subida
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    progressBarFill.style.width = percentComplete + '%';
                    progressPercent.textContent = percentComplete + '%';
                    
                    if (percentComplete === 100) {
                        progressStatus.textContent = "Procesando y guardando en BD... espera";
                        progressBarFill.style.background = "#023675"; // Cambia color a azul cuando termina de subir pero falta procesar
                    }
                }
            };

            // Cuando termina la petición (Éxito o Error del servidor)
            xhr.onload = function() {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> SUBIR A LA NUBE';
                
                // guardar_media.php hace un header("Location: ...")
                // Como estamos en AJAX, capturamos esa redirección en la URL final del response
                const responseURL = xhr.responseURL;
                
                if (responseURL.includes('status=ok')) {
                    // Éxito
                    alertContainer.innerHTML = `
                        <div style="background:#d1fae5; color:#065f46; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #10b981;">
                            <i class="fas fa-check-circle"></i> ¡Archivo subido exitosamente!
                        </div>`;
                    document.getElementById('uploadForm').reset();
                    document.querySelector('.file-input-wrapper').style.setProperty('--file-name', '"📁 Haz clic o arrastra archivos aquí"');
                    document.querySelector('.file-input-wrapper').style.borderColor = "#e2e8f0";
                    progressContainer.style.display = 'none';
                    
                    // Opcional: Redirigir al dashboard después de 2 segundos
                    // setTimeout(() => { window.location.href = "dashboard_cliente.php"; }, 2000);
                } else {
                    // Si falló (la url tiene status=error)
                    const urlParams = new URL(responseURL).searchParams;
                    const msg = urlParams.get('msg') || "Error desconocido en el servidor";
                    
                    alertContainer.innerHTML = `
                        <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #ef4444;">
                            <i class="fas fa-times-circle"></i> Error: ${msg}
                        </div>`;
                    progressContainer.style.display = 'none';
                }
            };

            // Error de conexión (se cae internet, etc)
            xhr.onerror = function() {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> REINTENTAR SUBIDA';
                progressStatus.textContent = "Error de conexión";
                progressBarFill.style.background = "#ef4444"; // Rojo error
                alertContainer.innerHTML = `
                        <div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid #ef4444;">
                            <i class="fas fa-wifi"></i> Se perdió la conexión con el servidor.
                        </div>`;
            };

            // INICIAMOS LA SUBIDA
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subiendo...';
            progressContainer.style.display = 'block';
            progressBarFill.style.width = '0%';
            progressBarFill.style.background = '#10b981';
            progressPercent.textContent = '0%';
            progressStatus.textContent = "Subiendo archivo...";
            alertContainer.innerHTML = ''; // Limpiamos alertas previas
            
            xhr.send(formData);
        });






    </script>
</body>
</html>