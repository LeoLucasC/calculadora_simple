<?php
include 'includes/db.php';
include 'includes/seguridad.php'; 

// Validar que no sea Admin
if ($_SESSION['id_rol'] == 1) { header("Location: dashboard_admin.php"); exit(); }

$id_user = $_SESSION['id_usuario'];

// 1. Averiguar a qué empresa (id_cliente) pertenece este VIEWER
$q_empresa = $conn->query("SELECT id_cliente FROM usuarios WHERE id_usuario = $id_user");
$mi_cliente = $q_empresa->fetch_assoc()['id_cliente'];

// 2. Traer todos los marcadores AR creados por CUALQUIER usuario de esa MISMA empresa
$sql_ar = "SELECT ma.*, m.url_archivo as modelo_url, m.observaciones as modelo_nombre 
           FROM marcadores_ar ma
           INNER JOIN multimedia m ON ma.id_modelo_3d = m.id_multimedia
           INNER JOIN usuarios u ON ma.id_usuario = u.id_usuario
           WHERE u.id_cliente = $mi_cliente";
           
$marcadores = $conn->query($sql_ar);
$lista_marcadores = [];

if($marcadores && $marcadores->num_rows > 0) {
    while($row = $marcadores->fetch_assoc()) {
        $lista_marcadores[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>KUNTUR-AR | Entorno Realidad Aumentada</title>

<script src="https://aframe.io/releases/1.0.4/aframe.min.js"></script>
<script src="https://raw.githack.com/AR-js-org/AR.js/master/aframe/build/aframe-ar.js"></script>

<style>
    body {
        margin: 0;
        overflow: hidden;
        font-family: Arial, sans-serif;
    }

    #topbar {
        position: absolute;
        top: 0;
        width: 100%;
        height: 55px;
        background: rgba(2, 54, 117, 0.85); /* Azul corporativo */
        color: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 15px;
        z-index: 10;
        font-weight: bold;
    }
    
    .back-btn {
        color: white;
        text-decoration: none;
        background: rgba(255,255,255,0.2);
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 14px;
    }

    #sidebar {
        position: absolute;
        left: 15px;
        top: 70px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 10;
    }

    .btn {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        border: none;
        background: rgba(0, 188, 212, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: white;
        font-size: 18px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.3);
    }

    .label {
        color: white;
        font-size: 12px;
        margin-top: 6px;
        text-shadow: 1px 1px 2px black;
    }

    .night video {
        filter: grayscale(100%) contrast(200%) brightness(120%);
    }
    
    .no-data-msg {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 30px;
        border-radius: 15px;
        text-align: center;
        z-index: 20;
    }
</style>

<script>
    let currentAudio = null;

    // Handler para los marcadores con soporte de AUDIO
    // DESPUÉS (correcto)
AFRAME.registerComponent('marker-handler', {
    schema: {
        audio: {type: 'string', default: ''}
    },
    init: function() {
        const marker = this.el;
        const self = this; // ✅ capturamos contexto

        marker.addEventListener("markerFound", () => {
            const model = marker.querySelector("a-entity"); // ✅ consultado cuando ya existe
            if (!model) return; // ✅ protección ante null
            model.setAttribute("visible", true);
            window.modelEl = model;

            if (self.data.audio && self.data.audio !== 'NULL' && self.data.audio !== '') {
                if (currentAudio) currentAudio.pause();
                currentAudio = new Audio(self.data.audio);
                currentAudio.play().catch(e => console.log("Audio bloqueado:", e));
            }
        });

        marker.addEventListener("markerLost", () => {
            const model = marker.querySelector("a-entity"); // ✅ igual aquí
            if (!model) return;
            model.setAttribute("visible", false);
            if (currentAudio) {
                currentAudio.pause();
                currentAudio.currentTime = 0;
            }
        });
    }
});

    // Rotación con mouse
    AFRAME.registerComponent('mouse-rotate', {
        init: function() {
            let drag = false, prev = {x: 0, y: 0};
            const el = this.el;

            window.addEventListener('mousedown', e => {
                drag = true;
                prev = {x: e.clientX, y: e.clientY};
            });

            window.addEventListener('mouseup', () => drag = false);

            window.addEventListener('mousemove', e => {
                if(!drag || !el.getAttribute("visible")) return;

                const dx = e.clientX - prev.x;
                const dy = e.clientY - prev.y;

                el.object3D.rotation.y += dx * 0.01;
                el.object3D.rotation.x += dy * 0.01;

                prev = {x: e.clientX, y: e.clientY};
            });
        }
    });

    // Funciones de control UI
    function moveModel(dx, dy, dz) {
        if(!modelEl || !modelEl.getAttribute("visible")) return;
        modelEl.object3D.position.x += dx;
        modelEl.object3D.position.y += dy;
        modelEl.object3D.position.z += dz;
    }

    function zoomModel(f) {
        if(!modelEl) return;
        modelEl.object3D.scale.multiplyScalar(f);
    }

    function clearModel() {
        if(modelEl) modelEl.setAttribute("visible", false);
    }

    function centerModel() {
        const cam = document.querySelector("[camera]").object3D;
        if(!modelEl) return;
        const dir = new THREE.Vector3();
        cam.getWorldDirection(dir);
        const pos = cam.position.clone().add(dir.multiplyScalar(-1));
        modelEl.object3D.position.copy(pos);
        modelEl.object3D.quaternion.copy(cam.quaternion);
        modelEl.setAttribute("visible", true);
    }

    let night = false;
    function toggleNightMode() {
        night = !night;
        document.body.classList.toggle("night", night);
    }
</script>
</head>

<body>

<div id="topbar">
    <span>KUNTUR-AR | Lector de Evidencias</span>
    <a href="ver_grupo_viewer.php" class="back-btn">⬅ Volver al Menú</a>
</div>

<?php if(empty($lista_marcadores)): ?>
    <div class="no-data-msg">
        <h2>No hay marcadores</h2>
        <p>Tu empresa aún no ha configurado marcadores AR.</p>
        <br>
        <a href="ver_grupo_viewer.php" style="color: #00bcd4;">Ir a la Galería</a>
    </div>
<?php else: ?>

    <div id="sidebar">
        <button class="btn" onclick="centerModel()" title="Centrar">🎯</button>
        <button class="btn" onclick="toggleNightMode()" title="Modo Noche">🌙</button>
        <button class="btn" onclick="zoomModel(1.2)" title="Acercar">＋</button>
        <button class="btn" onclick="zoomModel(0.8)" title="Alejar">－</button>
        <button class="btn" onclick="clearModel()" title="Ocultar">🗑</button>

        <div class="label">X (Lados)</div>
        <div style="display:flex; gap:6px;">
            <button class="btn" onclick="moveModel(-0.05,0,0)">◀</button>
            <button class="btn" onclick="moveModel(0.05,0,0)">▶</button>
        </div>

        <div class="label">Y (Arriba/Abajo)</div>
        <div style="display:flex; gap:6px;">
            <button class="btn" onclick="moveModel(0,0.05,0)">⬆</button>
            <button class="btn" onclick="moveModel(0,-0.05,0)">⬇</button>
        </div>

        <div class="label">Z (Profundidad)</div>
        <div style="display:flex; gap:6px;">
            <button class="btn" onclick="moveModel(0,0,0.05)">＋</button>
            <button class="btn" onclick="moveModel(0,0,-0.05)">－</button>
        </div>
    </div>

    <a-scene
        embedded
        vr-mode-ui="enabled: false"
        renderer="logarithmicDepthBuffer: true;"
        arjs="trackingMethod: best; sourceType: webcam; debugUIEnabled: false;">

        <a-assets>
            <?php foreach($lista_marcadores as $index => $m): ?>
                <a-asset-item id="modelo_<?= $index ?>" src="<?= htmlspecialchars($m['modelo_url']) ?>"></a-asset-item>
            <?php endforeach; ?>
        </a-assets>

       <?php foreach($lista_marcadores as $index => $m): 
            $escala = "{$m['escala_x']} {$m['escala_y']} {$m['escala_z']}";
            $pos    = "{$m['pos_x']} {$m['pos_y']} {$m['pos_z']}";
            
            // Lógica limpia para evitar crasheos en A-Frame si no hay audio
            $handler_attr = "marker-handler"; 
            if (!empty($m['archivo_audio']) && $m['archivo_audio'] != 'NULL') {
                $audio_path = trim($m['archivo_audio'], "'");
                if ($audio_path != '') {
                    $handler_attr = 'marker-handler="audio: ' . htmlspecialchars($audio_path) . '"';
                }
            }
        ?>
            <a-marker type="pattern" url="<?= htmlspecialchars($m['archivo_patt']) ?>" emitevents="true" <?= $handler_attr ?>>
                
                <a-entity scale="<?= $escala ?>" visible="false" mouse-rotate>
                    
                    <a-entity gltf-model="#modelo_<?= $index ?>"></a-entity>

                    <a-entity 
                        position="<?= $pos ?>" 
                        look-at="[camera]" 
                        text="value: <?= htmlspecialchars($m['texto_modelo']) ?>; align: <?= $m['alineacion'] ?>; color: <?= $m['color'] ?>; width: <?= $m['ancho_texto'] ?>;">
                    </a-entity>

                </a-entity>

            </a-marker>
        <?php endforeach; ?>

        <a-entity camera></a-entity>
    </a-scene>

<?php endif; ?>

</body>
</html>