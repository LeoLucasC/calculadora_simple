<?php
session_start();
include 'includes/db.php';
include 'includes/seguridad.php';

$id_user = $_SESSION['id_usuario'];

// --- Extraer a qué empresa pertenece el usuario ---
$q_empresa = $conn->query("SELECT id_cliente FROM usuarios WHERE id_usuario = $id_user");
$id_cliente = $q_empresa->fetch_assoc()['id_cliente'];

// --- Traer Fotos 360 de la empresa ---
$sql_fotos360 = "SELECT m.id_multimedia, m.url_archivo, m.observaciones 
                 FROM multimedia m
                 INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                 WHERE u.id_cliente = $id_cliente AND m.tipo_archivo = 'FOTO' AND m.es_360 = 1";
$res_fotos = $conn->query($sql_fotos360);

$fotos_360 = [];
if ($res_fotos) {
    while ($row = $res_fotos->fetch_assoc()) {
        $fotos_360[] = $row;
    }
}
$foto_inicial = (count($fotos_360) > 0) ? $fotos_360[0]['url_archivo'] : 'https://aframe.io/aframe/examples/boilerplate/panorama/puydesancy.jpg';

// --- Traer TODOS los proyectos guardados POR CUALQUIER USUARIO DE LA EMPRESA ---
$sql_proyectos = "SELECT p.nombre_proyecto, p.json_config 
                  FROM proyectos_xr p
                  INNER JOIN usuarios u ON p.id_usuario = u.id_usuario
                  WHERE u.id_cliente = $id_cliente";
                  
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
    <title>KUNTUR-XR | Galería de Tour 360</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #111; color: white; display: flex; height: 100vh; overflow: hidden; }
        
        .main-content { flex: 1; position: relative; height: 100vh; }

        /* HEADER FLOTANTE (Para salir de la galería) */
        .top-bar {
            position: absolute; top: 15px; left: 15px; z-index: 100;
            background: rgba(0, 0, 0, 0.6); padding: 10px 20px; border-radius: 8px;
            backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1);
        }
        .top-bar h2 { font-size: 1.2rem; color: #fff; }
        .top-bar p { font-size: 0.8rem; color: #00d4ff; margin-top: 2px;}

        /* SLIDER INFERIOR ESTILO ORBIX360 */
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
            transition: all 0.3s ease;
        }
        .thumb-item img {
            width: 100%; height: 80px; object-fit: cover; border-radius: 8px;
            border: 3px solid transparent; transition: border 0.3s;
        }
        .thumb-item.active img, .thumb-item:hover img { border-color: #00d4ff; transform: translateY(-3px); }
        .thumb-item span {
            display: block; font-size: 0.75rem; margin-top: 5px; 
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #cbd5e1;
        }
        .thumb-item.active span, .thumb-item:hover span { color: white; font-weight: bold; }
    </style>
</head>

<body>

    <?php include 'includes/navar_viewer.php'; ?>

    <div class="main-content">
        
        <div class="top-bar">
            <h2>KUNTUR-XR</h2>
            <p>Galería Interactiva 360°</p>
        </div>

        <div class="slider-container">
            <?php if (count($fotos_360) > 0): ?>
                <?php foreach($fotos_360 as $index => $f): ?>
                    <div class="thumb-item <?= ($index === 0) ? 'active' : '' ?>" 
                         onclick="cambiarEscena(this, '<?= htmlspecialchars($f['url_archivo']) ?>')">
                        <img src="<?= htmlspecialchars($f['url_archivo']) ?>" alt="Thumbnail">
                        <span><?= htmlspecialchars($f['observaciones'] ?: 'Escena '.($index+1)) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="thumb-item active">
                    <img src="https://aframe.io/aframe/examples/boilerplate/panorama/puydesancy.jpg" alt="Demo">
                    <span>Escena Demo</span>
                </div>
            <?php endif; ?>
        </div>

        <a-scene embedded style="width: 100%; height: 100%;">
            <a-assets>
                <img id="foto360" src="<?= htmlspecialchars($foto_inicial) ?>" crossorigin="anonymous"> 
            </a-assets>

            <a-sky id="cielo360" src="#foto360"></a-sky>

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
                cargarElementosGuardados("<?= htmlspecialchars($foto_inicial) ?>");
            }, 500);
        };

        function cambiarEscena(elementoHTML, urlFondo) {
            // 1. Efecto visual en el Slider (Pintar el seleccionado)
            document.querySelectorAll('.thumb-item').forEach(el => el.classList.remove('active'));
            elementoHTML.classList.add('active');

            // 2. Cambiar el fondo 360
            let cielo = document.getElementById("cielo360");
            cielo.setAttribute("src", urlFondo);

            // 3. Limpiar los objetos de la escena anterior
            document.querySelectorAll('.objeto').forEach(el => {
                if(el.parentNode) el.parentNode.removeChild(el);
            });

            // 4. Cargar los nuevos objetos si esta escena fue editada y guardada
            cargarElementosGuardados(urlFondo);
        }

        function cargarElementosGuardados(url) {
            // Si hay un JSON guardado para este fondo, lo reconstruimos
            if (proyectosGuardados[url]) {
                let data = JSON.parse(proyectosGuardados[url]);
                
                data.elementos.forEach(obj => {
                    // Creamos el elemento
                    let el = document.createElement(obj.tag);
                    el.setAttribute("position", obj.position);
                    el.setAttribute("rotation", obj.rotation);
                    el.setAttribute("scale", obj.scale);
                    el.setAttribute("class", "objeto");

                    // Aplicamos propiedades según el tipo
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

                    // A diferencia del editor, aquí NO le damos eventos de "click para seleccionar"
                    // ya que el usuario en la galería solo debe "Ver", no "Editar".
                    document.querySelector("a-scene").appendChild(el);
                });
            }
        }
    </script>
</body>
</html>