<?php
// Nota: Esta es una versión simulada para mostrar la interfaz.
// Reemplaza las partes de "simulación" con tu lógica real de PHP y SQL.
session_start();
include 'includes/db.php'; // Asegúrate de que la ruta sea correcta
include 'includes/seguridad.php';

$id_user = $_SESSION['id_usuario'];

// --- 1. Extraer a qué empresa pertenece el usuario ---
$q_empresa = $conn->query("SELECT id_cliente FROM usuarios WHERE id_usuario = $id_user");
$id_cliente = $q_empresa->fetch_assoc()['id_cliente'];
$mensaje = '';

// --- 2. Traer ubicaciones de la empresa ---
$sql_ubi = "SELECT u.id_ubicacion, u.nombre 
            FROM ubicaciones u 
            INNER JOIN operaciones o ON u.id_operacion = o.id_operacion 
            WHERE o.id_cliente = $id_cliente AND u.estado = 1";
$res_ubi = $conn->query($sql_ubi);
$ubicaciones = [];
if($res_ubi) { while($row = $res_ubi->fetch_assoc()) { $ubicaciones[] = $row; } }

// --- 3. Traer grupos de la empresa ---
$sql_grupos = "SELECT g.id_grupo, g.nombre, g.id_ubicacion 
               FROM grupos_operativos g 
               INNER JOIN ubicaciones u ON g.id_ubicacion = u.id_ubicacion 
               INNER JOIN operaciones o ON u.id_operacion = o.id_operacion 
               WHERE o.id_cliente = $id_cliente AND g.estado = 1";
$res_grupos = $conn->query($sql_grupos);
$grupos = [];
if($res_grupos) { while($row = $res_grupos->fetch_assoc()) { $grupos[] = $row; } }

// --- 4. Traer Multimedia (Fotos 360, Videos 360 y Modelos 3D) ---
$sql_multimedia = "SELECT m.id_multimedia, m.observaciones, m.tipo_archivo, m.es_360 
                   FROM multimedia m
                   INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                   WHERE u.id_cliente = $id_cliente";
$res_multi = $conn->query($sql_multimedia);

$fotos_360 = [];
$videos_360 = [];
$modelos_3d = [];

