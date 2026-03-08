<?php
session_start();
include 'includes/db.php';
include 'includes/seguridad.php';

// Validar que no sea Admin
if ($_SESSION['id_rol'] == 1) { header("Location: dashboard_admin.php"); exit(); }

$id_user = $_SESSION['id_usuario'];

// --- Extraer a qué empresa pertenece el usuario ---
$q_empresa = $conn->query("SELECT id_cliente FROM usuarios WHERE id_usuario = $id_user");
$id_cliente = $q_empresa->fetch_assoc()['id_cliente'];

// --- Extraer TODOS los proyectos guardados de este usuario ---
$sql_proyectos = "SELECT nombre_proyecto, json_config FROM proyectos_xr WHERE id_usuario = $id_user";
$res_proyectos = $conn->query($sql_proyectos);
$proyectos_guardados = [];
if ($res_proyectos && $res_proyectos->num_rows > 0) {
    while ($row = $res_proyectos->fetch_assoc()) {
        $proyectos_guardados[$row['nombre_proyecto']] = $row['json_config'];
    }
}

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

// Escuchar si viene un id_media por la URL
$id_modelo_solicitado = isset($_GET['id_media']) ? intval($_GET['id_media']) : null;
$modelo_inicial = 'https://cdn.aframe.io/test-models/models/glTF-2.0/virtualcity/VC.gltf'; // Por defecto

if (count($modelos_3d) > 0) {
    if ($id_modelo_solicitado) {
        // Buscar la URL que corresponda a ese ID
        foreach ($modelos_3d as $m) {
            if ($m['id_multimedia'] == $id_modelo_solicitado) {
                $modelo_inicial = $m['url_archivo'];
                break;
            }
        }
    } else {
        // Si no viene ID, cargar el primero de la lista
        $modelo_inicial = $modelos_3d[0]['url_archivo'];
    }
}

// --- Traer fotos NORMALES (Para usar como señaléticas) ---
$sql_fotos = "SELECT m.id_multimedia, m.url_archivo, m.observaciones 
              FROM multimedia m
              INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
              WHERE u.id_cliente = $id_cliente AND m.tipo_archivo = 'FOTO' AND m.es_360 = 0";
$res_fotos = $conn->query($sql_fotos);

