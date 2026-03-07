<?php
session_start();
include 'includes/db.php';

// SEGURIDAD: Solo ADMIN
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: index.html");
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// 1. TOGGLE ESTADO (Ocultar/Mostrar)
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $estado = ($_GET['estado'] == 1) ? 0 : 1;
    $conn->query("UPDATE multimedia SET estado = $estado WHERE id_multimedia = $id");
    header("Location: admin_archivos.php");
    exit();
}

// 2. ELIMINAR ARCHIVO (Físico + BD)
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Primero obtenemos la ruta
    $query = $conn->query("SELECT url_archivo FROM multimedia WHERE id_multimedia = $id");
    if ($row = $query->fetch_assoc()) {
        $ruta_fisica = $row['url_archivo']; // Ej: uploads/foto.jpg
        
        // Borrar archivo físico si existe
        if (file_exists($ruta_fisica)) {
            unlink($ruta_fisica);
        }
        
        // Borrar de la BD
        if ($conn->query("DELETE FROM multimedia WHERE id_multimedia = $id")) {
            $mensaje = "Archivo eliminado correctamente";
            $tipo_mensaje = "success";
        }
    }
}

// 3. FILTROS Y CONSULTA PRINCIPAL
// Inicializamos las variables para evitar errores de "Undefined variable" en el HTML
$cliente_filter = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$tipo_filter = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

$where = "WHERE 1=1";

// Filtro por Cliente
if (!empty($cliente_filter)) {
    $cliente_id = intval($cliente_filter);
    $where .= " AND u.id_cliente = $cliente_id";
}

// Filtro por Tipo (FOTO/VIDEO)
if (!empty($tipo_filter)) {
    $tipo = $conn->real_escape_string($tipo_filter);
    $where .= " AND m.tipo_archivo = '$tipo'";
}

// Filtro por Fecha Inicio
if (!empty($fecha_inicio)) {
    $fi = $conn->real_escape_string($fecha_inicio);
    $where .= " AND DATE(m.fecha_hora) >= '$fi'";
}

// Filtro por Fecha Fin
if (!empty($fecha_fin)) {
    $ff = $conn->real_escape_string($fecha_fin);
    $where .= " AND DATE(m.fecha_hora) <= '$ff'";
}

$sql = "SELECT m.*, 
        u.nombre as usuario_nombre,
        cl.razon_social as cliente_nombre,
        op.nombre as operacion_nombre,
        ub.nombre as ubicacion_nombre,
        gp.nombre as grupo_nombre
        FROM multimedia m
        INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
        INNER JOIN clientes cl ON u.id_cliente = cl.id_cliente
        INNER JOIN ubicaciones ub ON m.id_ubicacion = ub.id_ubicacion
        INNER JOIN operaciones op ON ub.id_operacion = op.id_operacion
        INNER JOIN grupos_operativos gp ON m.id_grupo = gp.id_grupo
        $where
        ORDER BY m.fecha_hora DESC";

$resultado = $conn->query($sql);
$archivos = [];
$stats = ['FOTO' => 0, 'VIDEO' => 0, '360' => 0];
$espacio_simulado = 0;



while($row = $resultado->fetch_assoc()) {
    $archivos[] = $row;
    
    // Calcular estadísticas al vuelo
    if(isset($stats[$row['tipo_archivo']])) $stats[$row['tipo_archivo']]++;
    if($row['es_360']) $stats['360']++;
}

$total_archivos = count($archivos);
$clientes = $conn->query("SELECT id_cliente, razon_social FROM clientes WHERE estado = 1 ORDER BY razon_social");

// --- CORRECCIÓN DE VARIABLES DE ESPACIO ---
// Simulamos que cada archivo pesa 15MB (en un sistema real sumarías el peso real)
$espacio_usado = $total_archivos * 15; 

// Definimos un límite ficticio de 5000 MB (5GB)
$espacio_total = 5000; 

