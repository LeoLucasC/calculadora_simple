<?php
session_start();
include 'includes/db.php'; // 1. Conexión a la BD
include 'includes/seguridad.php'; // 2. Verificación de sesión y rol

// SEGURIDAD
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.html");
    exit();
}
// Redirección si es Admin
if ($_SESSION['id_rol'] == 1) {
    header("Location: dashboard_admin.php");
    exit();
}

// --- CONSULTAS REALES (Corregidas para evitar error de columna 'estado') ---
$id_user = $_SESSION['id_usuario'];

// 1. Subidas este mes
$sql_mes = "SELECT COUNT(*) as total FROM multimedia 
            WHERE id_usuario = $id_user 
            AND MONTH(fecha_hora) = MONTH(CURRENT_DATE()) 
            AND YEAR(fecha_hora) = YEAR(CURRENT_DATE())";
$subidas_mes = $conn->query($sql_mes)->fetch_assoc()['total'];

// 2. Total Completadas (Contamos TODAS sin filtrar por estado)
$sql_total = "SELECT COUNT(*) as total FROM multimedia WHERE id_usuario = $id_user";
$completadas = $conn->query($sql_total)->fetch_assoc()['total'];


// 3. Total de Fotos
$sql_fotos = "SELECT COUNT(*) as total FROM multimedia WHERE id_usuario = $id_user AND tipo_archivo = 'FOTO'";
$total_fotos = $conn->query($sql_fotos)->fetch_assoc()['total'];

// 4. Total de Videos
$sql_videos = "SELECT COUNT(*) as total FROM multimedia WHERE id_usuario = $id_user AND tipo_archivo = 'VIDEO'";
$total_videos = $conn->query($sql_videos)->fetch_assoc()['total'];



// 5. Total de Fotos 360°
$sql_fotos_360 = "SELECT COUNT(*) as total FROM multimedia 
                  WHERE id_usuario = $id_user AND tipo_archivo = 'FOTO' AND es_360 = 1";
$total_fotos_360 = $conn->query($sql_fotos_360)->fetch_assoc()['total'];

// 6. Total de Videos 360°
$sql_videos_360 = "SELECT COUNT(*) as total FROM multimedia 
                   WHERE id_usuario = $id_user AND tipo_archivo = 'VIDEO' AND es_360 = 1";
$total_videos_360 = $conn->query($sql_videos_360)->fetch_assoc()['total'];

// 7. Total de Modelos 3D
$sql_modelos = "SELECT COUNT(*) as total FROM multimedia 
                WHERE id_usuario = $id_user AND tipo_archivo = 'MODELO_3D'";
$total_modelos = $conn->query($sql_modelos)->fetch_assoc()['total'];

// 8. Total de Marcadores
$sql_marcadores = "SELECT COUNT(*) as total FROM marcadores_ar WHERE id_usuario = $id_user";
$total_marcadores = $conn->query($sql_marcadores)->fetch_assoc()['total'];


// Cálculos para separar Normales de 360
$fotos_normales = $total_fotos - $total_fotos_360;
$videos_normales = $total_videos - $total_videos_360;
?>



