<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: index.html"); exit(); }
if ($_SESSION['id_rol'] == 1) { header("Location: dashboard_admin.php"); exit(); }

$id_user = $_SESSION['id_usuario'];

// Obtener parámetros
$ubicacion_id = isset($_GET['ubicacion']) ? (int)$_GET['ubicacion'] : 0;
$tipo_filtro = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';

// Si hay ubicación seleccionada, mostrar grupos
if ($ubicacion_id > 0) {
    // Obtener información de la ubicación
    $sql_ubicacion = "SELECT id_ubicacion, nombre, descripcion FROM ubicaciones WHERE id_ubicacion = $ubicacion_id";
    $ubicacion = $conn->query($sql_ubicacion)->fetch_assoc();
    
    
    // Obtener grupos de esta ubicación con sus archivos (FILTRADO POR PERMISOS)
    $sql_grupos = "SELECT 
                    g.id_grupo, g.nombre as grupo_nombre, g.descripcion as grupo_descripcion, tg.descripcion as tipo_grupo,
                    COUNT(m.id_multimedia) as total_archivos,
                    SUM(CASE WHEN m.tipo_archivo = 'FOTO' AND m.es_360 = 0 THEN 1 ELSE 0 END) as total_fotos,
                    SUM(CASE WHEN m.tipo_archivo = 'VIDEO' AND m.es_360 = 0 THEN 1 ELSE 0 END) as total_videos,
                    SUM(CASE WHEN m.tipo_archivo = 'FOTO' AND m.es_360 = 1 THEN 1 ELSE 0 END) as fotos_360,
                    SUM(CASE WHEN m.tipo_archivo = 'VIDEO' AND m.es_360 = 1 THEN 1 ELSE 0 END) as videos_360,
                    SUM(CASE WHEN m.tipo_archivo = 'MODELO_3D' THEN 1 ELSE 0 END) as modelos_3d,
                    (SELECT url_archivo FROM multimedia WHERE id_grupo = g.id_grupo ORDER BY fecha_creacion DESC LIMIT 1) as portada
                FROM grupos_operativos g
                INNER JOIN tipos_grupo tg ON g.id_tipo_grupo = tg.id_tipo_grupo
                INNER JOIN permisos_usuarios pu ON g.id_grupo = pu.id_grupo AND pu.id_usuario = $id_user
                LEFT JOIN multimedia m ON g.id_grupo = m.id_grupo
                WHERE g.id_ubicacion = $ubicacion_id AND g.estado = 1";
    
    // Aplicar filtro por tipo
    if ($tipo_filtro != 'todos') {
        switch($tipo_filtro) {
            case 'fotos':
                $sql_grupos .= " AND m.tipo_archivo = 'FOTO' AND m.es_360 = 0";
                break;
            case 'videos':
                $sql_grupos .= " AND m.tipo_archivo = 'VIDEO' AND m.es_360 = 0";
                break;
            case '360':
                $sql_grupos .= " AND m.es_360 = 1";
                break;
                
            case 'modelos3d':
                $sql_grupos .= " AND m.tipo_archivo = 'MODELO_3D'"; 
                // NOTA: en el segundo switch asegúrate de poner $sql_base .= en lugar de $sql_grupos .=
                break;
        }
    }
    
    $sql_grupos .= " GROUP BY g.id_grupo HAVING COUNT(m.id_multimedia) > 0";
    $grupos = $conn->query($sql_grupos)->fetch_all(MYSQLI_ASSOC);
} else {
    // Vista principal: mostrar ubicaciones
   // Vista principal: mostrar ubicaciones (FILTRADO POR PERMISOS)
    $sql_base = "SELECT 
                    u.id_ubicacion, u.nombre as ubicacion_nombre, u.descripcion,
                    (SELECT url_archivo FROM multimedia WHERE id_ubicacion = u.id_ubicacion ORDER BY fecha_creacion DESC LIMIT 1) as portada,
                    COUNT(DISTINCT g.id_grupo) as total_grupos,
                    COUNT(m.id_multimedia) as total_archivos,
                    SUM(CASE WHEN m.tipo_archivo = 'FOTO' AND m.es_360 = 0 THEN 1 ELSE 0 END) as total_fotos,
                    SUM(CASE WHEN m.tipo_archivo = 'VIDEO' AND m.es_360 = 0 THEN 1 ELSE 0 END) as total_videos,
                    SUM(CASE WHEN m.tipo_archivo = 'FOTO' AND m.es_360 = 1 THEN 1 ELSE 0 END) as fotos_360,
                    SUM(CASE WHEN m.tipo_archivo = 'MODELO_3D' THEN 1 ELSE 0 END) as modelos_3d,
                    SUM(CASE WHEN m.tipo_archivo = 'VIDEO' AND m.es_360 = 1 THEN 1 ELSE 0 END) as videos_360
                FROM ubicaciones u
                INNER JOIN grupos_operativos g ON u.id_ubicacion = g.id_ubicacion
                INNER JOIN permisos_usuarios pu ON g.id_grupo = pu.id_grupo AND pu.id_usuario = $id_user
                LEFT JOIN multimedia m ON u.id_ubicacion = m.id_ubicacion
                WHERE g.estado = 1";

    // Aplicar filtro por tipo
    if ($tipo_filtro != 'todos') {
        switch($tipo_filtro) {
            case 'fotos':
                $sql_base .= " AND m.tipo_archivo = 'FOTO' AND m.es_360 = 0";
                break;
            case 'videos':
                $sql_base .= " AND m.tipo_archivo = 'VIDEO' AND m.es_360 = 0";
                break;
            case '360':
                $sql_base .= " AND m.es_360 = 1";
                break;
            case 'modelos3d':
                $sql_base .= " AND m.tipo_archivo = 'MODELO_3D'"; 
                // NOTA: en el segundo switch asegúrate de poner $sql_base .= en lugar de $sql_grupos .=
                break;
        }
    }

    $sql = $sql_base . " GROUP BY u.id_ubicacion HAVING COUNT(m.id_multimedia) > 0";
    $galerias = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Para el Modal de Editar/Crear
$select_ubicaciones = $conn->query("SELECT id_ubicacion, nombre FROM ubicaciones WHERE estado=1");
$select_grupos = $conn->query("SELECT id_grupo, nombre FROM grupos_operativos WHERE estado=1");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>KunturVR | Galería</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Librería para visor 360 -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css"/>
    <script src="https://aframe.io/releases/1.4.2/aframe.min.js"></script>
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

        /* Header de página */
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-title h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .header-title p {
            color: #64748b;
            font-size: 1rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .breadcrumb a {
            color: #023675;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .btn-crear {
            background: #023675;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-crear:hover {
            background: #0349a3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(2, 54, 117, 0.3);
        }

        .btn-volver {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-volver:hover {
            background: #e2e8f0;
        }

        /* Filtros */
        .filters-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #e9eef2;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #023675;
            box-shadow: 0 0 0 3px rgba(2, 54, 117, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn i {
            font-size: 1rem;
        }

        .filter-btn:hover {
            border-color: #023675;
            color: #023675;
        }

        .filter-btn.active {
            background: #023675;
            color: white;
            border-color: #023675;
        }

        .filter-btn.active i {
            color: white;
        }

        .view-options {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .view-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .view-btn.active {
            background: #023675;
            color: white;
            border-color: #023675;
        }

        .view-btn:hover {
            border-color: #023675;
            color: #023675;
        }

        /* Grid de galerías */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .gallery-grid.list-view {
            grid-template-columns: 1fr;
        }

        .gallery-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid #e9eef2;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .gallery-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(2, 54, 117, 0.12);
            border-color: #023675;
        }

        .gallery-image {
            width: 100%;
            height: 200px;
            background: #f1f5f9;
            position: relative;
            overflow: hidden;
        }

        .gallery-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .gallery-card:hover .gallery-image img {
            transform: scale(1.05);
        }

        .gallery-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(2, 54, 117, 0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            backdrop-filter: blur(4px);
            z-index: 2;
        }

        .type-badge {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
            z-index: 2;
        }

        .gallery-actions {
            position: absolute;
            top: 12px;
            left: 12px;
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 2;
        }

        .gallery-card:hover .gallery-actions {
            opacity: 1;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            background: white;
            border: none;
            border-radius: 8px;
            color: #023675;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .action-btn:hover {
            background: #023675;
            color: white;
            transform: scale(1.1);
        }

        .gallery-info {
            padding: 20px;
        }

        .gallery-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .gallery-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0f172a;
        }

        .gallery-subtitle {
            font-size: 0.85rem;
            color: #64748b;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
        }

        .gallery-description {
            color: #64748b;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .gallery-stats {
            display: flex;
            gap: 16px;
            padding-top: 16px;
            border-top: 1px solid #e9eef2;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #475569;
            font-size: 0.85rem;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .stat-item:hover {
            background: #f1f5f9;
            color: #023675;
        }

        .stat-item i {
            color: #023675;
            font-size: 1rem;
        }

        .stat-item.active {
            background: #023675;
            color: white;
        }

        .stat-item.active i {
            color: white;
        }

        /* Vista de lista */
        .gallery-grid.list-view .gallery-card {
            display: flex;
            height: 200px;
        }

        .gallery-grid.list-view .gallery-image {
            width: 280px;
            height: 100%;
            flex-shrink: 0;
        }

        .gallery-grid.list-view .gallery-info {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .gallery-grid.list-view .gallery-stats {
            margin-top: auto;
        }

        /* Paginación */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 40px;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #475569;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .page-btn.active {
            background: #023675;
            color: white;
            border-color: #023675;
        }

        .page-btn:hover:not(.active) {
            border-color: #023675;
            color: #023675;
        }

        .page-btn.next-prev {
            width: auto;
            padding: 0 16px;
            gap: 8px;
        }

        /* Galería de archivos */
        .archivos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .archivo-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e9eef2;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .archivo-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(2, 54, 117, 0.15);
            border-color: #023675;
        }

        .archivo-preview {
            height: 180px;
            background: #f1f5f9;
            position: relative;
            overflow: hidden;
        }

        .archivo-preview img,
        .archivo-preview video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .archivo-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .archivo-info {
            padding: 15px;
        }

        .archivo-fecha {
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 5px;
        }

        .archivo-observaciones {
            font-size: 0.85rem;
            color: #1e293b;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 24px;
            border-bottom: 1px solid #e9eef2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 1.3rem;
            color: #0f172a;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
            transition: color 0.3s;
        }

        .modal-close:hover {
            color: #023675;
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1e293b;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #023675;
            box-shadow: 0 0 0 3px rgba(2, 54, 117, 0.1);
        }

        .modal-footer {
            padding: 24px;
            border-top: 1px solid #e9eef2;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background: #023675;
            color: white;
        }

        .btn-primary:hover {
            background: #0349a3;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #1e293b;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Visor 360 */
        #panorama-viewer {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            border-radius: 16px;
            overflow: hidden;
        }

        .viewer-container {
            width: 100%;
            height: 100%;
            position: relative;
            background: #000;
            border-radius: 16px;
            overflow: hidden;
        }

        /* Botón de cierre personalizado para el visor 360 */
        .pnlm-close-button {
            position: absolute !important;
            top: 20px !important;
            right: 20px !important;
            z-index: 3001 !important;
            background: rgba(0,0,0,0.7) !important;
            color: white !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 12px 20px !important;
            cursor: pointer !important;
            font-size: 16px !important;
            font-weight: 500 !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            transition: all 0.3s ease !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3) !important;
        }

        .pnlm-close-button:hover {
            background: rgba(2, 54, 117, 0.9) !important;
            transform: scale(1.05) !important;
        }

        .pnlm-close-button i {
            font-size: 18px;
        }

        /* Mensaje sin resultados */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            border: 1px solid #e9eef2;
            display: none;
        }

        .no-results i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .no-results h3 {
            font-size: 1.2rem;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .no-results p {
            color: #64748b;
            margin-bottom: 24px;
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

        /* Responsive */
        @media (max-width: 1024px) {
            .filters-section {
                flex-direction: column;
                align-items: stretch;
            }

            .view-options {
                margin-left: 0;
                justify-content: flex-end;
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

            .sidebar-overlay.show {
                display: block;
            }

            .mobile-toggle {
                display: block;
            }

            .main-content {
                margin-left: 0;
                padding: 80px 20px 30px 20px;
                width: 100%;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .btn-crear {
                width: 100%;
                justify-content: center;
            }

            .gallery-grid {
                grid-template-columns: 1fr;
            }

            .gallery-grid.list-view .gallery-card {
                flex-direction: column;
                height: auto;
            }

            .gallery-grid.list-view .gallery-image {
                width: 100%;
                height: 200px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 70px 15px 20px 15px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar_cliente.php'; ?>

    <div class="main-content" id="main-content">
        <div class="page-header">
            <div class="header-title">
                <h1>Galería</h1>
                <p>Explora evidencias organizadas por Ubicación y Grupo Operativo</p>
                <?php if ($ubicacion_id > 0): ?>
                <div class="breadcrumb">
                    <a href="galeria.php">Ubicaciones</a>
                    <i class="fas fa-chevron-right" style="font-size: 0.8rem;"></i>
                    <span><?= htmlspecialchars($ubicacion['nombre']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div style="display: flex; gap: 10px;">
                <?php if ($ubicacion_id > 0): ?>
                <a href="galeria.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i>
                    Volver a Ubicaciones
                </a>
                <?php endif; ?>
                <a href="subir.php" class="btn-crear">
                    <i class="fas fa-plus-circle"></i>
                    Subir Evidencia
                </a>
            </div>
        </div>

        <div class="filters-section">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="<?= $ubicacion_id > 0 ? 'Buscar grupo...' : 'Buscar por zona...' ?>" onkeyup="filterGalleries()">
            </div>
            
            <div class="filter-buttons">
                <button class="filter-btn <?= $tipo_filtro == 'todos' ? 'active' : '' ?>" onclick="filterByType('todos')">
                    <i class="fas fa-th-large"></i> Todos
                </button>
                <button class="filter-btn <?= $tipo_filtro == 'fotos' ? 'active' : '' ?>" onclick="filterByType('fotos')">
                    <i class="fas fa-image"></i> Fotos
                </button>
                <button class="filter-btn <?= $tipo_filtro == 'videos' ? 'active' : '' ?>" onclick="filterByType('videos')">
                    <i class="fas fa-video"></i> Videos
                </button>
                <button class="filter-btn <?= $tipo_filtro == '360' ? 'active' : '' ?>" onclick="filterByType('360')">
                    <i class="fas fa-vr-cardboard"></i> 360°
                </button>
                <button class="filter-btn <?= $tipo_filtro == 'modelos3d' ? 'active' : '' ?>" onclick="filterByType('modelos3d')">
                    <i class="fas fa-cube"></i> Modelos 3D
                </button>
            </div>
            
            <div class="view-options">
                <button class="view-btn active" id="btnGrid" onclick="changeView('grid')" title="Vista cuadrícula">
                    <i class="fas fa-th"></i>
                </button>
                <button class="view-btn" id="btnList" onclick="changeView('list')" title="Vista lista">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>

        <?php if ($ubicacion_id > 0): ?>
            <div class="gallery-grid" id="galleryGrid">
                <?php if (empty($grupos)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; background: white; border-radius: 16px;">
                        <i class="fas fa-users" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 16px;"></i>
                        <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 8px;">No hay grupos con evidencias</h3>
                        <p style="color: #64748b;">No se encontraron grupos operativos con archivos multimedia en esta ubicación.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($grupos as $g): ?>
                    <!-- ✅ CAMBIO: onclick movido al gallery-card completo -->
                    <div class="gallery-card" data-name="<?= strtolower($g['grupo_nombre']); ?>" onclick="window.location.href='ver_grupo.php?id=<?= $g['id_grupo'] ?>&ubicacion=<?= $ubicacion_id ?>'">
                        <div class="gallery-image">
                            <?php 
                            $archivo_portada = $g['portada'];
                            $extension = strtolower(pathinfo($archivo_portada, PATHINFO_EXTENSION));
                            $es_video = in_array($extension, ['mp4', 'mov', 'webm', 'avi']);
                            $es_3d = ($extension == 'glb');
                            
                            if ($archivo_portada && file_exists($archivo_portada)): 
                                if ($es_video): ?>
                                    <video muted loop onmouseover="this.play()" onmouseout="this.pause()" preload="none" poster="img/default_mining.jpg" style="width:100%; height:100%; object-fit:cover;">
                                        <source src="<?= $archivo_portada ?>" type="video/mp4">
                                    </video>
                                    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; font-size:2rem; opacity:0.8; pointer-events:none;">
                                        <i class="fas fa-play-circle"></i>
                                    </div>
                                <?php elseif ($es_3d): ?>
                                    <div style="width:100%; height:100%; background:#f1f5f9; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#64748b;">
                                        <i class="fas fa-cube" style="font-size: 3rem; margin-bottom:10px; color:#0284c7;"></i>
                                        <span style="font-size:0.9rem; font-weight:600;">Modelo 3D</span>
                                    </div>
                                <?php else: ?>
                                    <img src="miniatura.php?ruta=<?= urlencode($archivo_portada) ?>" alt="Portada" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
                                <?php endif; ?>
                            <?php else: ?>
                                <img src="img/default_mining.jpg" alt="Sin evidencia" style="width:100%; height:100%; object-fit:cover; filter: grayscale(1);">
                            <?php endif; ?>

                            <?php if ($g['fotos_360'] > 0 || $g['videos_360'] > 0 || !empty($g['modelos_3d'])): ?>
                                <div class="type-badge" style="background: #9b59b6;">
                                    <i class="fas fa-vr-cardboard"></i> 360° / 3D
                                </div>
                            <?php endif; ?>

                            <div class="gallery-badge">
                                <i class="fas fa-images"></i> <?= $g['total_archivos'] ?> archivos
                            </div>
                        </div>
                        <!-- ✅ CAMBIO: onclick eliminado del gallery-info -->
                        <div class="gallery-info">
                            <div class="gallery-header">
                                <h3 class="gallery-title"><?= $g['grupo_nombre'] ?></h3>
                            </div>
                            <span class="gallery-subtitle"><?= $g['tipo_grupo'] ?></span>
                            <p class="gallery-description"><?= $g['grupo_descripcion'] ?: 'Grupo operativo de la zona.' ?></p>
                            <div class="gallery-stats">
                                <span class="stat-item" title="Fotos normales" onclick="event.stopPropagation();">
                                    <i class="fas fa-image"></i> <?= $g['total_fotos'] ?>
                                </span>
                                <span class="stat-item" title="Videos normales" onclick="event.stopPropagation();">
                                    <i class="fas fa-video"></i> <?= $g['total_videos'] ?>
                                </span>
                                <?php if ($g['fotos_360'] > 0): ?>
                                <span class="stat-item" title="Fotos 360°" onclick="event.stopPropagation(); open360Preview('<?= $g['portada'] ?>', 'foto')" style="color: #d97706; cursor: pointer;">
                                    <i class="fas fa-street-view"></i> <?= $g['fotos_360'] ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($g['videos_360'] > 0): ?>
                                <span class="stat-item" title="Videos 360°" onclick="event.stopPropagation(); open360Preview('<?= $g['portada'] ?>', 'video')" style="color: #d97706; cursor: pointer;">
                                    <i class="fas fa-vr-cardboard"></i> <?= $g['videos_360'] ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($g['modelos_3d']) && $g['modelos_3d'] > 0): ?>
                                <span class="stat-item" title="Modelos 3D" onclick="event.stopPropagation(); open360Preview('<?= $g['portada'] ?>', 'modelo3d')" style="color: #0284c7; cursor: pointer; background: #e0f2fe;">
                                    <i class="fas fa-cube"></i> <?= $g['modelos_3d'] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="gallery-grid" id="galleryGrid">
                <?php if (empty($galerias)): ?>
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; background: white; border-radius: 16px;">
                        <i class="fas fa-images" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 16px;"></i>
                        <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 8px;">No hay resultados</h3>
                        <p style="color: #64748b;">No se encontraron archivos con el filtro seleccionado.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($galerias as $g): ?>
                    <!-- ✅ CAMBIO: onclick movido al gallery-card completo -->
                    <div class="gallery-card" data-name="<?= strtolower($g['ubicacion_nombre']); ?>" onclick="window.location.href='galeria.php?ubicacion=<?= $g['id_ubicacion'] ?>&tipo=<?= $tipo_filtro ?>'">
                        <div class="gallery-image">
                            <?php 
                            $archivo_portada = $g['portada'];
                            $extension = strtolower(pathinfo($archivo_portada, PATHINFO_EXTENSION));
                            $es_video = in_array($extension, ['mp4', 'mov', 'webm', 'avi']);
                            $es_3d = ($extension == 'glb');
                            
                            if ($archivo_portada && file_exists($archivo_portada)): 
                                if ($es_video): ?>
                                    <video muted loop onmouseover="this.play()" onmouseout="this.pause()" preload="none" poster="img/default_mining.jpg" style="width:100%; height:100%; object-fit:cover;">
                                        <source src="<?= $archivo_portada ?>" type="video/mp4">
                                    </video>
                                    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white; font-size:2rem; opacity:0.8; pointer-events:none;">
                                        <i class="fas fa-play-circle"></i>
                                    </div>
                                <?php elseif ($es_3d): ?>
                                    <div style="width:100%; height:100%; background:#f1f5f9; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#64748b;">
                                        <i class="fas fa-cube" style="font-size: 3rem; margin-bottom:10px; color:#0284c7;"></i>
                                        <span style="font-size:0.9rem; font-weight:600;">Modelo 3D</span>
                                    </div>
                                <?php else: ?>
                                    <img src="miniatura.php?ruta=<?= urlencode($archivo_portada) ?>" alt="Portada" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
                                <?php endif; ?>
                            <?php else: ?>
                                <img src="img/default_mining.jpg" alt="Sin evidencia" style="width:100%; height:100%; object-fit:cover; filter: grayscale(1);">
                            <?php endif; ?>

                            <?php if ($g['fotos_360'] > 0 || $g['videos_360'] > 0 || !empty($g['modelos_3d'])): ?>
                                <div class="type-badge" style="background: #9b59b6;">
                                    <i class="fas fa-vr-cardboard"></i> 360° / 3D
                                </div>
                            <?php endif; ?>

                            <div class="gallery-badge">
                                <i class="fas fa-images"></i> <?= $g['total_archivos'] ?> archivos
                                <span style="margin-left: 5px;">| <i class="fas fa-users"></i> <?= $g['total_grupos'] ?> grupos</span>
                            </div>
                            
                            <div class="gallery-actions">
                                <!-- ✅ event.stopPropagation() en el botón editar para no activar la navegación -->
                                <button class="action-btn" title="Editar Información" 
                                        onclick="event.stopPropagation(); openEditModal(<?= $g['id_ubicacion'] ?>, '<?= htmlspecialchars($g['ubicacion_nombre']) ?>', '<?= htmlspecialchars($g['descripcion'] ?? '') ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        <!-- ✅ CAMBIO: onclick eliminado del gallery-info -->
                        <div class="gallery-info">
                            <div class="gallery-header">
                                <h3 class="gallery-title"><?= $g['ubicacion_nombre'] ?></h3>
                            </div>
                            <p class="gallery-description"><?= $g['descripcion'] ?: 'Área operativa de inspección.' ?></p>
                            <div class="gallery-stats">
                                <span class="stat-item" title="Fotos normales" onclick="event.stopPropagation();">
                                    <i class="fas fa-image"></i> <?= $g['total_fotos'] ?>
                                </span>
                                <span class="stat-item" title="Videos normales" onclick="event.stopPropagation();">
                                    <i class="fas fa-video"></i> <?= $g['total_videos'] ?>
                                </span>
                                <?php if ($g['fotos_360'] > 0): ?>
                                <span class="stat-item" title="Fotos 360°" onclick="event.stopPropagation(); open360Preview('<?= $g['portada'] ?>', 'foto')" style="color: #d97706; cursor: pointer;">
                                    <i class="fas fa-street-view"></i> <?= $g['fotos_360'] ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($g['videos_360'] > 0): ?>
                                <span class="stat-item" title="Videos 360°" onclick="event.stopPropagation(); open360Preview('<?= $g['portada'] ?>', 'video')" style="color: #d97706; cursor: pointer;">
                                    <i class="fas fa-vr-cardboard"></i> <?= $g['videos_360'] ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($g['modelos_3d']) && $g['modelos_3d'] > 0): ?>
                                <span class="stat-item" title="Modelos 3D" onclick="event.stopPropagation(); open360Preview('<?= $g['portada'] ?>', 'modelo3d')" style="color: #0284c7; cursor: pointer; background: #e0f2fe;">
                                    <i class="fas fa-cube"></i> <?= $g['modelos_3d'] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="no-results" id="noResults" style="display: none;">
            <i class="fas fa-search"></i>
            <h3>Sin coincidencias</h3>
            <p>No encontramos <?= $ubicacion_id > 0 ? 'grupos' : 'ubicaciones' ?> con ese nombre.</p>
        </div>
    </div>

    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Información del Área</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form action="actualizar_galeria.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_ubicacion" id="edit_id_ubicacion">
                    
                    <div class="form-group">
                        <label>Reasignar Ubicación (Opcional)</label>
                        <select name="nueva_ubicacion" class="form-control" id="edit_select_ubi">
                            <?php 
                            $select_ubicaciones->data_seek(0);
                            while($u = $select_ubicaciones->fetch_assoc()): ?>
                                <option value="<?= $u['id_ubicacion'] ?>"><?= $u['nombre'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Grupo Responsable</label>
                        <select name="nuevo_grupo" class="form-control">
                            <?php 
                            $select_grupos->data_seek(0);
                            while($gr = $select_grupos->fetch_assoc()): ?>
                                <option value="<?= $gr['id_grupo'] ?>"><?= $gr['nombre'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Descripción / Notas de la Zona</label>
                        <textarea name="descripcion" id="edit_desc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancelar</button>
                    <button type="submit" class="btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="previewModal360" style="background: rgba(0,0,0,0.95); z-index: 3000;">
        <div class="modal-content" style="max-width: 1200px; width: 95%; height: 90vh; background: transparent;">
            <div class="modal-header" style="background: rgba(255,255,255,0.1); color: white; border-bottom: 1px solid rgba(255,255,255,0.2);">
                <h2 style="color: white;">Visor Inmersivo</h2>
                <button class="modal-close" onclick="close360Preview()" style="color: white;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 20px; height: calc(100% - 80px);">
                <div id="panorama-viewer" class="viewer-container"></div>
            </div>
        </div>
    </div>


    <script src="https://cdn.babylonjs.com/babylon.js"></script>
    <script src="https://cdn.babylonjs.com/loaders/babylonjs.loaders.min.js"></script>


<script>
    let babylonEngine = null;

    function changeView(view) {
        const grid = document.getElementById('galleryGrid');
        const btnGrid = document.getElementById('btnGrid');
        const btnList = document.getElementById('btnList');
        
        if (view === 'grid') {
            grid.classList.remove('list-view');
            btnGrid.classList.add('active');
            btnList.classList.remove('active');
        } else {
            grid.classList.add('list-view');
            btnList.classList.add('active');
            btnGrid.classList.remove('active');
        }
        localStorage.setItem('galleryView', view);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const savedView = localStorage.getItem('galleryView');
        if (savedView) { changeView(savedView); }
    });

    function filterGalleries() {
        const query = document.getElementById('searchInput').value.toLowerCase();
        const cards = document.querySelectorAll('.gallery-card');
        let encontrados = 0;

        cards.forEach(card => {
            const name = card.getAttribute('data-name');
            if (name.includes(query)) {
                card.style.display = '';
                encontrados++;
            } else {
                card.style.display = 'none';
            }
        });
        document.getElementById('noResults').style.display = (encontrados === 0) ? 'block' : 'none';
    }

    function filterByType(tipo) {
        const url = new URL(window.location.href);
        url.searchParams.set('tipo', tipo);
        window.location.href = url.toString();
    }

    function openEditModal(id, nombre, desc) {
        document.getElementById('edit_id_ubicacion').value = id;
        document.getElementById('edit_desc').value = desc;
        document.getElementById('edit_select_ubi').value = id;
        document.getElementById('editModal').classList.add('show');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
    }

    function open360Preview(url, tipo) {
        const modal = document.getElementById('previewModal360');
        const container = document.getElementById('panorama-viewer');
        container.innerHTML = '';
        modal.style.display = 'flex';

        if (tipo === 'video') {
            const uniqueId = "vid-" + Math.random().toString(36).substr(2, 9);
            container.innerHTML = `
                <a-scene embedded style="width:100%; height:100%;" vr-mode-ui="enabled: true" device-orientation-permission-ui="enabled: true" renderer="colorManagement: true" loading-screen="dotsColor:white; backgroundColor:#111">
                    <a-assets timeout="30000">
                        <video id="${uniqueId}" src="${url}" preload="auto" autoplay loop playsinline webkit-playsinline crossorigin="anonymous"></video>
                    </a-assets>
                    <a-videosphere src="#${uniqueId}" rotation="0 180 0" radius="100"></a-videosphere>
                    <a-camera position="0 0 0" fov="80" rotation="0 0 0" look-controls="enabled:true; reverseMouseDrag:false; touchEnabled:true; magicWindowTrackingEnabled:true" wasd-controls="enabled:false"></a-camera>
                </a-scene>`;

        } else if (tipo === 'modelo3d') {
            container.innerHTML = `<canvas id="renderCanvas" style="width:100%; height:100%; touch-action:none; outline:none; border-radius:16px; display:block;"></canvas>`;

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

        } else {
            container.innerHTML = `
                <a-scene embedded style="width:100%; height:100%;" vr-mode-ui="enabled: true" device-orientation-permission-ui="enabled: true" renderer="colorManagement: true" loading-screen="dotsColor:white; backgroundColor:#111">
                    <a-sky src="${url}" rotation="0 -90 0"></a-sky>
                    <a-camera position="0 0 0" fov="80" rotation="0 0 0" look-controls="enabled:true; reverseMouseDrag:false; touchEnabled:true; magicWindowTrackingEnabled:true" wasd-controls="enabled:false"></a-camera>
                </a-scene>`;
        }
    }

    function close360Preview() {
        const modal = document.getElementById('previewModal360');
        const container = document.getElementById('panorama-viewer');

        if (babylonEngine) {
            babylonEngine.dispose();
            babylonEngine = null;
        }

        container.innerHTML = '';
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        const editModal = document.getElementById('editModal');
        const previewModal = document.getElementById('previewModal360');
        if (event.target === editModal) closeEditModal();
        if (event.target === previewModal) close360Preview();
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === "Escape") {
            closeEditModal();
            close360Preview();
        }
    });
</script>
</body>
</html>