// Calculamos el porcentaje (evitando división por cero)
$porcentaje_uso = ($espacio_total > 0) ? round(($espacio_usado / $espacio_total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Gestión de Archivos</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #1e293b;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #023675 0%, #0345a0 100%);
            color: white;
            padding: 30px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(2, 54, 117, 0.2);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .logo-container {
            padding: 0 24px 30px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 20px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 0%, #a5d8ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo span {
            font-size: 0.85rem;
            display: block;
            font-weight: 400;
            opacity: 0.7;
            margin-top: 5px;
            background: none;
            -webkit-text-fill-color: rgba(255, 255, 255, 0.7);
            color: rgba(255, 255, 255, 0.7);
        }

        .user-info {
            padding: 0 24px 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .user-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .user-role {
            font-size: 0.85rem;
            opacity: 0.7;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .menu {
            flex: 1;
            padding: 20px 0;
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
        }

        .menu-item i {
            width: 20px;
            font-size: 1.2rem;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
        }

        .menu-item.active {
            background: white;
            color: #023675;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .menu-item.active i {
            color: #023675;
        }

        .logout-btn {
            margin: 20px 24px;
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
            transform: translateY(-2px);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px 40px;
            overflow-y: auto;
        }

        /* Header */
        .page-header {
            margin-bottom: 30px;
            background: white;
            padding: 25px 30px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid #e9eef2;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h1 i {
            color: #023675;
            font-size: 2.2rem;
        }

        .breadcrumb {
            color: #64748b;
            font-size: 0.95rem;
        }

        .breadcrumb a {
            color: #023675;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Alertas */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        .alert.success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .alert.error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #b91c1c;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e9eef2;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon i {
            font-size: 1.8rem;
            color: #023675;
        }

        .stat-info h4 {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 400;
            margin-bottom: 5px;
        }

        .stat-info .number {
            font-size: 1.6rem;
            font-weight: 700;
            color: #0f172a;
        }

        /* Barra de progreso */
        .storage-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e9eef2;
            margin-bottom: 30px;
        }

        .storage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .storage-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #0f172a;
        }

        .storage-bar {
            height: 10px;
            background: #e9eef2;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }

        .storage-progress {
            height: 100%;
            background: linear-gradient(90deg, #023675, #00d4ff);
            border-radius: 10px;
            width: <?php echo $porcentaje_uso; ?>%;
            transition: width 0.3s ease;
        }

        .storage-stats {
            display: flex;
            justify-content: space-between;
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        /* Filtros */
        .filters-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e9eef2;
            margin-bottom: 25px;
        }

        .filters-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: #0f172a;
            font-weight: 600;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #64748b;
        }

        .filter-select, .filter-input {
            padding: 10px 12px;
            border: 1px solid #e9eef2;
            border-radius: 8px;
            font-size: 0.95rem;
            color: #1e293b;
            background: white;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #023675;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .btn-filter {
            background: #023675;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-filter:hover {
            background: #0345a0;
        }

        .btn-reset {
            background: white;
            color: #64748b;
            border: 1px solid #e9eef2;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .btn-reset:hover {
            background: #f8fafc;
        }

        /* Tabla de archivos */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid #e9eef2;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px 10px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9eef2;
        }

        td {
            padding: 15px 10px;
            border-bottom: 1px solid #e9eef2;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .archivo-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .archivo-thumbnail {
            width: 50px;
            height: 50px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #023675;
            position: relative;
        }

        .archivo-thumbnail.video {
            background: rgba(0, 212, 255, 0.1);
            color: #00d4ff;
        }

        .archivo-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .badge-360 {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #023675;
            color: white;
            font-size: 0.65rem;
            padding: 2px 4px;
            border-radius: 4px;
        }

        .archivo-detalle h4 {
            font-weight: 600;
            margin-bottom: 3px;
            color: #0f172a;
            font-size: 0.95rem;
        }

        .archivo-detalle small {
            color: #64748b;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge.foto {
            background: #e0f2fe;
            color: #0369a1;
        }

        .badge.video {
            background: #f3e8ff;
            color: #6b21a8;
        }

        .badge.ubicacion {
            background: #fff7ed;
            color: #9a3412;
        }

        .badge.usuario {
            background: #d1fae5;
            color: #065f46;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid #e9eef2;
            background: white;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-icon:hover {
            background: #f8fafc;
            transform: translateY(-2px);
        }

        .btn-icon.view:hover {
            border-color: #023675;
            color: #023675;
        }

        .btn-icon.download:hover {
            border-color: #10b981;
            color: #10b981;
        }

        .btn-icon.toggle:hover {
            border-color: #f59e0b;
            color: #f59e0b;
        }

        .btn-icon.delete:hover {
            border-color: #ef4444;
            color: #ef4444;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
    background: white;
    border-radius: 20px;
    width: 95%; /* Un poco más de margen en los lados */
    max-width: 700px; /* Reducimos un poco el ancho para que se vea más centrado */
    max-height: 90vh; /* ¡CLAVE! Máximo 90% de la altura de la pantalla */
    padding: 25px;
    animation: modalSlide 0.3s ease;
    display: flex;
    flex-direction: column;
    overflow-y: auto; /* Si el contenido es largo, permite scroll interno */
}

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            color: #0f172a;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
        }

        .preview-container {
            text-align: center;
            margin: 20px 0;
        }

        .preview-container img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 12px;
            border: 1px solid #e9eef2;
        }

        .preview-container video {
            max-width: 100%;
            max-height: 400px;
            border-radius: 12px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }

        .info-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
        }

        .info-item .label {
            font-size: 0.8rem;
            color: #64748b;
            margin-bottom: 5px;
        }

        .info-item .value {
            font-weight: 600;
            color: #0f172a;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar_admin.php'; ?>

    <!-- Contenido principal -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-file-video"></i>
                Gestión de Archivos Multimedia
            </h1>
            <div class="breadcrumb">
                <a href="dashboard_admin.php">Dashboard</a> / Archivos
            </div>
        </div>

        <!-- Alertas -->
        <?php if ($mensaje): ?>
        <div class="alert <?php echo $tipo_mensaje; ?>">
            <i class="fas <?php echo $tipo_mensaje == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-video"></i>
                </div>
                <div class="stat-info">
                    <h4>Total Archivos</h4>
                    <div class="number"><?php echo $total_archivos; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <div class="stat-info">
                    <h4>Fotos</h4>
                    <div class="number"><?php echo $stats['por_tipo']['FOTO'] ?? 0; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-video"></i>
                </div>
                <div class="stat-info">
                    <h4>Videos</h4>
                    <div class="number"><?php echo $stats['por_tipo']['VIDEO'] ?? 0; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-vr-cardboard"></i>
                </div>
                <div class="stat-info">
                    <h4>360°</h4>
                    <div class="number"><?php echo $stats['360']['total_360'] ?? 0; ?></div>
                </div>
            </div>
        </div>

        <!-- Uso de almacenamiento -->
        <div class="storage-card">
            <div class="storage-header">
                <h3><i class="fas fa-database" style="margin-right: 8px; color: #023675;"></i> Uso de Almacenamiento</h3>
                <span><?php echo $espacio_usado; ?> MB / <?php echo $espacio_total; ?> MB</span>
            </div>
            <div class="storage-bar">
                <div class="storage-progress"></div>
            </div>
            <div class="storage-stats">
                <span><i class="fas fa-circle" style="color: #023675;"></i> Usado: <?php echo $espacio_usado; ?> MB</span>
                <span><i class="fas fa-circle" style="color: #e9eef2;"></i> Libre: <?php echo $espacio_total - $espacio_usado; ?> MB</span>
                <span><?php echo $porcentaje_uso; ?>% utilizado</span>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-section">
            <div class="filters-title">
                <i class="fas fa-filter"></i>
                <span>Filtros de búsqueda</span>
            </div>
            
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Cliente</label>
                        <select name="cliente" class="filter-select">
                            <option value="">Todos los clientes</option>
                            <?php foreach ($clientes as $cliente): ?>
                            <option value="<?php echo $cliente['id_cliente']; ?>" <?php echo $cliente_filter == $cliente['id_cliente'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cliente['razon_social']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Tipo de archivo</label>
                        <select name="tipo" class="filter-select">
                            <option value="">Todos</option>
                            <option value="FOTO" <?php echo $tipo_filter == 'FOTO' ? 'selected' : ''; ?>>Fotos</option>
                            <option value="VIDEO" <?php echo $tipo_filter == 'VIDEO' ? 'selected' : ''; ?>>Videos</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Fecha desde</label>
                        <input type="date" name="fecha_inicio" class="filter-input" value="<?php echo $fecha_inicio; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>Fecha hasta</label>
                        <input type="date" name="fecha_fin" class="filter-input" value="<?php echo $fecha_fin; ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                        <a href="admin_archivos.php" class="btn-reset">
                            <i class="fas fa-undo"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de archivos -->
        <div class="table-container">
            <table id="tablaArchivos">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Ubicación</th>
                        <th>Usuario</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($archivos as $archivo): ?>
                    <tr>
                        <td>
                            <div class="archivo-info">
                                <div class="archivo-thumbnail <?php echo $archivo['tipo_archivo'] == 'VIDEO' ? 'video' : ''; ?>">
                                    <?php if ($archivo['tipo_archivo'] == 'FOTO'): ?>
                                        <i class="fas fa-camera"></i>
                                    <?php else: ?>
                                        <i class="fas fa-video"></i>
                                    <?php endif; ?>
                                    
                                    <?php if ($archivo['es_360']): ?>
                                    <span class="badge-360">360°</span>
                                    <?php endif; ?>
                                </div>
                                <div class="archivo-detalle">
                                    <h4><?php echo basename(htmlspecialchars($archivo['url_archivo'])); ?></h4>
                                    <small>
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($archivo['cliente_nombre']); ?> |
                                        <i class="fas fa-hard-hat"></i> <?php echo htmlspecialchars($archivo['operacion_nombre']); ?>
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($archivo['ubicacion_nombre']); ?></strong>
                                <br>
                                <small><?php echo htmlspecialchars($archivo['grupo_nombre']); ?></small>
                            </div>
                        </td>
                        <td>
                            <span class="badge usuario">
                                <i class="fas fa-user"></i>
                                <?php echo htmlspecialchars($archivo['usuario_nombre']); ?>
                            </span>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo date('d/m/Y', strtotime($archivo['fecha_hora'])); ?></strong>
                                <br>
                                <small><?php echo date('H:i', strtotime($archivo['fecha_hora'])); ?></small>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?php echo $archivo['tipo_archivo'] == 'FOTO' ? 'foto' : 'video'; ?>">
                                <i class="fas <?php echo $archivo['tipo_archivo'] == 'FOTO' ? 'fa-camera' : 'fa-video'; ?>"></i>
                                <?php echo $archivo['tipo_archivo']; ?>
                                <?php if ($archivo['es_360']): ?> 360° <?php endif; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon view" title="Ver archivo"
                                    onclick="verArchivo(
                                        '<?php echo $archivo['url_archivo']; ?>', 
                                        '<?php echo $archivo['tipo_archivo']; ?>',
                                        '<?php echo $archivo['es_360']; ?>',
                                        '<?php echo htmlspecialchars($archivo['cliente_nombre']); ?>',
                                        '<?php echo htmlspecialchars($archivo['operacion_nombre']); ?>',
                                        '<?php echo htmlspecialchars($archivo['ubicacion_nombre']); ?>',
                                        '<?php echo date('d/m/Y H:i', strtotime($archivo['fecha_hora'])); ?>'
                                    )">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="<?php echo htmlspecialchars($archivo['url_archivo']); ?>" download class="btn-icon download" title="Descargar">
                                    <i class="fas fa-download"></i>
                                </a>
                                <a href="?toggle=1&id=<?php echo $archivo['id_multimedia']; ?>&estado=<?php echo $archivo['estado'] ?? 1; ?>" 
                                   class="btn-icon toggle" 
                                   title="<?php echo ($archivo['estado'] ?? 1) == 1 ? 'Ocultar' : 'Mostrar'; ?>"
                                   onclick="return confirm('¿<?php echo ($archivo['estado'] ?? 1) == 1 ? 'Ocultar' : 'Mostrar'; ?> este archivo?')">
                                    <i class="fas <?php echo ($archivo['estado'] ?? 1) == 1 ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                                </a>
                                <a href="?delete&id=<?php echo $archivo['id_multimedia']; ?>" 
                                   class="btn-icon delete" 
                                   title="Eliminar"
                                   onclick="return confirm('¿Eliminar permanentemente este archivo? Esta acción no se puede deshacer.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($archivos)): ?>
            <div style="text-align: center; padding: 50px; color: #64748b;">
                <i class="fas fa-file-video" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i>
                <p>No hay archivos que coincidan con los filtros</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de vista previa -->
    <div class="modal" id="modalVistaPrevia">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Vista Previa del Archivo</h2>
                <button class="close-modal" onclick="cerrarVistaPrevia()">&times;</button>
            </div>
            
            <div id="previewContent" class="preview-container">
                <!-- Contenido cargado vía AJAX -->
            </div>
            
            <div id="previewInfo" class="info-grid">
                <!-- Información cargada vía AJAX -->
            </div>
        </div>
    </div>

   <script>
    // Función para abrir el modal y mostrar el contenido REAL
    function verArchivo(url, tipo, es360, cliente, operacion, ubicacion, fecha) {
        const contentDiv = document.getElementById('previewContent');
        const infoDiv = document.getElementById('previewInfo');
        
        // 1. Generar el contenido visual (Imagen o Video)
        let htmlContent = '';
        if (tipo === 'VIDEO') {
            htmlContent = `
                <video controls style="width: 100%; max-height: 450px; border-radius: 8px; background: #000;">
                    <source src="${url}" type="video/mp4">
                    Tu navegador no soporta videos.
                </video>`;
        } else {
            // Es FOTO
            htmlContent = `<img src="${url}" style="width: 100%; max-height: 450px; object-fit: contain; border-radius: 8px;">`;
        }

        // Si es 360, agregar un aviso
        if (es360 == 1) {
            htmlContent += `<div style="margin-top:10px; color:#00d4ff; font-weight:bold; text-align:center;">🕶️ Contenido 360° VR Compatible</div>`;
        }

        contentDiv.innerHTML = htmlContent;

        // 2. Generar la información lateral
        infoDiv.innerHTML = `
            <div class="info-item">
                <div class="label">Cliente</div>
                <div class="value">${cliente}</div>
            </div>
            <div class="info-item">
                <div class="label">Operación</div>
                <div class="value">${operacion}</div>
            </div>
            <div class="info-item">
                <div class="label">Ubicación / Zona</div>
                <div class="value">${ubicacion}</div>
            </div>
            <div class="info-item">
                <div class="label">Fecha de Carga</div>
                <div class="value">${fecha}</div>
            </div>
        `;

        // 3. Mostrar el modal
        document.getElementById('modalVistaPrevia').classList.add('active');
    }

    function cerrarVistaPrevia() {
        // Pausar video si se cierra el modal
        const video = document.querySelector('#previewContent video');
        if (video) video.pause();
        
        document.getElementById('modalVistaPrevia').classList.remove('active');
    }

    // Auto-ocultar alertas a los 3 segundos
    document.addEventListener('DOMContentLoaded', function() {
        var alerta = document.querySelector('.alert');
        if (alerta) {
            setTimeout(function() {
                alerta.style.transition = 'opacity 0.5s ease';
                alerta.style.opacity = '0';
                setTimeout(() => alerta.remove(), 500);
            }, 3000);
        }
    });

    // Cerrar modal con clic fuera
    window.onclick = function(event) {
        let modal = document.getElementById('modalVistaPrevia');
        if (event.target == modal) {
            cerrarVistaPrevia();
        }
    }
</script>

</body>
</html>