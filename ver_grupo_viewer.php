<?php
session_start();
include 'includes/db.php';

// 1. Seguridad
if (!isset($_SESSION['id_usuario'])) { header("Location: index.html"); exit(); }

$id_grupo     = isset($_GET['id'])        ? intval($_GET['id'])        : 0;
$id_ubicacion = isset($_GET['ubicacion']) ? intval($_GET['ubicacion']) : 0;

if ($id_grupo == 0) { header("Location: viewer_galeria.php"); exit(); }

// 2. Obtener datos del grupo (Validando si tiene permiso)
$id_usuario_actual = $_SESSION['id_usuario'];
$res_grupo = $conn->query("
    SELECT g.nombre, g.descripcion, tg.descripcion as tipo_grupo,
           u.nombre as ubicacion_nombre, u.id_ubicacion
    FROM grupos_operativos g
    INNER JOIN tipos_grupo  tg ON g.id_tipo_grupo = tg.id_tipo_grupo
    INNER JOIN ubicaciones  u  ON g.id_ubicacion  = u.id_ubicacion
    INNER JOIN permisos_usuarios pu ON g.id_grupo = pu.id_grupo
    WHERE g.id_grupo = $id_grupo AND pu.id_usuario = $id_usuario_actual
");
$grupo = $res_grupo->fetch_assoc();

if (!$grupo) { header("Location: viewer_galeria.php"); exit(); }

// 3. Capturar filtro de la URL
$tipo_filtro  = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';

// 4. Consulta base de multimedia
$sql = "SELECT m.*, u.nombre AS usuario_nombre
        FROM multimedia m
        INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
        WHERE m.id_grupo = $id_grupo";

// 5. Aplicar filtro
$filtro_texto = "Todos los archivos";
if ($tipo_filtro == 'fotos') {
    $sql .= " AND m.tipo_archivo = 'FOTO' AND m.es_360 = 0";
    $filtro_texto = "Fotos Normales";
} elseif ($tipo_filtro == 'videos') {
    $sql .= " AND m.tipo_archivo = 'VIDEO' AND m.es_360 = 0";
    $filtro_texto = "Videos Normales";
} elseif ($tipo_filtro == 'fotos360') {
    $sql .= " AND m.tipo_archivo = 'FOTO' AND m.es_360 = 1";
    $filtro_texto = "Fotos 360°";
} elseif ($tipo_filtro == 'modelos3d') {
    $sql .= " AND m.tipo_archivo = 'MODELO_3D'";
    $filtro_texto = "Modelos 3D";
}
$sql .= " ORDER BY m.fecha_hora DESC";
$archivos = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | <?= htmlspecialchars($grupo['nombre']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; display: flex; min-height: 100vh; }

        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }

        /* ── Cabecera ── */
        .page-header {
            margin-bottom: 30px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }
        .btn-back {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 10px 15px;
            border-radius: 10px;
            color: #023675;
            text-decoration: none;
            transition: 0.3s;
            flex-shrink: 0;
            margin-top: 4px;
        }
        .btn-back:hover { background: #f8fafc; transform: translateX(-5px); }

        .header-info h1 { font-size: 2rem; color: #0f172a; }
        .header-info p   { color: #64748b; margin-top: 4px; }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: #64748b;
        }
        .breadcrumb a { color: #023675; text-decoration: none; font-weight: 500; }
        .breadcrumb a:hover { text-decoration: underline; }

        /* ── Badges ── */
        .badge-group {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            padding: 5px 12px;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .active-filter-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 5px 12px;
            background: #e2e8f0;
            color: #334155;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        /* ── Filtros ── */
        .filters-bar {
            background: white;
            border: 1px solid #e9eef2;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .filter-btn {
            padding: 9px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #64748b;
            font-size: 0.88rem;
            font-weight: 500;
            cursor: pointer;
            transition: 0.25s;
            display: flex;
            align-items: center;
            gap: 7px;
            text-decoration: none;
        }
        .filter-btn:hover  { border-color: #023675; color: #023675; }
        .filter-btn.active { background: #023675; color: white; border-color: #023675; }

        /* ── Grid de archivos ── */
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .media-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid #e9eef2;
            transition: 0.3s;
        }
        .media-item:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }

        .media-preview {
            height: 180px;
            position: relative;
            cursor: zoom-in;
            background: #000;
        }
        .media-preview img,
        .media-preview video { width: 100%; height: 100%; object-fit: cover; }

        .play-icon {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%,-50%);
            color: white;
            font-size: 2.5rem;
            opacity: 0.85;
            pointer-events: none;
        }

        .media-details { padding: 15px; }
        .media-details small {
            display: block;
            color: #94a3b8;
            font-size: 0.75rem;
            margin-bottom: 5px;
        }
        
        /* Nuevos estilos para la información del archivo */
        .media-details .fecha-info {
            color: #94a3b8;
            font-size: 0.75rem;
            margin-bottom: 8px;
        }
        
        .media-details .nombre-general {
            font-weight: 700;
            font-size: 1.1rem;
            color: #0f172a;
            margin-bottom: 5px;
            border-left: 3px solid #023675;
            padding-left: 8px;
        }
        
        .media-details .subido-por {
            color: #64748b;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 8px;
        }
        
        .media-details .subido-por i {
            color: #023675;
        }
        
        .media-details .meta { 
            font-size: 0.85rem; 
            color: #1e293b; 
            font-weight: 500; 
        }
        .media-details i { color: #023675; margin-right: 5px; }

        /* Etiquetas de tipo */
        .type-badge-card {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 12px;
        }
        .bg-foto  { background: #e0f2fe; color: #0369a1; }
        .bg-video { background: #f3e8ff; color: #6b21a8; }
        .bg-360   { background: #fef3c7; color: #b45309; border: 1px solid #f59e0b; }

        /* ── Modal visor ── */
        #previewModal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        #previewContent {
            max-width: 1200px;
            width: 95%;
            height: 90vh;
            background: transparent;
            border-radius: 16px;
            overflow: hidden;
            position: relative;
        }

        .viewer-container {
            width: 100%;
            height: 100%;
            position: relative;
            background: #000;
            border-radius: 16px;
            overflow: hidden;
        }
        .close-modal {
            position: absolute;
            top: 20px; right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
            z-index: 10000;
        }

        /* ── Empty state ── */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .main-content { padding: 20px 15px; }
            .page-header  { flex-direction: column; }
        }
    </style>
</head>
<body>

    <?php include 'includes/navar_viewer.php'; ?>

    <div class="main-content">

        <div class="page-header">
            <a href="viewer_galeria.php?ubicacion=<?= $grupo['id_ubicacion'] ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div class="header-info">
                <h1><?= htmlspecialchars($grupo['nombre']) ?></h1>
                <p><?= htmlspecialchars($grupo['descripcion'] ?: 'Evidencias multimedia del grupo operativo.') ?></p>

                <div class="breadcrumb">
                    <a href="viewer_galeria.php">Ubicaciones</a>
                    <i class="fas fa-chevron-right" style="font-size:0.75rem;"></i>
                    <a href="viewer_galeria.php?ubicacion=<?= $grupo['id_ubicacion'] ?>"><?= htmlspecialchars($grupo['ubicacion_nombre']) ?></a>
                    <i class="fas fa-chevron-right" style="font-size:0.75rem;"></i>
                    <span><?= htmlspecialchars($grupo['nombre']) ?></span>
                </div>

                <div class="badge-group">
                    <i class="fas fa-tag"></i> <?= htmlspecialchars($grupo['tipo_grupo']) ?>
                </div>
                <div class="active-filter-badge">
                    <i class="fas fa-filter"></i> Mostrando: <?= $filtro_texto ?>
                </div>
            </div>
        </div>

        <div class="filters-bar">
            <a href="?id=<?= $id_grupo ?>&ubicacion=<?= $id_ubicacion ?>&tipo=todos"       class="filter-btn <?= $tipo_filtro=='todos'      ? 'active':'' ?>"><i class="fas fa-th-large"></i> Todos</a>
            <a href="?id=<?= $id_grupo ?>&ubicacion=<?= $id_ubicacion ?>&tipo=fotos"       class="filter-btn <?= $tipo_filtro=='fotos'      ? 'active':'' ?>"><i class="fas fa-image"></i> Fotos</a>
            <a href="?id=<?= $id_grupo ?>&ubicacion=<?= $id_ubicacion ?>&tipo=videos"      class="filter-btn <?= $tipo_filtro=='videos'     ? 'active':'' ?>"><i class="fas fa-video"></i> Videos</a>
            <a href="?id=<?= $id_grupo ?>&ubicacion=<?= $id_ubicacion ?>&tipo=fotos360"    class="filter-btn <?= $tipo_filtro=='fotos360'   ? 'active':'' ?>"><i class="fas fa-street-view"></i> Fotos 360°</a>
            <a href="?id=<?= $id_grupo ?>&ubicacion=<?= $id_ubicacion ?>&tipo=videos360"   class="filter-btn <?= $tipo_filtro=='videos360'  ? 'active':'' ?>"><i class="fas fa-vr-cardboard"></i> Videos 360°</a>
            <a href="?id=<?= $id_grupo ?>&ubicacion=<?= $id_ubicacion ?>&tipo=modelos3d"   class="filter-btn <?= $tipo_filtro=='modelos3d'  ? 'active':'' ?>"><i class="fas fa-cube"></i> Modelos 3D</a>
        </div>

        <div class="media-grid">
            <?php if ($archivos && $archivos->num_rows > 0): ?>
                <?php while ($row = $archivos->fetch_assoc()):
                    $ext      = strtolower(pathinfo($row['url_archivo'], PATHINFO_EXTENSION));
                    $es_video = in_array($ext, ['mp4', 'mov', 'webm']);
                    $es_3d    = ($ext == 'glb' || $row['tipo_archivo'] == 'MODELO_3D');
                ?>
                <div class="media-item">
                    <div class="media-preview"
                         onclick="openPreview('<?= htmlspecialchars($row['url_archivo']) ?>',
                                              '<?= $es_3d ? 'modelo3d' : ($es_video ? 'video' : 'foto') ?>',
                                               <?= $row['es_360'] ?>)">

                        <?php if ($es_video): ?>
                            <video muted preload="none" poster="img/default_mining.jpg"><source src="<?= $row['url_archivo'] ?>"></video>
                            <i class="fas fa-play-circle play-icon"></i>
                        <?php elseif ($es_3d): ?>
                            <div style="width:100%; height:100%; background:#f1f5f9; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#64748b;">
                                <i class="fas fa-cube" style="font-size: 3rem; margin-bottom:10px; color:#0284c7;"></i>
                                <span style="font-size:0.9rem; font-weight:600;">Modelo 3D</span>
                            </div>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($row['url_archivo']) ?>" alt="Evidencia" loading="lazy">
                        <?php endif; ?>
                    </div>

                    <div class="media-details">
                        <!-- FECHA -->
                        <div class="fecha-info">
                            <i class="far fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($row['fecha_hora'])) ?>
                        </div>
                        
                        <!-- NOMBRE GENERAL (RESALTADO) -->
                        <div class="nombre-general">
                            <?= htmlspecialchars($row['observaciones'] ?: 'Evidencia') ?>
                        </div>
                        
                        <!-- SUBIDO POR (SIN RESALTAR) -->
                        <div class="subido-por">
                            <i class="fas fa-user-circle"></i> Subido por: <?= htmlspecialchars($row['usuario_nombre']) ?>
                        </div>

                        <?php if ($row['tipo_archivo'] == 'MODELO_3D'): ?>
                            <div class="type-badge-card" style="background: #e0f2fe; color: #0284c7; border: 1px solid #7dd3fc;">
                                <i class="fas fa-cube"></i> Modelo 3D
                            </div>
                        <?php elseif ($row['es_360'] == 1): ?>
                            <div class="type-badge-card bg-360">
                                <i class="fas <?= $row['tipo_archivo']=='VIDEO' ? 'fa-vr-cardboard':'fa-street-view' ?>"></i>
                                <?= $row['tipo_archivo']=='VIDEO' ? 'Video 360°':'Foto 360°' ?>
                            </div>
                        <?php elseif ($row['tipo_archivo'] == 'VIDEO'): ?>
                            <div class="type-badge-card bg-video">
                                <i class="fas fa-video"></i> Video
                            </div>
                        <?php else: ?>
                            <div class="type-badge-card bg-foto">
                                <i class="fas fa-camera"></i> Foto Estándar
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open" style="font-size:4rem; color:#cbd5e1; margin-bottom:20px;"></i>
                    <h3 style="color:#1e293b; font-size:1.5rem; margin-bottom:10px;">Sin archivos</h3>
                    <p style="color:#64748b;">No hay <strong><?= strtolower($filtro_texto) ?></strong>
                        registrados en este grupo por el momento.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Modal visor ── -->
    <div id="previewModal">
        <span class="close-modal" onclick="closePreview()">&times;</span>
        <div id="previewContent">
            <div style="padding:20px; height:calc(100% - 0px);">
                <div id="panorama-viewer" class="viewer-container"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.babylonjs.com/babylon.js"></script>
    <script src="https://cdn.babylonjs.com/loaders/babylonjs.loaders.min.js"></script>

    <script>

let babylonEngine = null;

function openPreview(url, tipo, es360) {
    const container = document.getElementById('panorama-viewer');
    const modal     = document.getElementById('previewModal');

    container.innerHTML = '';

    const isGLB = url.toLowerCase().endsWith('.glb') || tipo === 'modelo3d';

    if (isGLB) {
        // ── MODELO 3D (.GLB) con Babylon.js + WebXR Meta Quest 2 ──
        container.innerHTML = `<canvas id="renderCanvas" style="width:100%; height:100%; touch-action:none; outline:none; border-radius:16px; display:block;"></canvas>`;

        setTimeout(function() {
        const canvas = document.getElementById("renderCanvas");
        babylonEngine = new BABYLON.Engine(canvas, true, {
            adaptToDeviceRatio: true
        });

        (async () => {
            const scene = new BABYLON.Scene(babylonEngine);
            scene.clearColor = new BABYLON.Color4(0.88, 0.91, 0.94, 1);

            const camera = new BABYLON.ArcRotateCamera("camera", Math.PI / 2, Math.PI / 2.5, 5, BABYLON.Vector3.Zero(), scene);
            camera.attachControl(canvas, true);
            camera.wheelPrecision = 50;

            const light = new BABYLON.HemisphericLight("light", new BABYLON.Vector3(0, 1, 0), scene);
            light.intensity = 1.2;

            BABYLON.SceneLoader.Append("", url, scene, function (scene) {
                camera.setTarget(scene.meshes[0]);
            });

            // ── WebXR para Meta Quest 2 ──
            try {
                const xr = await scene.createDefaultXRExperienceAsync({
                    uiOptions: {
                        sessionMode: "immersive-vr",
                        referenceSpaceType: "local-floor"
                    },
                    optionalFeatures: true
                });
                if (!xr.baseExperience) {
                    console.warn("WebXR: headset no detectado, solo modo web.");
                }
            } catch (e) {
                console.warn("WebXR no soportado en este dispositivo:", e);
            }

            babylonEngine.runRenderLoop(function () { scene.render(); });
            window.addEventListener("resize", function () {
                if (babylonEngine) babylonEngine.resize();
            });
        })();
        }, 50);

    } else if (es360 == 1) {
        const uid = "media-" + Math.random().toString(36).substr(2, 9);

        if (tipo === 'video') {
            container.innerHTML = `
                <a-scene embedded style="width:100%; height:100%;"
                    vr-mode-ui="enabled: true"
                    device-orientation-permission-ui="enabled: true"
                    renderer="colorManagement: true"
                    loading-screen="dotsColor:white; backgroundColor:#111">
                    <a-assets timeout="30000">
                        <video id="${uid}" src="${url}" preload="auto" autoplay loop
                            playsinline webkit-playsinline crossorigin="anonymous"></video>
                    </a-assets>
                    <a-videosphere src="#${uid}" rotation="0 180 0" radius="100"></a-videosphere>
                    <a-camera position="0 0 0" fov="80" rotation="0 0 0"
                        look-controls="enabled:true; reverseMouseDrag:false; touchEnabled:true; magicWindowTrackingEnabled:true"
                        wasd-controls="enabled:false">
                    </a-camera>
                </a-scene>`;
        } else {
            container.innerHTML = `
                <a-scene embedded style="width:100%; height:100%;"
                    vr-mode-ui="enabled: true"
                    device-orientation-permission-ui="enabled: true"
                    renderer="colorManagement: true"
                    loading-screen="dotsColor:white; backgroundColor:#111">
                    <a-sky src="${url}" rotation="0 -90 0"></a-sky>
                    <a-camera position="0 0 0" fov="80" rotation="0 0 0"
                        look-controls="enabled:true; reverseMouseDrag:false; touchEnabled:true; magicWindowTrackingEnabled:true"
                        wasd-controls="enabled:false">
                    </a-camera>
                </a-scene>`;
        }

    } else {
        if (tipo === 'video') {
            container.innerHTML = `
                <video controls autoplay style="width:100%; max-height:100%; border-radius:10px; display:block;">
                    <source src="${url}">
                </video>`;
        } else {
            container.innerHTML = `
                <img src="${url}" style="max-width:100%; max-height:100%; border-radius:10px; object-fit:contain; display:block; margin:auto;">`;
        }
    }

    modal.style.display = 'flex';
}

function closePreview() {
    if (babylonEngine) {
        babylonEngine.dispose();
        babylonEngine = null;
    }

    const viewer = document.getElementById('panorama-viewer');
    if (viewer) viewer.innerHTML = '';

    document.getElementById('previewModal').style.display = 'none';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePreview();
});

document.getElementById('previewModal').addEventListener('click', function(e) {
    if (e.target === this) closePreview();
});
</script>
</body>
</html>