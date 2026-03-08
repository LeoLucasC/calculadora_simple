<?php
session_start();
include 'includes/db.php';
include 'includes/seguridad.php';

// Validar que no sea Admin
if ($_SESSION['id_rol'] == 1) { header("Location: dashboard_admin.php"); exit(); }

$id_user = $_SESSION['id_usuario'];

// --- ¡AQUÍ ESTABA EL DETALLE! Volvemos a traer el id_cliente ---
$q_empresa = $conn->query("SELECT id_cliente FROM usuarios WHERE id_usuario = $id_user");
$id_cliente = $q_empresa->fetch_assoc()['id_cliente'];
// ---------------------------------------------------------------

// --- Extraer TODOS los proyectos guardados de este usuario ---
$sql_proyectos = "SELECT nombre_proyecto, json_config FROM proyectos_xr WHERE id_usuario = $id_user";
$res_proyectos = $conn->query($sql_proyectos);
$proyectos_guardados = [];
if ($res_proyectos && $res_proyectos->num_rows > 0) {
    while ($row = $res_proyectos->fetch_assoc()) {
        $proyectos_guardados[$row['nombre_proyecto']] = $row['json_config'];
    }
}

// --- Traer VIDEOS 360 de la empresa del usuario ---
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
// Escuchar si viene un id_media por la URL
$id_video_solicitado = isset($_GET['id_media']) ? intval($_GET['id_media']) : null;
$video_inicial = 'https://ucarecdn.com/bcece0a8-86ce-460e-856b-40dac4875f15/'; // Por defecto

if (count($videos_360) > 0) {
    if ($id_video_solicitado) {
        // Buscar la URL que corresponda a ese ID
        foreach ($videos_360 as $v) {
            if ($v['id_multimedia'] == $id_video_solicitado) {
                $video_inicial = $v['url_archivo'];
                break;
            }
        }
    } else {
        // Si no viene ID, cargar el primero de la lista
        $video_inicial = $videos_360[0]['url_archivo'];
    }
}   

// --- Traer fotos NORMALES y MODELOS 3D de la empresa ---
$sql_elementos = "SELECT m.id_multimedia, m.url_archivo, m.observaciones, m.tipo_archivo 
                  FROM multimedia m
                  INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                  WHERE u.id_cliente = $id_cliente AND (m.tipo_archivo = 'MODELO_3D' OR (m.tipo_archivo = 'FOTO' AND m.es_360 = 0))";
$res_elementos = $conn->query($sql_elementos);

