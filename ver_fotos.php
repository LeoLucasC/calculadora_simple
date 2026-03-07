<?php
session_start();
include 'includes/db.php';

// 1. Seguridad
if (!isset($_SESSION['id_usuario'])) { header("Location: index.html"); exit(); }
$id_ubicacion = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_ubicacion == 0) { header("Location: galeria.php"); exit(); }

// 2. Obtener datos de la ubicación
$res_ubi = $conn->query("SELECT nombre, descripcion FROM ubicaciones WHERE id_ubicacion = $id_ubicacion");
$ubicacion = $res_ubi->fetch_assoc();

// 3. CAPTURAR EL FILTRO DE LA URL
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';

// 4. PREPARAR LA CONSULTA BASE
$sql = "SELECT m.*, u.nombre as usuario_nombre, g.nombre as grupo_nombre 
        FROM multimedia m
        INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
        INNER JOIN grupos_operativos g ON m.id_grupo = g.id_grupo
        WHERE m.id_ubicacion = $id_ubicacion";

// 5. APLICAR EL FILTRO EXACTO
$filtro_texto = "Todos los archivos";
if ($tipo_filtro == 'fotos') {
    $sql .= " AND m.tipo_archivo = 'FOTO' AND m.es_360 = 0";
    $filtro_texto = "Fotos Normales";
} elseif ($tipo_filtro == 'videos') {
    $sql .= " AND m.tipo_archivo = 'VIDEO' AND m.es_360 = 0";
    $filtro_texto = "Videos Normales";
} elseif ($tipo_filtro == 'fotos360') {
    $sql .= " AND m.tipo_archivo = 'FOTO' AND m.es_360 = 1";
    $filtro_texto = "Fotos 360° VR";
} elseif ($tipo_filtro == 'videos360') {
    $sql .= " AND m.tipo_archivo = 'VIDEO' AND m.es_360 = 1";
    $filtro_texto = "Videos 360° VR";
}

