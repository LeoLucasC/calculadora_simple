<?php
session_start();
include 'includes/db.php';
include 'includes/seguridad.php';

$id_user = $_SESSION['id_usuario'];

// --- Extraer a qué empresa pertenece el usuario ---
$q_empresa = $conn->query("SELECT id_cliente FROM usuarios WHERE id_usuario = $id_user");
$id_cliente = $q_empresa->fetch_assoc()['id_cliente'];

// --- Traer VIDEOS 360 de la empresa ---
$sql_videos360 = "SELECT m.id_multimedia, m.url_archivo, m.observaciones 
                  FROM multimedia m
                  INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                  WHERE u.id_cliente = $id_cliente AND m.tipo_archivo = 'VIDEO' AND m.es_360 = 1";
$res_videos = $conn->query($sql_videos360);

$videos_360 = [];
if ($res_videos) {
    while ($row = $res_videos->fetch_assoc()) {
        $videos_360[] = $row;
    }
}
// Seleccionamos el primer video o dejamos uno de prueba
$video_inicial = (count($videos_360) > 0) ? $videos_360[0]['url_archivo'] : 'https://ucarecdn.com/bcece0a8-86ce-460e-856b-40dac4875f15/';

// --- Traer TODOS los proyectos guardados (para cargar elementos 3D/Textos sobre el video) ---
$sql_proyectos = "SELECT nombre_proyecto, json_config FROM proyectos_xr WHERE id_usuario = $id_user";
$res_proyectos = $conn->query($sql_proyectos);
$proyectos_guardados = [];
if ($res_proyectos && $res_proyectos->num_rows > 0) {
    while ($row = $res_proyectos->fetch_assoc()) {
        $proyectos_guardados[$row['nombre_proyecto']] = $row['json_config'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KUNTUR-XR | Galería Video 360</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #111; color: white; display: flex; height: 100vh; overflow: hidden; }
        
        .main-content { flex: 1; position: relative; height: 100vh; }

        /* HEADER FLOTANTE */
        .top-bar {
            position: absolute; top: 15px; left: 15px; z-index: 100;
            background: rgba(0, 0, 0, 0.6); padding: 10px 20px; border-radius: 8px;
            backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1);
        }
        .top-bar h2 { font-size: 1.2rem; color: #fff; }
        .top-bar p { font-size: 0.8rem; color: #00d4ff; margin-top: 2px;}

        /* CONTROLES DE REPRODUCCIÓN (Nuevo para Video) */
        .play-controls {
            position: absolute; top: 15px; right: 15px; z-index: 100;
            background: rgba(0, 0, 0, 0.6); padding: 8px; border-radius: 8px;
            backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1);
            display: flex; gap: 10px;
        }
        .play-controls button {
            background: transparent; color: white; border: none; font-size: 1.2rem;
            cursor: pointer; transition: color 0.3s; width: 35px; height: 35px; border-radius: 50%;
        }
        .play-controls button:hover { color: #00d4ff; background: rgba(255,255,255,0.1); }

        /* SLIDER INFERIOR DE MINIATURAS */
        .slider-container {
            position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
            width: 90%; max-width: 1000px; background: rgba(0, 0, 0, 0.6);
            padding: 15px; border-radius: 12px; backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2); z-index: 100;
            display: flex; gap: 15px; overflow-x: auto;
            scrollbar-width: thin; scrollbar-color: #00d4ff rgba(0,0,0,0.3);
        }
        
        .slider-container::-webkit-scrollbar { height: 8px; }
        .slider-container::-webkit-scrollbar-thumb { background: #00d4ff; border-radius: 10px; }
        .slider-container::-webkit-scrollbar-track { background: rgba(0,0,0,0.3); border-radius: 10px; }

        .thumb-item {
            flex: 0 0 auto; width: 120px; text-align: center; cursor: pointer;
            transition: all 0.3s ease; position: relative;
        }
        /* Thumbnail video estético (Simulado con fondo gris oscuro) */
        .thumb-video-box {
            width: 100%; height: 80px; background: #222; border-radius: 8px;
            border: 3px solid transparent; transition: border 0.3s;
            display: flex; align-items: center; justify-content: center;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.8);
        }
        .thumb-video-box i { font-size: 1.5rem; color: rgba(255,255,255,0.5); transition: 0.3s; }
        
        .thumb-item.active .thumb-video-box, .thumb-item:hover .thumb-video-box { border-color: #00d4ff; transform: translateY(-3px); }
        .thumb-item.active .thumb-video-box i, .thumb-item:hover .thumb-video-box i { color: #00d4ff; }
        
        .thumb-item span {
            display: block; font-size: 0.75rem; margin-top: 5px; 
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #cbd5e1;
        }
        .thumb-item.active span, .thumb-item:hover span { color: white; font-weight: bold; }
    </style>
</head>

<body>

    <?php include 'includes/navbar_cliente.php'; ?>

    <div class="main-content">
        
        <div class="top-bar">
            <h2>KUNTUR-XR</h2>
            <p>Galería Video 360°</p>
        </div>

        <div class="play-controls">
            <button onclick="controlarVideo('play')" title="Reproducir"><i class="fas fa-play"></i></button>
            <button onclick="controlarVideo('pause')" title="Pausar"><i class="fas fa-pause"></i></button>
            <button onclick="controlarVideo('restart')" title="Reiniciar"><i class="fas fa-redo"></i></button>
        </div>

        <div class="slider-container">
            <?php if (count($videos_360) > 0): ?>
                <?php foreach($videos_360 as $index => $v): ?>
                    <div class="thumb-item <?= ($index === 0) ? 'active' : '' ?>" 
                         onclick="cambiarEscena(this, '<?= htmlspecialchars($v['url_archivo']) ?>')">
                        <div class="thumb-video-box">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <span><?= htmlspecialchars($v['observaciones'] ?: 'Video '.($index+1)) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="thumb-item active">
                    <div class="thumb-video-box"><i class="fas fa-play-circle"></i></div>
                    <span>Video Demo</span>
                </div>
            <?php endif; ?>
        </div>

        <a-scene embedded style="width: 100%; height: 100%;">
            <a-assets>
                <video id="video360" src="<?= htmlspecialchars($video_inicial) ?>" crossorigin="anonymous" autoplay loop muted playsinline webkit-playsinline></video>
            </a-assets>

            <a-videosphere id="esferaVideo360" src="#video360" rotation="0 -90 0"></a-videosphere>

            <a-entity camera look-controls="reverseMouseDrag: true" position="0 1.6 0">
                <a-cursor color="#ffffff" scale="0.8 0.8 0.8" opacity="0.5"></a-cursor>
            </a-entity>
        </a-scene>

    </div>

    <script>
        let proyectosGuardados = <?php echo json_encode($proyectos_guardados, JSON_UNESCAPED_SLASHES); ?>;

        // Iniciar la primera escena al cargar la página
        window.onload = function() {
            setTimeout(() => {
                cargarElementosGuardados("<?= htmlspecialchars($video_inicial) ?>");
            }, 500);
        };

        // --- DESBLOQUEO DE AUTOPLAY ---
        // Al primer clic del usuario en la pantalla, se fuerza la reproducción del video
        document.body.addEventListener('click', function() {
            let videoEl = document.getElementById("video360");
            if (videoEl && videoEl.paused) {
                videoEl.play().catch(e => console.log("Esperando interacción para reproducir..."));
            }
        }, { once: true });


        // --- CONTROLES DE REPRODUCCIÓN MANUAL ---
        function controlarVideo(accion) {
            let video = document.getElementById("video360");
            if (!video) return;
            if (accion === 'play') video.play();
            if (accion === 'pause') video.pause();
            if (accion === 'restart') {
                video.currentTime = 0;
                video.play();
            }
        }

        // --- CAMBIO DE ESCENA DESDE EL SLIDER ---
        function cambiarEscena(elementoHTML, urlFondo) {
            // 1. Efecto visual en el Slider
            document.querySelectorAll('.thumb-item').forEach(el => el.classList.remove('active'));
            elementoHTML.classList.add('active');

            // 2. Cambiar el Video 360 y reproducirlo
            let videoEl = document.getElementById("video360");
            videoEl.setAttribute("src", urlFondo);
            videoEl.load();
            videoEl.play().catch(e => console.log("Esperando interacción..."));

            // 3. Limpiar los objetos (Señaléticas, modelos 3D) de la escena anterior
            document.querySelectorAll('.objeto').forEach(el => {
                if(el.parentNode) el.parentNode.removeChild(el);
            });

            // 4. Cargar los nuevos objetos si esta escena fue editada y guardada
            cargarElementosGuardados(urlFondo);
        }

        function cargarElementosGuardados(url) {
            if (proyectosGuardados[url]) {
                let data = JSON.parse(proyectosGuardados[url]);
                
                data.elementos.forEach(obj => {
                    let el = document.createElement(obj.tag);
                    el.setAttribute("position", obj.position);
                    el.setAttribute("rotation", obj.rotation);
                    el.setAttribute("scale", obj.scale);
                    el.setAttribute("class", "objeto");

                    if (obj.tag === "a-text") {
                        el.setAttribute("value", obj.value);
                        el.setAttribute("color", obj.color);
                        el.setAttribute("width", obj.width);
                        el.setAttribute("align", "center");
                    } else if (obj.tag === "a-image") {
                        el.setAttribute("src", obj.src);
                    } else if (obj.tag === "a-entity") {
                        el.setAttribute("gltf-model", obj.gltf_model);
                    }

                    document.querySelector("a-scene").appendChild(el);
                });
            }
        }
    </script>
</body>
</html>