$elementos_xr = [];
if ($res_elementos) {
    while ($row = $res_elementos->fetch_assoc()) {
        $elementos_xr[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KUNTUR-XR | Editor Foto 360</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #111; color: white; display: flex; height: 100vh; overflow: hidden; }
        
        .main-content { flex: 1; position: relative; height: 100vh; }

        .xr-panel {
            position: absolute; top: 15px; width: 320px;
            background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(10px); color: white;
            padding: 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1); z-index: 100;
            max-height: calc(100vh - 30px); overflow-y: auto;
        }
        
        .xr-panel::-webkit-scrollbar { width: 6px; }
        .xr-panel::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); border-radius: 10px; }
        .xr-panel::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 10px; }

        #panel-tools { left: 15px; }
        #panel-layers { right: 15px; }

        .xr-panel h2 { 
            font-size: 1.2rem; margin-bottom: 15px; padding-bottom: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 10px;
        }
        .xr-panel h2 i { color: #00d4ff; }

        .section { background: rgba(0,0,0,0.2); padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.05); }
        .section b { display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }

        button.xr-btn {
            background: #023675; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer;
            font-weight: 500; font-size: 0.85rem; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; margin: 2px;
        }
        button.xr-btn:hover { background: #00d4ff; color: #023675; }
        button.xr-btn-icon { width: 35px; height: 35px; padding: 0; font-size: 1rem; }

        select, input[type=range], input[type=file] {
            width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 6px; margin-bottom: 8px;
        }

        #lista { margin-top: 10px; }
        .item { background: rgba(255,255,255,0.05); padding: 10px; border-radius: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; border: 1px solid transparent; transition: 0.2s; }
        .item:hover { background: rgba(255,255,255,0.1); }
        .item.selected { border-color: #00d4ff; background: rgba(0, 212, 255, 0.1); }
        .item-actions button { background: none; border: none; color: #cbd5e1; cursor: pointer; padding: 5px; transition: 0.2s; }
        .item-actions button:hover { color: white; }
        .item-actions button.del:hover { color: #ef4444; }

        .flex-row { display: flex; gap: 5px; justify-content: center; flex-wrap: wrap; }
    </style>
</head>

<body>

    <a href="editor_xr.php" style="position: absolute; top: 15px; left: 15px; z-index: 1000; background: rgba(15, 23, 42, 0.85); color: white; padding: 10px 15px; border-radius: 8px; text-decoration: none; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2); font-size: 0.9rem; font-weight: 600;">
        <i class="fas fa-arrow-left"></i> Volver
    </a>

    <div class="main-content">
        
        <div class="xr-panel" id="panel-tools">

          <a href="editor_xr.php" style="display: block; background: #334155; color: white; padding: 10px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 600; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s;" onmouseover="this.style.background='#ef4444'" onmouseout="this.style.background='#334155'">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>

            <h2><i class="fas fa-tools"></i> Herramientas XR</h2>

            <div class="section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <b style="margin-bottom: 0;">Video 360° (Fondo)</b>
                    <div style="display: flex; gap: 4px;">
                        <button class="xr-btn xr-btn-icon" style="width: 28px; height: 28px; font-size: 0.75rem;" onclick="playFondo()" title="Reproducir"><i class="fas fa-play"></i></button>
                        <button class="xr-btn xr-btn-icon" style="width: 28px; height: 28px; font-size: 0.75rem;" onclick="pauseFondo()" title="Pausar"><i class="fas fa-pause"></i></button>
                        <button class="xr-btn xr-btn-icon" style="width: 28px; height: 28px; font-size: 0.75rem;" onclick="restartFondo()" title="Reiniciar"><i class="fas fa-redo"></i></button>
                    </div>
                </div>
                <select id="comboFondo360" onchange="cambiarFondo360(this.value)">
                    <?php if (count($videos_360) > 0): ?>
                        <?php foreach($videos_360 as $v): ?>
                            <option value="<?= htmlspecialchars($v['url_archivo']) ?>">
                                <?= htmlspecialchars($v['observaciones'] ?: 'Video ID: '.$v['id_multimedia']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="https://ucarecdn.com/bcece0a8-86ce-460e-856b-40dac4875f15/">Video de prueba (Sin videos subidos)</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="section">
                <b>Agregar Texto</b>
                <button class="xr-btn" style="width: 100%;" onclick="agregarTexto()"><i class="fas fa-font"></i> Nuevo Texto</button>
            </div>

            <div class="section">
                <b>Agregar Elemento (Imagen/3D)</b>
                <select id="comboImagen">
                    <?php if (count($elementos_xr) > 0): ?>
                        <?php foreach($elementos_xr as $el): ?>
                            <?php $etiqueta = ($el['tipo_archivo'] == 'MODELO_3D') ? '(3D)' : '(Imagen)'; ?>
                            <option value="<?= htmlspecialchars($el['url_archivo']) ?>">
                                <?= $etiqueta ?> <?= htmlspecialchars($el['observaciones'] ?: 'Elemento ID: '.$el['id_multimedia']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="https://www.kingsoft.pe/kunturvr/imagenes/logo1.png">(Imagen) Logo KunturVR</option>
                    <?php endif; ?>
                </select>
                <button class="xr-btn" style="width: 100%;" onclick="agregarElemento()"><i class="fas fa-plus"></i> Insertar en Escena</button>
            </div>

            <div class="section">
                <b>Transformación 3D</b>
                <div style="font-size: 0.8rem; margin-bottom: 5px; color: #94a3b8; text-align: center;">Mover Posición</div>
                <div class="flex-row">
                    <button class="xr-btn xr-btn-icon" title="Izquierda" onclick="mover(-0.3,0)"><i class="fas fa-arrow-left"></i></button>
                    <button class="xr-btn xr-btn-icon" title="Arriba" onclick="mover(0,0.3)"><i class="fas fa-arrow-up"></i></button>
                    <button class="xr-btn xr-btn-icon" title="Abajo" onclick="mover(0,-0.3)"><i class="fas fa-arrow-down"></i></button>
                    <button class="xr-btn xr-btn-icon" title="Derecha" onclick="mover(0.3,0)"><i class="fas fa-arrow-right"></i></button>
                </div>
                
                <div style="font-size: 0.8rem; margin: 10px 0 5px; color: #94a3b8; text-align: center;">Profundidad</div>
                <div class="flex-row">
                    <button class="xr-btn" onclick="profundidad(-0.3)"><i class="fas fa-minus"></i> Alejar</button>
                    <button class="xr-btn" onclick="profundidad(0.3)"><i class="fas fa-plus"></i> Acercar</button>
                </div>
            </div>

            <div class="section">
                <b>Rotación</b>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; text-align: center; font-size: 0.8rem;">
                    <div>Horizontal</div>
                    <div>Vertical</div>
                    <div>
                        <button class="xr-btn xr-btn-icon" onclick="rotarY(-5)">⟲</button>
                        <button class="xr-btn xr-btn-icon" onclick="rotarY(5)">⟳</button>
                    </div>
                    <div>
                        <button class="xr-btn xr-btn-icon" onclick="rotarX(5)">⬆</button>
                        <button class="xr-btn xr-btn-icon" onclick="rotarX(-5)">⬇</button>
                    </div>
                </div>
            </div>

            <div class="section">
                <b>Órbita (Alrededor de la cámara)</b>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; text-align: center; font-size: 0.8rem;">
                    <div>Horizontal</div>
                    <div>Vertical</div>
                    <div>
                        <button class="xr-btn xr-btn-icon" onclick="orbitarHorizontal(-5)">⟲</button>
                        <button class="xr-btn xr-btn-icon" onclick="orbitarHorizontal(5)">⟳</button>
                    </div>
                    <div>
                        <button class="xr-btn xr-btn-icon" onclick="orbitarVertical(5)">⬆</button>
                        <button class="xr-btn xr-btn-icon" onclick="orbitarVertical(-5)">⬇</button>
                    </div>
                </div>
            </div>

            <div class="section">
                <b>Escala (Imágenes y 3D)</b>
                <div class="flex-row">
                    <button class="xr-btn" onclick="escalar(-0.2)"><i class="fas fa-compress-alt"></i> Achicar</button>
                    <button class="xr-btn" onclick="escalar(0.2)"><i class="fas fa-expand-alt"></i> Agrandar</button>
                </div>
            </div>

            <div class="section">
                <b>Estilos (Texto)</b>
                <select onchange="cambiarColor(this.value)">
                    <option value="white">Color: Blanco</option>
                    <option value="yellow" selected>Color: Amarillo</option>
                    <option value="red">Color: Rojo</option>
                    <option value="green">Color: Verde</option>
                    <option value="blue">Color: Azul</option>
                    <option value="black">Color: Negro</option>
                </select>
                <select onchange="cambiarTamano(this.value)">
                    <option value="3">Tamaño: Pequeño</option>
                    <option value="6" selected>Tamaño: Normal</option>
                    <option value="10">Tamaño: Grande</option>
                    <option value="15">Tamaño: Gigante</option>
                </select>
            </div>
        </div>

        <div class="xr-panel" id="panel-layers">
            <h2><i class="fas fa-layer-group"></i> Elementos</h2>

            <button class="xr-btn" style="width: 100%; background: #10b981; color: white; margin-bottom: 15px;" onclick="guardarEscena()">
                <i class="fas fa-save"></i> Guardar Proyecto XR
            </button>
            
            <div class="section">
                <b>Capas en la escena</b>
                <div id="lista">
                    <div style="color:#64748b; font-size:0.85rem; text-align:center; padding:10px;">La escena está vacía</div>
                </div>
            </div>

            <h2><i class="fas fa-music"></i> Audio Ambiental</h2>
            <div class="section">
                <input type="file" id="audioFile" accept="audio/mp3">
                <button class="xr-btn" style="width: 100%; margin-bottom: 10px;" onclick="cargarAudio()"><i class="fas fa-upload"></i> Cargar MP3</button>
                
                <div class="flex-row">
                    <button class="xr-btn xr-btn-icon" onclick="playAudio()"><i class="fas fa-play"></i></button>
                    <button class="xr-btn xr-btn-icon" onclick="stopAudio()"><i class="fas fa-stop"></i></button>
                    <button class="xr-btn xr-btn-icon" style="background:#ef4444;" onclick="eliminarAudio()"><i class="fas fa-trash"></i></button>
                </div>
                
                <b style="margin-top: 15px;">Volumen</b>
                <input type="range" min="0" max="1" step="0.01" value="1" onchange="cambiarVolumen(this.value)">
            </div>
        </div>

        <a-scene embedded style="width: 100%; height: 100%;">
            <a-assets>
                <video id="video360" src="<?= htmlspecialchars($video_inicial) ?>" crossorigin="anonymous" autoplay loop muted playsinline></video>
                <img id="img1" src="https://www.kingsoft.pe/kunturvr/imagenes/logo1.png" crossorigin="anonymous">
            </a-assets>

            <a-videosphere id="cielo360" src="#video360" rotation="0 -90 0"></a-videosphere>

            <a-entity camera look-controls position="0 1.6 0">
                <a-cursor raycaster="objects: .objeto" color="#00d4ff" animation__click="property: scale; startEvents: click; easing: easeInCubic; dur: 150; from: 0.1 0.1 0.1; to: 1 1 1"></a-cursor>
            </a-entity>
        </a-scene>

    </div>


   
    <script>
        let textos = [];
        let seleccionado = null;
        let contador = 0;
        let audioGlobal = null;
        let proyectosGuardados = <?php echo json_encode($proyectos_guardados, JSON_UNESCAPED_SLASHES); ?>;

        function agregarTexto(){
            let contenido = prompt("Escribe el texto a mostrar:");
            if(!contenido) return;
            
            let id = "obj" + contador++;
            let entity = document.createElement("a-text");
            entity.setAttribute("value", contenido);
            entity.setAttribute("color", "yellow");
            entity.setAttribute("align", "center");
            entity.setAttribute("width", "6");
            entity.setAttribute("class", "objeto");
            entity.setAttribute("position", "0 1.6 -3");
            entity.setAttribute("id", id);
            
            entity.addEventListener("click", function(){ seleccionar(id) });
            document.querySelector("a-scene").appendChild(entity);
            
            textos.push({id: id, texto: "Texto: " + contenido.substring(0, 10)});
            actualizarLista();
            seleccionar(id);
        }

        function actualizarLista(){
            let lista = document.getElementById("lista");
            lista.innerHTML = "";
            
            if(textos.length === 0){
                lista.innerHTML = '<div style="color:#64748b; font-size:0.85rem; text-align:center; padding:10px;">La escena está vacía</div>';
                return;
            }

            textos.forEach((t) => {
                let div = document.createElement("div");
                div.className = "item";
                if(t.id === seleccionado) div.classList.add("selected");
                
                let iconClass = 'fa-font';
                if(t.texto.includes('Imagen')) iconClass = 'fa-image';
                if(t.texto.includes('3D')) iconClass = 'fa-cube';

                div.innerHTML = `
                    <span style="cursor:pointer; flex:1;" onclick="seleccionar('${t.id}')"><i class="fas ${iconClass}" style="color:#00d4ff; width:20px;"></i> ${t.texto}</span>
                    <div class="item-actions">
                        <button onclick="eliminar('${t.id}')" class="del" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </div>
                `;
                lista.appendChild(div);
            });
        }

        function seleccionar(id){
            seleccionado = id;
            
            document.querySelectorAll("a-text").forEach(t => { 
                if(t.getAttribute("data-color-original")) {
                    t.setAttribute("color", t.getAttribute("data-color-original"));
                } else {
                    t.setAttribute("color", "yellow"); 
                }
            });

            let el = document.getElementById(id);
            if(el && el.tagName == "A-TEXT") {
                if(!el.getAttribute("data-color-original")) el.setAttribute("data-color-original", el.getAttribute("color"));
                el.setAttribute("color", "red");
            }
            
            actualizarLista(); 
        }

        function mover(x,y){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            let p = el.getAttribute("position");
            p.x += x; p.y += y;
            el.setAttribute("position", p);
        }

        function profundidad(z){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            let p = el.getAttribute("position");
            p.z += z;
            el.setAttribute("position", p);
        }

        function rotarY(g){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            let r = el.getAttribute("rotation");
            r.y += g;
            el.setAttribute("rotation", r);
        }

        function rotarX(g){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            let r = el.getAttribute("rotation");
            r.x += g;
            el.setAttribute("rotation", r);
        }

        function orbitarHorizontal(grados){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            let p = el.getAttribute("position");
            let r = Math.sqrt(p.x*p.x + p.z*p.z);
            let angulo = Math.atan2(p.z, p.x);
            angulo += grados*Math.PI/180;
            p.x = r*Math.cos(angulo);
            p.z = r*Math.sin(angulo);
            el.setAttribute("position", p);
        }

        function orbitarVertical(grados){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            let p = el.getAttribute("position");
            let r = Math.sqrt(p.x*p.x + p.y*p.y + p.z*p.z);
            let theta = Math.atan2(p.y, Math.sqrt(p.x*p.x+p.z*p.z));
            theta += grados*Math.PI/180;
            let phi = Math.atan2(p.z, p.x);
            p.x = r*Math.cos(theta)*Math.cos(phi);
            p.y = r*Math.sin(theta);
            p.z = r*Math.cos(theta)*Math.sin(phi);
            el.setAttribute("position", p);
        }

        function escalar(v){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            if(el.tagName != "A-IMAGE" && el.tagName != "A-ENTITY") return; 
            
            let s = el.getAttribute("scale");
            if(typeof s === 'string') {
               let parts = s.split(' ');
               s = {x: parseFloat(parts[0]), y: parseFloat(parts[1]), z: parseFloat(parts[2])};
            }
            
            s.x += v; 
            s.y += v;
            if(el.tagName == "A-ENTITY") s.z += v; 
            
            if(s.x < 0.1) { s.x = 0.1; s.y = 0.1; if(s.z) s.z = 0.1; }
            
            el.setAttribute("scale", s);
        }

        function cambiarColor(c){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            if(el.tagName != "A-TEXT") return;
            el.setAttribute("color", c);
            el.setAttribute("data-color-original", c); 
        }

        function cambiarTamano(s){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            if(el.tagName != "A-TEXT") return;
            el.setAttribute("width", s);
        }

        function eliminar(id){
            let el = document.getElementById(id);
            if(el) el.parentNode.removeChild(el);
            textos = textos.filter(t => t.id != id);
            if(seleccionado == id) seleccionado = null;
            actualizarLista();
        }

        function cargarAudio(){
            let file = document.getElementById("audioFile").files[0];
            if(!file){ alert("Selecciona un archivo MP3 desde tu computadora"); return; }
            let url = URL.createObjectURL(file);
            if(audioGlobal) audioGlobal.pause();
            audioGlobal = new Audio(url);
            audioGlobal.loop = true;
            alert("Audio cargado y listo para reproducir.");
        }

        function playAudio(){ if(audioGlobal) audioGlobal.play(); }
        function stopAudio(){ if(audioGlobal){ audioGlobal.pause(); audioGlobal.currentTime = 0; } }
        function eliminarAudio(){ if(audioGlobal){ audioGlobal.pause(); audioGlobal = null; alert("Audio eliminado"); } }
        function cambiarVolumen(v){ if(audioGlobal) audioGlobal.volume = v; }

        function cambiarFondo360(url) {
            let videoEl = document.getElementById("video360");
            videoEl.setAttribute("src", url);
            videoEl.load(); // Forzamos a recargar el video nuevo
            videoEl.play(); // Le damos play

            document.querySelectorAll('.objeto').forEach(el => {
                if(el.parentNode) el.parentNode.removeChild(el);
            });
            textos = [];
            seleccionado = null;
            actualizarLista();

            if (proyectosGuardados[url]) {
                reconstruirEscena(proyectosGuardados[url]);
            }
        }

        function agregarElemento(){
            let url = document.getElementById("comboImagen").value;
            let isGLB = url.toLowerCase().endsWith('.glb');
            let id = "obj" + contador++;
            
            let entity;
            let textoLista = "";

            if (isGLB) {
                entity = document.createElement("a-entity");
                entity.setAttribute("gltf-model", url);
                entity.setAttribute("scale", "0.5 0.5 0.5"); 
                textoLista = "3D";
            } else {
                entity = document.createElement("a-image");
                entity.setAttribute("src", url);
                let tmpImg = new Image();
                tmpImg.src = url;
                tmpImg.onload = function(){
                    let w = tmpImg.width;
                    let h = tmpImg.height;
                    let maxDimension = 2; 
                    if(w >= h){
                        entity.setAttribute("scale", `${maxDimension} ${maxDimension*h/w} 1`);
                    } else {
                        entity.setAttribute("scale", `${maxDimension*w/h} ${maxDimension} 1`);
                    }
                }
                textoLista = "Imagen";
            }

            entity.setAttribute("position", "0 1.6 -3");
            entity.setAttribute("class", "objeto");
            entity.setAttribute("id", id);
            
            entity.addEventListener("click", function(){ seleccionar(id) });
            document.querySelector("a-scene").appendChild(entity);
            
            textos.push({id: id, texto: textoLista});
            actualizarLista();
            seleccionar(id);
        }

        function guardarEscena() {
            // 1. Extraer el fondo del VIDEO 360
            let escenaData = {
                fondo: document.getElementById("video360").getAttribute("src"),
                elementos: []
            };

            document.querySelectorAll('.objeto').forEach(el => {
                let obj = {
                    id: el.getAttribute("id"),
                    tag: el.tagName.toLowerCase(),
                    position: el.getAttribute("position"),
                    rotation: el.getAttribute("rotation") || {x:0, y:0, z:0},
                    scale: el.getAttribute("scale") || {x:1, y:1, z:1}
                };

                if (obj.tag === "a-text") {
                    obj.value = el.getAttribute("value");
                    obj.color = el.getAttribute("data-color-original") || el.getAttribute("color");
                    obj.width = el.getAttribute("width");
                } else if (obj.tag === "a-image") {
                    obj.src = el.getAttribute("src");
                } else if (obj.tag === "a-entity") {
                    obj.gltf_model = el.getAttribute("gltf-model");
                }
                escenaData.elementos.push(obj);
            });

            let jsonFinal = JSON.stringify(escenaData);
            
            let btnGuardar = document.querySelector('button[onclick="guardarEscena()"]');
            let textoOriginal = btnGuardar.innerHTML;
            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            btnGuardar.disabled = true;

            // AQUÍ ESTÁ EL ARREGLO: Se declara una sola vez apuntando al video
            let fondoActual = document.getElementById("video360").getAttribute("src");
            
            let formData = new FormData();
            formData.append('json_data', jsonFinal);
            formData.append('nombre_proyecto', fondoActual); 

            fetch('guardar_xr_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'ok') {
                    alert('✅ Proyecto guardado correctamente en la Base de Datos.');
                    proyectosGuardados[fondoActual] = jsonFinal;
                } else {
                    alert('❌ Error al guardar: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error de conexión al servidor.');
                console.error(error);
            })
            .finally(() => {
                btnGuardar.innerHTML = textoOriginal;
                btnGuardar.disabled = false;
            });
        }


        // --- INICIADOR AUTOMÁTICO ---
        // --- INICIADOR AUTOMÁTICO ---
        window.onload = function() {
            setTimeout(() => {
                let urlInicial = "<?= htmlspecialchars($video_inicial) ?>";
                // Forzar al select a ponerse en el video que pidió el usuario por URL
                document.getElementById("comboFondo360").value = urlInicial;
                // Cargar el video y sus elementos guardados
                cambiarFondo360(urlInicial);
            }, 500);
        };

        function reconstruirEscena(jsonString) {
            if (!jsonString || jsonString === "") return;
            let data = JSON.parse(jsonString);
            
            let videoEl = document.getElementById("video360");
            videoEl.setAttribute("src", data.fondo);
            videoEl.load();
            videoEl.play();

            data.elementos.forEach(obj => {
                let el = document.createElement(obj.tag);
                el.setAttribute("id", obj.id);
                el.setAttribute("position", obj.position);
                el.setAttribute("rotation", obj.rotation);
                el.setAttribute("scale", obj.scale);
                el.setAttribute("class", "objeto");

                let textoLista = "";
                if (obj.tag === "a-text") {
                    el.setAttribute("value", obj.value);
                    el.setAttribute("color", obj.color);
                    el.setAttribute("data-color-original", obj.color);
                    el.setAttribute("width", obj.width);
                    el.setAttribute("align", "center");
                    textoLista = "Texto: " + obj.value.substring(0, 10);
                } else if (obj.tag === "a-image") {
                    el.setAttribute("src", obj.src);
                    textoLista = "Imagen";
                } else if (obj.tag === "a-entity") {
                    el.setAttribute("gltf-model", obj.gltf_model);
                    textoLista = "3D";
                }

                el.addEventListener("click", function(){ seleccionar(obj.id) });
                document.querySelector("a-scene").appendChild(el);

                textos.push({id: obj.id, texto: textoLista});
                
                let numId = parseInt(obj.id.replace("obj", ""));
                if(numId >= contador) contador = numId + 1;
            });

            actualizarLista();
            
            document.getElementById("comboFondo360").value = data.fondo;
        }


        // --- DESBLOQUEO DE AUTOPLAY DE VIDEO ---
        // Los navegadores bloquean el video hasta que el usuario interactúa con la página.
        // Con esto, al primer clic que haga en cualquier lado, el video arrancará.
        document.body.addEventListener('click', function() {
            let videoEl = document.getElementById("video360");
            if (videoEl && videoEl.paused) {
                videoEl.play().catch(e => console.log("Esperando interacción para reproducir..."));
            }
        }, { once: true }); // { once: true } hace que este evento se borre después del primer clic
        // --- CONTROLES DEL VIDEO DE FONDO ---
        function playFondo() {
            let video = document.getElementById("video360");
            if(video) video.play();
        }
        function pauseFondo() {
            let video = document.getElementById("video360");
            if(video) video.pause();
        }
        function restartFondo() {
            let video = document.getElementById("video360");
            if(video) {
                video.currentTime = 0; // Regresa al segundo 0
                video.play();
            }
        }
    </script>
</body>
</html>