$fotos_normales = [];
if ($res_fotos) {
    while ($row = $res_fotos->fetch_assoc()) {
        $fotos_normales[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KUNTUR-XR | Editor Modelo 3D</title>

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
        .xr-panel h3 { font-size: 1rem; color: #00d4ff; margin-bottom: 10px; margin-top: 10px; border-bottom: 1px dashed rgba(255,255,255,0.2); padding-bottom: 5px;}

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

    <?php include 'includes/navbar_cliente.php'; ?>

    <div class="main-content">
        
        <div class="xr-panel" id="panel-tools">
            
            <a href="editor_xr.php" style="display: block; background: #334155; color: white; padding: 10px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 600; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s;" onmouseover="this.style.background='#ef4444'" onmouseout="this.style.background='#334155'">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>

            <h2><i class="fas fa-tools"></i> Herramientas XR</h2>

            <div class="section">
                <b>Modelo 3D Principal (Base)</b>
                <select id="comboModeloPrincipal" onchange="cambiarModeloPrincipal(this.value)">
                    <?php if (count($modelos_3d) > 0): ?>
                        <?php foreach($modelos_3d as $m): ?>
                            <option value="<?= htmlspecialchars($m['url_archivo']) ?>">
                                <?= htmlspecialchars($m['observaciones'] ?: 'Modelo ID: '.$m['id_multimedia']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="https://cdn.aframe.io/test-models/models/glTF-2.0/virtualcity/VC.gltf">Modelo de prueba (Sin modelos subidos)</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="section">
                <b>Agregar Texto</b>
                <button class="xr-btn" style="width: 100%;" onclick="agregarTexto()"><i class="fas fa-font"></i> Nuevo Texto</button>
            </div>

            <div class="section">
                <b>Agregar Imagen (Señalética)</b>
                <select id="comboImagen">
                    <?php if (count($fotos_normales) > 0): ?>
                        <?php foreach($fotos_normales as $fn): ?>
                            <option value="<?= htmlspecialchars($fn['url_archivo']) ?>">
                                <?= htmlspecialchars($fn['observaciones'] ?: 'Imagen ID: '.$fn['id_multimedia']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="https://www.kingsoft.pe/kunturvr/imagenes/logo1.png">Logo KunturVR</option>
                    <?php endif; ?>
                </select>
                <button class="xr-btn" style="width: 100%;" onclick="agregarImagen()"><i class="fas fa-image"></i> Insertar Imagen</button>
            </div>

            <div class="section">
                <b>Mover Elemento</b>
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
                <b>Escala y Estilos</b>
                <div class="flex-row" style="margin-bottom:10px;">
                    <button class="xr-btn" onclick="escalar(-0.2)"><i class="fas fa-compress-alt"></i> Achicar</button>
                    <button class="xr-btn" onclick="escalar(0.2)"><i class="fas fa-expand-alt"></i> Agrandar</button>
                </div>
                <select onchange="cambiarColor(this.value)">
                    <option value="white">Color Texto: Blanco</option>
                    <option value="yellow" selected>Color Texto: Amarillo</option>
                    <option value="red">Color Texto: Rojo</option>
                    <option value="green">Color Texto: Verde</option>
                </select>
            </div>
        </div>

        <div class="xr-panel" id="panel-layers">
            
            <h3><i class="fas fa-cube"></i> Controles del Modelo 3D</h3>
            <div class="section">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; text-align: center; font-size: 0.8rem;">
                    <div>Girar H.</div>
                    <div>Girar V.</div>
                    <div>
                        <button class="xr-btn xr-btn-icon" onclick="rotarModeloY(-10)">⟲</button>
                        <button class="xr-btn xr-btn-icon" onclick="rotarModeloY(10)">⟳</button>
                    </div>
                    <div>
                        <button class="xr-btn xr-btn-icon" onclick="rotarModeloX(10)">⬆</button>
                        <button class="xr-btn xr-btn-icon" onclick="rotarModeloX(-10)">⬇</button>
                    </div>
                </div>
                <div style="font-size: 0.8rem; margin: 10px 0 5px; color: #94a3b8; text-align: center;">Tamaño del Modelo</div>
                <div class="flex-row">
                    <button class="xr-btn" onclick="escalarModelo(-0.2)"><i class="fas fa-search-minus"></i></button>
                    <button class="xr-btn" onclick="escalarModelo(0.2)"><i class="fas fa-search-plus"></i></button>
                </div>
            </div>

            <h2><i class="fas fa-layer-group"></i> Elementos</h2>
            <button class="xr-btn" style="width: 100%; background: #10b981; color: white; margin-bottom: 15px;" onclick="guardarEscena()">
                <i class="fas fa-save"></i> Guardar Proyecto XR
            </button>
            
            <div class="section">
                <b>Capas Añadidas</b>
                <div id="lista">
                    <div style="color:#64748b; font-size:0.85rem; text-align:center; padding:10px;">Sin elementos</div>
                </div>
            </div>

            <h2><i class="fas fa-music"></i> Audio</h2>
            <div class="section">
                <input type="file" id="audioFile" accept="audio/mp3">
                <button class="xr-btn" style="width: 100%; margin-bottom: 10px;" onclick="cargarAudio()"><i class="fas fa-upload"></i> Cargar</button>
                <div class="flex-row">
                    <button class="xr-btn xr-btn-icon" onclick="playAudio()"><i class="fas fa-play"></i></button>
                    <button class="xr-btn xr-btn-icon" onclick="stopAudio()"><i class="fas fa-stop"></i></button>
                </div>
            </div>
        </div>

        <a-scene embedded style="width: 100%; height: 100%; background-color: #222;">
            <a-entity id="modelo3D"
                      gltf-model="<?= htmlspecialchars($modelo_inicial) ?>"
                      position="0 0 -5"
                      scale="1 1 1">
            </a-entity>

            <a-entity camera look-controls position="0 1.6 0">
                <a-cursor raycaster="objects: .objeto" color="#00d4ff"></a-cursor>
            </a-entity>
        </a-scene>

    </div>

    <script>
        let textos = [];
        let seleccionado = null;
        let contador = 0;
        let audioGlobal = null;
        let proyectosGuardados = <?php echo json_encode($proyectos_guardados, JSON_UNESCAPED_SLASHES); ?>;

        function cambiarModeloPrincipal(url) {
            let modelo = document.getElementById("modelo3D");
            modelo.setAttribute("gltf-model", url);
            modelo.setAttribute("rotation", "0 0 0"); // Resetear rotación
            modelo.setAttribute("scale", "1 1 1"); // Resetear escala

            // Limpiar elementos agregados
            document.querySelectorAll('.objeto').forEach(el => {
                if(el.parentNode) el.parentNode.removeChild(el);
            });
            textos = [];
            seleccionado = null;
            actualizarLista();

            // Restaurar si ya existía un guardado para este modelo
            if (proyectosGuardados[url]) {
                reconstruirEscena(proyectosGuardados[url]);
            }
        }

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
            entity.setAttribute("position", "0 2 -4");
            entity.setAttribute("id", id);
            
            entity.addEventListener("click", function(){ seleccionar(id) });
            document.querySelector("a-scene").appendChild(entity);
            
            textos.push({id: id, texto: "Texto: " + contenido.substring(0, 10)});
            actualizarLista();
            seleccionar(id);
        }

        function agregarImagen(){
            let url = document.getElementById("comboImagen").value;
            let id = "obj" + contador++;
            let img = document.createElement("a-image");
            
            img.setAttribute("src", url);
            img.setAttribute("position", "0 1.6 -3");
            img.setAttribute("class", "objeto");
            img.setAttribute("id", id);
            
            let tmpImg = new Image();
            tmpImg.src = url;
            tmpImg.onload = function(){
                let w = tmpImg.width;
                let h = tmpImg.height;
                let maxDimension = 2; 
                if(w >= h){
                    img.setAttribute("scale", `${maxDimension} ${maxDimension*h/w} 1`);
                } else {
                    img.setAttribute("scale", `${maxDimension*w/h} ${maxDimension} 1`);
                }
            }
            
            img.addEventListener("click", function(){ seleccionar(id) });
            document.querySelector("a-scene").appendChild(img);
            
            textos.push({id: id, texto: "Imagen"});
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
                
                let iconClass = t.texto.includes('Imagen') ? 'fa-image' : 'fa-font';

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

        // --- CONTROLES DE ELEMENTOS AGREGADOS ---
        function mover(x,y){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            let p = el.getAttribute("position");
            p.x += x; p.y += y; el.setAttribute("position", p);
        }
        function profundidad(z){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            let p = el.getAttribute("position");
            p.z += z; el.setAttribute("position", p);
        }
        function escalar(v){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            if(el.tagName != "A-IMAGE") return; 
            let s = el.getAttribute("scale");
            if(typeof s === 'string') {
               let parts = s.split(' ');
               s = {x: parseFloat(parts[0]), y: parseFloat(parts[1]), z: parseFloat(parts[2])};
            }
            s.x += v; s.y += v;
            if(s.x < 0.1) { s.x = 0.1; s.y = 0.1; }
            el.setAttribute("scale", s);
        }
        function cambiarColor(c){ 
            if(!seleccionado) return;
            let el = document.getElementById(seleccionado);
            if(el.tagName != "A-TEXT") return;
            el.setAttribute("color", c); el.setAttribute("data-color-original", c); 
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

        // --- CONTROLES DEL MODELO PRINCIPAL ---
        function rotarModeloY(g){
            let m = document.getElementById("modelo3D");
            let r = m.getAttribute("rotation") || {x:0, y:0, z:0};
            if(typeof r === 'string') { let p = r.split(' '); r = {x: parseFloat(p[0]), y: parseFloat(p[1]), z: parseFloat(p[2])}; }
            r.y += g; m.setAttribute("rotation", r);
        }
        function rotarModeloX(g){
            let m = document.getElementById("modelo3D");
            let r = m.getAttribute("rotation") || {x:0, y:0, z:0};
            if(typeof r === 'string') { let p = r.split(' '); r = {x: parseFloat(p[0]), y: parseFloat(p[1]), z: parseFloat(p[2])}; }
            r.x += g; m.setAttribute("rotation", r);
        }
        function escalarModelo(v){
            let m = document.getElementById("modelo3D");
            let s = m.getAttribute("scale") || {x:1, y:1, z:1};
            if(typeof s === 'string') { let p = s.split(' '); s = {x: parseFloat(p[0]), y: parseFloat(p[1]), z: parseFloat(p[2])}; }
            s.x += v; s.y += v; s.z += v;
            if(s.x < 0.1) { s.x = 0.1; s.y = 0.1; s.z = 0.1; }
            m.setAttribute("scale", s);
        }

        // --- SISTEMA DE GUARDADO ---
        function guardarEscena() {
            let modBase = document.getElementById("modelo3D");
            let urlModelo = modBase.getAttribute("gltf-model");

            let escenaData = {
                fondo: urlModelo, 
                modelRotation: modBase.getAttribute("rotation"),
                modelScale: modBase.getAttribute("scale"),
                elementos: []
            };

            document.querySelectorAll('.objeto').forEach(el => {
                let obj = {
                    id: el.getAttribute("id"),
                    tag: el.tagName.toLowerCase(),
                    position: el.getAttribute("position"),
                    scale: el.getAttribute("scale") || {x:1, y:1, z:1}
                };

                if (obj.tag === "a-text") {
                    obj.value = el.getAttribute("value");
                    obj.color = el.getAttribute("data-color-original") || el.getAttribute("color");
                    obj.width = el.getAttribute("width");
                } else if (obj.tag === "a-image") {
                    obj.src = el.getAttribute("src");
                }
                escenaData.elementos.push(obj);
            });

            let jsonFinal = JSON.stringify(escenaData);
            
            let btnGuardar = document.querySelector('button[onclick="guardarEscena()"]');
            let textoOriginal = btnGuardar.innerHTML;
            btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            btnGuardar.disabled = true;

            let formData = new FormData();
            formData.append('json_data', jsonFinal);
            formData.append('nombre_proyecto', urlModelo); 

            fetch('guardar_xr_ajax.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'ok') {
                    alert('✅ Modelo y configuración guardada correctamente.');
                    proyectosGuardados[urlModelo] = jsonFinal;
                } else {
                    alert('❌ Error al guardar: ' + data.message);
                }
            })
            .catch(error => { console.error(error); })
            .finally(() => {
                btnGuardar.innerHTML = textoOriginal;
                btnGuardar.disabled = false;
            });
        }

        function reconstruirEscena(jsonString) {
            if (!jsonString || jsonString === "") return;
            let data = JSON.parse(jsonString);
            
            let modBase = document.getElementById("modelo3D");
            modBase.setAttribute("gltf-model", data.fondo);
            if(data.modelRotation) modBase.setAttribute("rotation", data.modelRotation);
            if(data.modelScale) modBase.setAttribute("scale", data.modelScale);

            data.elementos.forEach(obj => {
                let el = document.createElement(obj.tag);
                el.setAttribute("id", obj.id);
                el.setAttribute("position", obj.position);
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
                }

                el.addEventListener("click", function(){ seleccionar(obj.id) });
                document.querySelector("a-scene").appendChild(el);
                textos.push({id: obj.id, texto: textoLista});
                
                let numId = parseInt(obj.id.replace("obj", ""));
                if(numId >= contador) contador = numId + 1;
            });
            actualizarLista();
            document.getElementById("comboModeloPrincipal").value = data.fondo;
        }

        window.onload = function() {
            setTimeout(() => {
                let urlInicial = "<?= htmlspecialchars($modelo_inicial) ?>";
                // Forzar al select a ponerse en el modelo 3D que pidió el usuario por URL
                document.getElementById("comboModeloPrincipal").value = urlInicial;
                // Cargar el modelo 3D y sus elementos guardados
                cambiarModeloPrincipal(urlInicial);
            }, 500);
        };

        // --- AUDIO ---
        function cargarAudio(){
            let file = document.getElementById("audioFile").files[0];
            if(!file) return;
            let url = URL.createObjectURL(file);
            if(audioGlobal) audioGlobal.pause();
            audioGlobal = new Audio(url); audioGlobal.loop = true; alert("Audio listo");
        }
        function playAudio(){ if(audioGlobal) audioGlobal.play(); }
        function stopAudio(){ if(audioGlobal){ audioGlobal.pause(); audioGlobal.currentTime = 0; } }

    </script>
</body>
</html>