<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>KunturVR | Portal Operativo</title>
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

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #023675;
            color: white;
            padding: 30px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .sidebar.collapsed {
            transform: translateX(-280px);
        }

        .logo-container {
            padding: 0 24px 30px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 0%, #b3d9ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo span {
            font-size: 0.9rem;
            display: block;
            font-weight: 400;
            opacity: 0.7;
            margin-top: 5px;
            background: none;
            -webkit-text-fill-color: rgba(255, 255, 255, 0.7);
            color: rgba(255, 255, 255, 0.7);
        }

        .user-info {
            padding: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .user-role {
            font-size: 0.85rem;
            opacity: 0.7;
        }

        /* Menú lateral */
        .menu {
            flex: 1;
            padding: 24px 0;
        }

        .menu-item {
            padding: 12px 24px;
            margin: 4px 8px;
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .menu-item i {
            width: 20px;
            font-size: 1.2rem;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .menu-item.active {
            background: white;
            color: #023675;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .menu-item.active i {
            color: #023675;
        }

        /* Submenú */
        .submenu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            margin: 0 8px 0 24px;
        }

        .submenu.show {
            max-height: 200px;
        }

        .submenu-item {
            padding: 10px 24px 10px 52px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .submenu-item i {
            font-size: 0.9rem;
            width: 16px;
        }

        .submenu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .logout-btn {
            margin: 24px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Contenido principal */
        .main-content {
            flex: 1;
            padding: 30px 40px;
            overflow-y: auto;
            transition: margin-left 0.3s ease;
        }

        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1100;
            background: #023675;
            color: white;
            border: none;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #64748b;
            font-size: 1rem;
        }

        /* Grid de cards */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid #e9eef2;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(2, 54, 117, 0.12);
            border-color: #023675;
        }

        .card-icon {
            width: 48px;
            height: 48px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .card-icon i {
            font-size: 1.8rem;
            color: #023675;
        }

        .card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #0f172a;
        }

        .card p {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
        }

/* Stats cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr); /* 4 columnas para acomodar la nueva tarjeta */
            gap: 20px;
            margin-bottom: 30px;
        }
        /* Ajuste para móvil y tablet */
        @media (max-width: 1200px) { .stats-container { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 900px) { .stats-container { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .stats-container { grid-template-columns: 1fr; } }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e9eef2;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon i {
            font-size: 1.5rem;
            color: #023675;
        }

        .stat-info h4 {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 400;
            margin-bottom: 4px;
        }

        .stat-info .number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .mobile-toggle {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 80px 20px 30px 20px;
                width: 100%;
            }

            .stats-container {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .cards-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .page-header {
                text-align: center;
            }

            .page-header h1 {
                font-size: 1.8rem;
            }

            .stat-card {
                padding: 15px;
            }

            .card {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 70px 15px 20px 15px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .page-header p {
                font-size: 0.9rem;
            }

            .stat-info .number {
                font-size: 1.2rem;
            }

            .card h3 {
                font-size: 1.1rem;
            }

            .card p {
                font-size: 0.85rem;
            }
        }

        /* Overlay para móvil */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .sidebar-overlay.show {
            display: block;
        }

        @media (max-width: 768px) {
            .sidebar-overlay.show {
                display: block;
            }
        }

        /* --- NUEVO ENCABEZADO SIMPLE DE EMPRESA (SIN RECUADROS) --- */
        .empresa-header-simple {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e7ef;
        }

        .empresa-logo-simple {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empresa-logo-simple img {
            height: 50px;
            width: auto;
            object-fit: contain;
        }

        .empresa-nombre-simple {
            font-size: 2rem;
            font-weight: 700;
            color: #023675;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }
        /* --- FIN NUEVO ENCABEZADO SIMPLE --- */
    </style>
</head>
<body>

    <?php include 'includes/navbar_cliente.php'; ?>

    <div class="main-content" id="main-content">


        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Bienvenido a tu centro de operaciones, <strong><?php echo $_SESSION['nombre']; ?></strong></p>
        </div>

       <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(2, 54, 117, 0.1); color: #023675;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h4>Subidas este mes</h4>
                    <div class="number"><?php echo $subidas_mes; ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-info">
                    <h4>Total Archivos</h4>
                    <div class="number"><?php echo $completadas; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                    <i class="fas fa-camera"></i>
                </div>
                <div class="stat-info">
                    <h4>Fotos Estándar</h4>
                    <div class="number"><?php echo $fotos_normales; ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                    <i class="fas fa-video"></i>
                </div>
                <div class="stat-info">
                    <h4>Videos Estándar</h4>
                    <div class="number"><?php echo $videos_normales; ?></div>
                </div>
            </div>

            <div class="stat-card" style="border: 1px solid #f59e0b;">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <i class="fas fa-street-view"></i>
                </div>
                <div class="stat-info">
                    <h4>Fotos 360° VR</h4>
                    <div class="number"><?php echo $total_fotos_360; ?></div>
                </div>
            </div>

            <div class="stat-card" style="border: 1px solid #f59e0b;">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <i class="fas fa-vr-cardboard"></i>
                </div>
                <div class="stat-info">
                    <h4>Videos 360° VR</h4>
                    <div class="number"><?php echo $total_videos_360; ?></div>
                </div>
            </div>
            
            
            
            <div class="stat-card" style="border: 1px solid #0284c7;">
                <div class="stat-icon" style="background: rgba(2, 132, 199, 0.1); color: #0284c7;">
                    <i class="fas fa-cube"></i>
                </div>
                <div class="stat-info">
                    <h4>Modelos 3D</h4>
                    <div class="number"><?php echo $total_modelos; ?></div>
                </div>
            </div>
            
            <!-- NUEVA TARJETA DE MARCADORES -->
            <div class="stat-card" style="border: 1px solid #8b5cf6;">
                <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                    <i class="fas fa-qrcode"></i>
                </div>
                <div class="stat-info">
                    <h4>Marcadores AR</h4>
                    <div class="number"><?php echo $total_marcadores; ?></div>
                </div>
            </div>
            
        </div>

        <div class="cards-grid">
            <a href="subir.php" class="card">
                <div class="card-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <h3>Subir Evidencia</h3>
                <p>Carga reportes, fotos y videos de inspección a la nube de forma segura.</p>
            </a>

            <a href="historial.php" class="card">
                <div class="card-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h3>Historial de Cargas</h3>
                <p>Visualiza el estado y detalle de todas tus subidas anteriores.</p>
            </a>

            <a href="galeria.php" class="card">
                <div class="card-icon">
                    <i class="fas fa-images"></i>
                </div>
                <h3>Galería</h3>
                <p>Explora todas las fotos, videos y contenido 360° organizado por ubicación.</p>
            </a>

            <a href="perfil_editar.php" class="card">
                <div class="card-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <h3>Mi Perfil</h3>
                <p>Actualiza tu información personal, contacto y credenciales.</p>
            </a>

            <!-- REPORTES OCULTADO -->
        </div>
    </div>

</body>
</html>