if($res_multi) {
    while($row = $res_multi->fetch_assoc()) {
        if($row['tipo_archivo'] == 'FOTO' && $row['es_360'] == 1) {
            $fotos_360[] = $row;
        } elseif($row['tipo_archivo'] == 'VIDEO' && $row['es_360'] == 1) {
            $videos_360[] = $row;
        } elseif($row['tipo_archivo'] == 'MODELO_3D') {
            $modelos_3d[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Editor XR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7fb;
            color: #1e293b;
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 30px 40px;
            overflow-y: auto;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 80px 20px 30px;
            }
        }

        .form-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: #023675;
        }

        .page-header p {
            color: #64748b;
            font-size: 1rem;
        }

        .form-container {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9eef2;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9eef2;
        }

        .form-title i {
            color: #023675;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        /* Grid específico para los items multimedia */
        .grid-multimedia {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1e293b;
            font-size: 0.95rem;
        }

        .form-group label i {
            color: #023675;
            margin-right: 8px;
            width: 18px;
        }

        .required::after {
            content: " *";
            color: #ef4444;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            color: #1e293b;
            transition: 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #023675;
            background: white;
        }

        .form-control:disabled {
            background: #e2e8f0;
            cursor: not-allowed;
            opacity: 0.7;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
        }

        /* Botón EDITAR XR */
        .btn-editar-xr {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #1e293b;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
            height: 46px; /* Para alinear con el select */
        }

        .btn-editar-xr i {
            color: #023675;
            font-size: 0.9rem;
        }

        .btn-editar-xr:hover {
            background: #e2e8f0;
            border-color: #94a3b8;
            transform: translateY(-1px);
        }

        .btn-editar-xr:active {
            transform: translateY(0);
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: #023675;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 30px;
            transition: 0.2s;
            font-family: 'Inter', sans-serif;
            letter-spacing: 0.5px;
        }

        .btn-submit:hover {
            background: #0347a3;
        }

        .btn-submit i {
            font-size: 1.1rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            margin-top: 20px;
            font-size: 0.95rem;
            transition: 0.2s;
        }

        .back-link:hover {
            color: #023675;
        }

        .help-text {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-top: 5px;
        }

        hr {
            border: none;
            border-top: 1px solid #e9eef2;
            margin: 25px 0;
        }

        @media (max-width: 640px) {
            .grid-2 {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            .grid-multimedia {
                grid-template-columns: 1fr;
            }
            .btn-editar-xr {
                width: 100%;
                justify-content: center;
            }
            .form-container {
                padding: 25px;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar_cliente.php'; ?>
    <!-- Si no tienes el navbar, comenta la línea de arriba y descomenta la de abajo para probar -->
    <!-- <div style="padding: 20px; background: #023675; color:white; text-align:center;">Navbar Simulado</div> -->

    <div class="main-content">
        <div class="form-wrapper">
            <div class="page-header">
                <h1><i class="fas fa-vr-cardboard"></i> ⚙️ EDITOR XR</h1>
                <p>Configure los recursos multimedia y sus propiedades de realidad extendida</p>
            </div>

            <?= $mensaje ?>

            <div class="form-container">
                <div class="form-title">
                    <i class="fas fa-sliders-h"></i> Configuración de Escena XR
                </div>

                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- SECCIÓN: UBICACIÓN Y GRUPO (IGUAL QUE EL EJEMPLO) -->
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="required"><i class="fas fa-map-marker-alt"></i>📍 Ubicación</label>
                            <select name="id_ubicacion" id="select_ubicacion" class="form-control" required>
                                <option value="">Seleccione una zona...</option>
                                <?php foreach ($ubicaciones as $row): ?>
                                    <option value="<?= $row['id_ubicacion'] ?>"><?= htmlspecialchars($row['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="required"><i class="fas fa-users"></i>👥 Grupo Operativo</label>
                            <select name="id_grupo" id="select_grupo" class="form-control" required disabled>
                                <option value="">Primero seleccione una ubicación...</option>
                            </select>
                        </div>
                    </div>

                    <hr>

                    <!-- SECCIÓN: FOTO 360 CON BOTÓN EDITAR XR -->
                    <div class="grid-multimedia">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label><i class="fas fa-camera"></i>📸 FOTO 360</label>
                            <select id="select_foto_360" class="form-control">
                                <option value="">Seleccione una foto 360°...</option>
                                <?php foreach ($fotos_360 as $foto): ?>
                                    <option value="<?= $foto['id_multimedia'] ?>">
                                        🌄 <?= htmlspecialchars($foto['observaciones'] ?: 'Foto ID: '.$foto['id_multimedia']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn-editar-xr" onclick="irAEditor('foto')">
                            <i class="fas fa-pen"></i> EDITAR XR
                        </button>
                    </div>

                    <div class="grid-multimedia">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label><i class="fas fa-video"></i>🎥 VIDEO 360</label>
                            <select id="select_video_360" class="form-control">
                                <option value="">Seleccione un video 360°...</option>
                                <?php foreach ($videos_360 as $video): ?>
                                    <option value="<?= $video['id_multimedia'] ?>">
                                        🎬 <?= htmlspecialchars($video['observaciones'] ?: 'Video ID: '.$video['id_multimedia']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn-editar-xr" onclick="irAEditor('video')">
                            <i class="fas fa-pen"></i> EDITAR XR
                        </button>
                    </div>

                    <div class="grid-multimedia">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label><i class="fas fa-cube"></i>🖥️ Modelo 3D</label>
                            <select id="select_modelo_3d" class="form-control">
                                <option value="">Seleccione un modelo 3D...</option>
                                <?php foreach ($modelos_3d as $m3d): ?>
                                    <option value="<?= $m3d['id_multimedia'] ?>">
                                        🧊 <?= htmlspecialchars($m3d['observaciones'] ?: 'Modelo ID: '.$m3d['id_multimedia']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="btn-editar-xr" onclick="irAEditor('modelo')">
                            <i class="fas fa-pen"></i> EDITAR XR
                        </button>
                    </div>

                   

                    <!-- Puedes agregar más campos si es necesario, por ejemplo: -->
                    <!--
                    <div class="form-group">
                        <label><i class="fas fa-music"></i> Sonido ambiente (opcional)</label>
                        <input type="file" class="form-control" accept=".mp3">
                    </div>
                    -->

        
                </form>

                <a href="dashboard_cliente.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Volver al Panel
                </a>
            </div>
        </div>
    </div>

    <script>
        // Lógica de ubicaciones a grupos (exactamente igual que en tu ejemplo)
        const todosLosGrupos = [
            <?php foreach ($grupos as $row): ?>
            { id: <?= $row['id_grupo'] ?>, nombre: '<?= addslashes($row['nombre']) ?>', id_ubicacion: <?= $row['id_ubicacion'] ?> },
            <?php endforeach; ?>
        ];

        const selectUbicacion = document.getElementById('select_ubicacion');
        const selectGrupo = document.getElementById('select_grupo');

        function filtrarGrupos() {
            const ubicacionSeleccionada = selectUbicacion.value;
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
                    const optionVacio = document.createElement('option');
                    optionVacio.value = "";
                    optionVacio.textContent = "No hay grupos asignados a esta zona";
                    selectGrupo.appendChild(optionVacio);
                    selectGrupo.disabled = true;
                }
            } else {
                selectGrupo.disabled = true;
                const optionDefault = document.createElement('option');
                optionDefault.value = "";
                optionDefault.textContent = "Primero seleccione una ubicación...";
                selectGrupo.appendChild(optionDefault);
            }
        }

        selectUbicacion.addEventListener('change', filtrarGrupos);

        // Para mantener la funcionalidad si hay un valor preseleccionado (útil en ediciones)
        if (selectUbicacion.value) {
            filtrarGrupos();
        }

        // --- Redirección Inteligente a los Editores XR ---
        function irAEditor(tipo) {
            let idMultimedia = '';
            let urlDestino = '';

            if (tipo === 'foto') {
                idMultimedia = document.getElementById('select_foto_360').value;
                urlDestino = 'editor_xr_foto_360.php';
            } else if (tipo === 'video') {
                idMultimedia = document.getElementById('select_video_360').value;
                urlDestino = 'editor_xr_video_360.php';
            } else if (tipo === 'modelo') {
                idMultimedia = document.getElementById('select_modelo_3d').value;
                urlDestino = 'editor_xr_modelo_3d.php';
            }

            if (!idMultimedia) {
                alert('⚠️ Por favor, seleccione un archivo multimedia del menú desplegable antes de editar.');
                return;
            }

            // Opcional: También mandamos la ubicación y el grupo por la URL por si lo necesitas en el editor
            let ubi = document.getElementById('select_ubicacion').value;
            let grp = document.getElementById('select_grupo').value;

            window.location.href = `${urlDestino}?id_media=${idMultimedia}&id_ubi=${ubi}&id_grp=${grp}`;
        }
    </script>
</body>
</html>