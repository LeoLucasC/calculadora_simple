<?php
session_start();
include 'includes/db.php';
include 'includes/seguridad.php';

$id_user = $_SESSION['id_usuario'];

// --- Extraer a qué empresa pertenece el usuario ---
$q_empresa = $conn->query("SELECT id_cliente FROM usuarios WHERE id_usuario = $id_user");
$id_cliente = $q_empresa->fetch_assoc()['id_cliente'];

// --- Traer MODELOS 3D de la empresa ---
$sql_modelos = "SELECT m.id_multimedia, m.url_archivo, m.observaciones 
                FROM multimedia m
                INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                WHERE u.id_cliente = $id_cliente AND m.tipo_archivo = 'MODELO_3D'";
$res_modelos = $conn->query($sql_modelos);

$modelos_3d = [];
if ($res_modelos) {
    while ($row = $res_modelos->fetch_assoc()) {
        $modelos_3d[] = $row;
    }
}
// Seleccionamos el primer modelo o dejamos uno de prueba
$modelo_inicial = (count($modelos_3d) > 0) ? $modelos_3d[0]['url_archivo'] : 'https://cdn.aframe.io/test-models/models/glTF-2.0/virtualcity/VC.gltf';

// --- Traer TODOS los proyectos guardados (para cargar elementos 3D/Textos sobre el modelo) ---
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
    <title>KUNTUR-XR | Galería Modelo 3D</title>

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

        /* CONTROLES DEL VISOR (Rotar Modelo) */
        .view-controls {
            position: absolute; top: 15px; right: 15px; z-index: 100;
            background: rgba(0, 0, 0, 0.6); padding: 8px; border-radius: 8px;
            backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1);
            display: flex; gap: 10px;
        }
        .view-controls button {
            background: transparent; color: white; border: none; font-size: 1.2rem;
            cursor: pointer; transition: color 0.3s; width: 35px; height: 35px; border-radius: 50%;
        }
        .view-controls button:hover { color: #00d4ff; background: rgba(255,255,255,0.1); }

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
        
        /* Thumbnail 3D estético */
        .thumb-3d-box {
            width: 100%; height: 80px; background: #222; border-radius: 8px;
            border: 3px solid transparent; transition: border 0.3s;
            display: flex; align-items: center; justify-content: center;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.8);
        }
        .thumb-3d-box i { font-size: 1.8rem; color: rgba(255,255,255,0.5); transition: 0.3s; }
        
        .thumb-item.active .thumb-3d-box, .thumb-item:hover .thumb-3d-box { border-color: #00d4ff; transform: translateY(-3px); }
        .thumb-item.active .thumb-3d-box i, .thumb-item:hover .thumb-3d-box i { color: #00d4ff; }
        
        .thumb-item span {
            display: block; font-size: 0.75rem; margin-top: 5px; 
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #cbd5e1;
        }
        .thumb-item.active span, .thumb-item:hover span { color: white; font-weight: bold; }
    </style>
</head>

<body>

    

    <div class="main-content">
        
    <a href="viewer_galeria.php" style="display: block; background: #334155; color: white; padding: 10px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 600; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s;" onmouseover="this.style.background='#ef4444'" onmouseout="this.style.background='#334155'">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
        <div class="top-bar">
            <h2>KUNTUR-XR</h2>
            <p>Galería Modelos 3D</p>
        </div>

        <div class="view-controls">
            <button onclick="hacerZoom(0.2)" title="Acercar (Zoom In)"><i class="fas fa-search-plus"></i></button>
            <button onclick="hacerZoom(-0.2)" title="Alejar (Zoom Out)"><i class="fas fa-search-minus"></i></button>
            
            <button onclick="inspeccionarModelo(-15)" title="Girar Izquierda"><i class="fas fa-undo"></i></button>
            <button onclick="inspeccionarModelo(15)" title="Girar Derecha"><i class="fas fa-redo"></i></button>
            <button onclick="resetearVista()" title="Restaurar Vista Original"><i class="fas fa-compress-arrows-alt"></i></button>
        </div>

        <div class="slider-container">
            <?php if (count($modelos_3d) > 0): ?>
                <?php foreach($modelos_3d as $index => $m): ?>
                    <div class="thumb-item <?= ($index === 0) ? 'active' : '' ?>" 
                         onclick="cambiarEscena(this, '<?= htmlspecialchars($m['url_archivo']) ?>')">
                        <div class="thumb-3d-box">
                            <i class="fas fa-cube"></i>
                        </div>
                        <span><?= htmlspecialchars($m['observaciones'] ?: 'Modelo '.($index+1)) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="thumb-item active">
                    <div class="thumb-3d-box"><i class="fas fa-cube"></i></div>
                    <span>Modelo Demo</span>
                </div>
            <?php endif; ?>
        </div>

        <a-scene embedded style="width: 100%; height: 100%; background-color: #222;">
            
            <a-entity id="modelo3D"
                      gltf-model="<?= htmlspecialchars($modelo_inicial) ?>"
                      position="0 0 -5"
                      scale="1 1 1">
            </a-entity>

            <a-entity camera look-controls position="0 1.6 0">
                <a-cursor color="#ffffff" scale="0.8 0.8 0.8" opacity="0.5"></a-cursor>
            </a-entity>
            
        </a-scene>

    </div>

    <script>
       let proyectosGuardados = <?php echo json_encode($proyectos_guardados, JSON_UNESCAPED_SLASHES); ?>;
        
        // Guardamos la rotación y escala original para el botón de "Reset"
        let rotacionOriginalGuardada = {x:0, y:0, z:0}; 
        let escalaOriginalGuardada = {x:1, y:1, z:1};

        window.onload = function() {
            setTimeout(() => {
                cargarElementosGuardados("<?= htmlspecialchars($modelo_inicial) ?>");
            }, 500);
        };

        // --- CONTROLES DE INSPECCIÓN DEL ESPECTADOR ---
        function hacerZoom(v) {
            let m = document.getElementById("modelo3D");
            let s = m.getAttribute("scale") || {x:1, y:1, z:1};
            if(typeof s === 'string') { 
                let p = s.split(' '); 
                s = {x: parseFloat(p[0]), y: parseFloat(p[1]), z: parseFloat(p[2])}; 
            }
            s.x += v; s.y += v; s.z += v;
            
            // Límite para que no se haga microscópico ni se invierta
            if(s.x < 0.1) { s.x = 0.1; s.y = 0.1; s.z = 0.1; } 
            
            m.setAttribute("scale", s);
        }

        function inspeccionarModelo(gradosY) {
            let m = document.getElementById("modelo3D");
            let r = m.getAttribute("rotation") || {x:0, y:0, z:0};
            if(typeof r === 'string') { 
                let p = r.split(' '); 
                r = {x: parseFloat(p[0]), y: parseFloat(p[1]), z: parseFloat(p[2])}; 
            }
            r.y += gradosY; 
            m.setAttribute("rotation", r);
        }

        function resetearVista() {
            let m = document.getElementById("modelo3D");
            m.setAttribute("rotation", rotacionOriginalGuardada);
            m.setAttribute("scale", escalaOriginalGuardada); // Restaura el tamaño
        }

        // --- CAMBIO DE ESCENA DESDE EL SLIDER ---
        function cambiarEscena(elementoHTML, urlModelo) {
            document.querySelectorAll('.thumb-item').forEach(el => el.classList.remove('active'));
            elementoHTML.classList.add('active');

            let modelo = document.getElementById("modelo3D");
            modelo.setAttribute("gltf-model", urlModelo);
            
            // Reset por defecto antes de cargar el JSON
            modelo.setAttribute("rotation", "0 0 0"); 
            modelo.setAttribute("scale", "1 1 1");

            document.querySelectorAll('.objeto').forEach(el => {
                if(el.parentNode) el.parentNode.removeChild(el);
            });

            cargarElementosGuardados(urlModelo);
        }

        function cargarElementosGuardados(url) {
            if (proyectosGuardados[url]) {
                let data = JSON.parse(proyectosGuardados[url]);
                
                // Aplicar configuraciones al modelo principal
                let modBase = document.getElementById("modelo3D");
                if(data.modelRotation) {
                    modBase.setAttribute("rotation", data.modelRotation);
                    rotacionOriginalGuardada = data.modelRotation; 
                } else {
                    rotacionOriginalGuardada = {x:0, y:0, z:0};
                }

                if(data.modelScale) {
                    modBase.setAttribute("scale", data.modelScale);
                    escalaOriginalGuardada = data.modelScale; // Recordar para el botón Reset
                } else {
                    escalaOriginalGuardada = {x:1, y:1, z:1};
                }

                // Generar los elementos secundarios (etiquetas, fotos)
                data.elementos.forEach(obj => {
                    let el = document.createElement(obj.tag);
                    el.setAttribute("position", obj.position);
                    el.setAttribute("scale", obj.scale);
                    el.setAttribute("class", "objeto");

                    if (obj.tag === "a-text") {
                        el.setAttribute("value", obj.value);
                        el.setAttribute("color", obj.color);
                        el.setAttribute("width", obj.width);
                        el.setAttribute("align", "center");
                    } else if (obj.tag === "a-image") {
                        el.setAttribute("src", obj.src);
                    }

                    document.querySelector("a-scene").appendChild(el);
                });
            } else {
                // Si no hay guardado, la rotación original es 0 0 0
                rotacionOriginalGuardada = {x:0, y:0, z:0};
            }
        }
    </script>
</body>
</html>