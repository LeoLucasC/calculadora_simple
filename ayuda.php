<?php
session_start();
include 'includes/db.php';

// SEGURIDAD
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.html");
    exit();
}
if ($_SESSION['id_rol'] == 1) {
    header("Location: dashboard_admin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Centro de Ayuda</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1e293b; display: flex; min-height: 100vh; }
        
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }

        /* Header Corporativo */
        .page-header {
            margin-bottom: 30px;
            text-align: center;
            background: linear-gradient(135deg, #023675 0%, #0349a3 100%);
            padding: 50px 20px;
            border-radius: 20px;
            color: white;
            box-shadow: 0 10px 20px rgba(2, 54, 117, 0.15);
        }
        .page-header h1 { font-size: 2.2rem; margin-bottom: 10px; }
        .page-header p { opacity: 0.9; font-size: 1.1rem; }

        /* Buscador de Ayuda */
        .search-container { max-width: 700px; margin: -35px auto 40px; }
        .search-box {
            background: white; border-radius: 50px; padding: 8px;
            display: flex; align-items: center; box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .search-box input { flex: 1; border: none; padding: 12px 20px; font-size: 1rem; outline: none; }
        .search-box button { background: #023675; color: white; border: none; padding: 12px 25px; border-radius: 50px; cursor: pointer; font-weight: 500; transition: 0.3s; }
        .search-box button:hover { background: #0349a3; }

        /* Secciones */
        .section-title { font-size: 1.4rem; font-weight: 700; margin: 40px 0 20px; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .section-title i { color: #023675; }

        /* Cards de Categorías */
        .grid-help { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .help-card {
            background: white; padding: 25px; border-radius: 16px; border: 1px solid #e9eef2;
            transition: 0.3s; cursor: pointer; text-decoration: none; color: inherit;
        }
        .help-card:hover { transform: translateY(-5px); border-color: #023675; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .help-card i { font-size: 2rem; color: #023675; margin-bottom: 15px; display: block; }
        .help-card h3 { font-size: 1.2rem; margin-bottom: 10px; }
        .help-card p { color: #64748b; font-size: 0.9rem; line-height: 1.5; }

        /* Acordeón FAQ */
        .faq-item { background: white; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e9eef2; overflow: hidden; }
        .faq-question { padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; font-weight: 500; }
        .faq-answer { max-height: 0; overflow: hidden; transition: 0.3s ease-out; background: #f8fafc; color: #475569; line-height: 1.6; }
        .faq-item.active .faq-answer { max-height: 200px; padding: 20px 24px; }
        .faq-item.active .faq-question { color: #023675; }

        /* Contacto */
        .support-banner {
            background: white; border-radius: 20px; padding: 40px; margin-top: 50px;
            text-align: center; border: 1px solid #e9eef2;
        }
        .btn-contact {
            display: inline-flex; align-items: center; gap: 10px; background: #25d366;
            color: white; padding: 15px 30px; border-radius: 12px; text-decoration: none;
            font-weight: 600; margin-top: 20px; transition: 0.3s;
        }
        .btn-contact:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(37, 211, 102, 0.2); }

        @media (max-width: 768px) { .main-content { padding: 80px 20px 30px; } }
    </style>
</head>
<body>

    <?php include 'includes/navbar_cliente.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Centro de Soporte Operativo</h1>
            <p>Guías técnicas y soluciones para la gestión de evidencias mineras en KunturVR.</p>
        </div>

        <div class="search-container">
            <div class="search-box">
                <i class="fas fa-search" style="margin-left: 15px; color: #94a3b8;"></i>
                <input type="text" placeholder="Ej: ¿Cómo subir videos 360?, errores de carga..." id="helpSearch">
                <button onclick="searchAction()">Buscar Solución</button>
            </div>
        </div>

        <h2 class="section-title"><i class="fas fa-layer-group"></i> Áreas de Soporte</h2>
        <div class="grid-help">
            <div class="help-card">
                <i class="fas fa-cloud-upload-alt"></i>
                <h3>Gestión de Evidencias</h3>
                <p>Aprende a clasificar archivos por ubicación, bocaminas y grupos operativos correctamente.</p>
            </div>
            <div class="help-card">
                <i class="fas fa-vr-cardboard"></i>
                <h3>Visualización 360°/VR</h3>
                <p>Cómo usar dispositivos VR para inspeccionar tajos y labores de forma remota.</p>
            </div>
            <div class="help-card">
                <i class="fas fa-file-pdf"></i>
                <h3>Manuales de Usuario</h3>
                <p>Descarga protocolos de toma de fotografía técnica y estándares de video minero.</p>
            </div>
        </div>

        <h2 class="section-title"><i class="fas fa-question-circle"></i> Preguntas Frecuentes</h2>
        
        <div class="faq-container">
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    ¿Qué formatos de video se recomiendan para inspecciones?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Se recomienda usar formato **MP4 (H.264)**. Para videos 360°, asegúrate de que la resolución sea al menos 4K para permitir un zoom digital claro durante la auditoría.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    ¿Cómo subir archivos si tengo baja señal en la mina?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    KunturVR permite la carga parcial. Si la conexión se corta, los archivos se mantienen en cola. Se recomienda realizar la subida masiva en zonas con cobertura Wi-Fi estable o en oficinas de superficie.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFaq(this)">
                    ¿Quién puede ver las evidencias que subo?
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="faq-answer">
                    Tus archivos solo son visibles por el **Administrador del Sistema** y los **Supervisores** asignados a tu unidad operativa. La privacidad está garantizada por el ID de Cliente.
                </div>
            </div>
        </div>

        <div class="support-banner">
            <i class="fas fa-headset" style="font-size: 3rem; color: #023675; margin-bottom: 20px;"></i>
            <h3 class="support-title">¿Problemas Críticos?</h3>
            <p class="support-description">Si tienes dificultades para acceder a una ubicación o errores en la base de datos, contacta a nuestro equipo de soporte técnico vía WhatsApp.</p>
            <a href="https://wa.me/51987654321" class="btn-contact" target="_blank">
                <i class="fab fa-whatsapp"></i> Contactar Soporte Técnico
            </a>
        </div>
    </div>

    <script>
        function toggleFaq(element) {
            const item = element.parentElement;
            item.classList.toggle('active');
            
            // Cerrar otros
            document.querySelectorAll('.faq-item').forEach(other => {
                if (other !== item) other.classList.remove('active');
            });
        }

        function searchAction() {
            const query = document.getElementById('helpSearch').value;
            if(query) {
                alert('Buscando guías para: ' + query);
            }
        }
    </script>
</body>
</html>