// 6. ORDENAR Y EJECUTAR
$sql .= " ORDER BY m.fecha_hora DESC";
$archivos = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>KunturVR | Detalle de Zona</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; display: flex; min-height: 100vh; }
        
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        
        .page-header { margin-bottom: 30px; display: flex; align-items: center; gap: 20px; }
        .btn-back { background: white; border: 1px solid #e2e8f0; padding: 10px 15px; border-radius: 10px; color: #023675; text-decoration: none; transition: 0.3s; }
        .btn-back:hover { background: #f8fafc; transform: translateX(-5px); }

        .header-info h1 { font-size: 2rem; color: #0f172a; }
        .header-info p { color: #64748b; }

        .active-filter-badge {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 12px;
            background: #e2e8f0;
            color: #334155;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .media-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); 
            gap: 20px; 
        }
        
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
        .bg-foto { background: #e0f2fe; color: #0369a1; }
        .bg-video { background: #f3e8ff; color: #6b21a8; }
        .bg-360 { background: #fef3c7; color: #b45309; border: 1px solid #f59e0b; }

        .media-item { 
            background: white; 
            border-radius: 15px; 
            overflow: hidden; 
            border: 1px solid #e9eef2; 
            transition: 0.3s;
            position: relative;
        }
        .media-item:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }

        .media-preview { height: 180px; position: relative; cursor: zoom-in; background: #000; }
        .media-preview img, .media-preview video { width: 100%; height: 100%; object-fit: cover; }

        .video-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); color: white; font-size: 2.5rem; opacity: 0.8; }
        
        .media-details { padding: 15px; }
        .media-details small { display: block; color: #94a3b8; font-size: 0.75rem; margin-bottom: 5px; }
        .media-details .meta { font-size: 0.85rem; color: #1e293b; font-weight: 500; }
        .media-details i { color: #023675; margin-right: 5px; }

        /* Modal Preview */
        #previewModal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.92);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        #previewContent {
            width: 95%;
            max-width: 1200px;
            height: 90vh;        /* <-- antes era 500px */
            text-align: center;
            position: relative;
        }
        .close-modal {
            position: absolute;
            top: 20px; right: 30px;
            color: white;
            font-size: 40px;
            cursor: pointer;
            z-index: 10000;
        }

        /* Hint de arrastre para 360 */
        .drag-hint {
            position: absolute;
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.65);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            pointer-events: none;
            z-index: 10;
            white-space: nowrap;
        }
        
        .empty-state { grid-column: 1/-1; text-align: center; padding: 60px 20px; background: white; border-radius: 15px; }
    </style>

    <!-- A-Frame para los visores 360 -->
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>
</head>
<body>

    <?php include 'includes/navbar_cliente.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <a href="galeria.php" class="btn-back"><i class="fas fa-arrow-left"></i></a>
            <div class="header-info">
                <h1><?= htmlspecialchars($ubicacion['nombre']) ?></h1>
                <p><?= htmlspecialchars($ubicacion['descripcion'] ?: 'Evidencias multimedia registradas en esta zona.') ?></p>
                <div class="active-filter-badge"><i class="fas fa-filter"></i> Mostrando: <?= $filtro_texto ?></div>
            </div>
        </div>

        <div class="media-grid">
            <?php if ($archivos->num_rows > 0): ?>
                <?php while($row = $archivos->fetch_assoc()): 
                    $ext = strtolower(pathinfo($row['url_archivo'], PATHINFO_EXTENSION));
                    $es_video = in_array($ext, ['mp4', 'mov', 'webm']);
                ?>
                    <div class="media-item">
                        <div class="media-preview" onclick="openPreview('<?= $row['url_archivo'] ?>', '<?= $es_video ? 'video' : 'foto' ?>', <?= $row['es_360'] ?>)">
                            <?php if($es_video): ?>
                                <video muted><source src="<?= $row['url_archivo'] ?>"></video>
                                <i class="fas fa-play-circle video-icon"></i>
                            <?php else: ?>
                                <img src="<?= $row['url_archivo'] ?>" alt="Evidencia">
                            <?php endif; ?>
                        </div>
                        <div class="media-details">
                            <small><i class="fas fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($row['fecha_hora'])) ?></small>
                            <div class="meta"><i class="fas fa-user"></i> <?= htmlspecialchars($row['usuario_nombre']) ?></div>
                            <div class="meta"><i class="fas fa-users"></i> <?= htmlspecialchars($row['grupo_nombre']) ?></div>
                            
                            <?php if($row['es_360'] == 1): ?>
                                <div class="type-badge-card bg-360">
                                    <i class="fas <?= $row['tipo_archivo'] == 'VIDEO' ? 'fa-vr-cardboard' : 'fa-street-view' ?>"></i> 
                                    <?= $row['tipo_archivo'] == 'VIDEO' ? 'Video 360°' : 'Foto 360°' ?>
                                </div>
                            <?php elseif($row['tipo_archivo'] == 'VIDEO'): ?>
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
                    <i class="fas fa-folder-open" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 20px;"></i>
                    <h3 style="color: #1e293b; font-size: 1.5rem; margin-bottom: 10px;">Carpeta vacía</h3>
                    <p style="color: #64748b;">No hay <strong><?= strtolower($filtro_texto) ?></strong> registrados en esta ubicación por el momento.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="previewModal">
        <span class="close-modal" onclick="closePreview()">&times;</span>
        <div id="previewContent"></div>
    </div>

    <script>
   


function openPreview(url, tipo, es360) {
    const container = document.getElementById('previewContent');
    const modal     = document.getElementById('previewModal');
    container.innerHTML = '';

    if (es360 == 1) {
        const uniqueId = "media-" + Math.random().toString(36).substr(2, 9);
        let aframeHTML = '';

        if (tipo === 'video') {
            aframeHTML = `
                <a-scene embedded style="width:100%; height:100%;"
                    vr-mode-ui="enabled: true"
                    device-orientation-permission-ui="enabled: true"
                    renderer="colorManagement: true"
                    loading-screen="dotsColor:white; backgroundColor:#111">
                    <a-assets timeout="30000">
                        <video id="${uniqueId}" src="${url}" preload="auto"
                            autoplay loop playsinline webkit-playsinline
                            crossorigin="anonymous"></video>
                    </a-assets>
                    <a-videosphere src="#${uniqueId}" rotation="0 180 0" radius="100"></a-videosphere>
                    <a-camera position="0 0 0" fov="80" rotation="0 0 0"
                        look-controls="enabled:true; reverseMouseDrag:false; touchEnabled:true; magicWindowTrackingEnabled:true"
                        wasd-controls="enabled:false">
                    </a-camera>
                </a-scene>`;
        } else {
            aframeHTML = `
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

        container.innerHTML = aframeHTML;

    } else {
        if (tipo === 'video') {
            container.innerHTML = `
                <video controls autoplay style="width:100%; max-height:80vh; border-radius:10px;">
                    <source src="${url}">
                </video>`;
        } else {
            container.innerHTML = `
                <img src="${url}" style="max-width:100%; max-height:80vh; border-radius:10px; object-fit:contain;">`;
        }
    }

    modal.style.display = 'flex';
}

function closePreview() {
    const modal     = document.getElementById('previewModal');
    const container = document.getElementById('previewContent');
    container.innerHTML = '';
    modal.style.display = 'none';
}

    // Cerrar con tecla Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === "Escape") closePreview();
    });
    </script>
</body>
</html>