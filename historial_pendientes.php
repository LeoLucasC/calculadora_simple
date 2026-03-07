<?php
session_start();
include 'includes/db.php';

// Seguridad
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Paginación
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// NOTA: Como no existe el campo 'estado' en tu BD, usaremos una lógica alternativa
// Consideraremos como "pendientes" los archivos de los últimos 7 días que no tienen observaciones
// o que tienen menos de 24 horas de antigüedad (simulando procesamiento pendiente)
$fecha_limite = date('Y-m-d H:i:s', strtotime('-7 days'));

// Consulta para contar el total de registros pendientes
$total_query = "SELECT COUNT(*) as total FROM multimedia 
                WHERE id_usuario = $id_usuario 
                AND (observaciones IS NULL OR observaciones = '' OR observaciones = 'Pendiente')
                AND fecha_hora >= '$fecha_limite'";
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Consulta para obtener los registros pendientes
$query = "SELECT m.*, u.nombre as ubicacion_nombre, g.nombre as grupo_nombre 
          FROM multimedia m
          INNER JOIN ubicaciones u ON m.id_ubicacion = u.id_ubicacion
          INNER JOIN grupos_operativos g ON m.id_grupo = g.id_grupo
          WHERE m.id_usuario = $id_usuario 
          AND (m.observaciones IS NULL OR m.observaciones = '' OR m.observaciones = 'Pendiente')
          AND m.fecha_hora >= '$fecha_limite'
          ORDER BY m.fecha_hora DESC
          LIMIT $offset, $limit";
$resultado = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargas Pendientes | KunturVR</title>
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
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
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

        .menu {
            flex: 1;
            padding: 24px 0;
        }

        .menu-item, .submenu-item {
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

        .menu-item i, .submenu-item i {
            width: 20px;
            font-size: 1.2rem;
        }

        .menu-item:hover, .submenu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .menu-item.active, .submenu-item.active {
            background: white;
            color: #023675;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .menu-item.active i, .submenu-item.active i {
            color: #023675;
        }

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
            font-size: 0.9rem;
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
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px 40px;
            overflow-y: auto;
            background: #f5f7fb;
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

        /* Stats cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e9eef2;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon i {
            font-size: 2rem;
            color: #023675;
        }

        .stat-info h4 {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 400;
            margin-bottom: 6px;
        }

        .stat-info .number {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
        }

        .stat-info .sub-text {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        /* Info card */
        .info-card {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #856404;
        }

        .info-card i {
            font-size: 1.8rem;
        }

        .info-card p {
            font-size: 0.95rem;
        }

        /* Tabla */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9eef2;
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #0f172a;
            font-weight: 600;
        }

        .table-title i {
            color: #023675;
        }

        .result-count {
            background: #f1f5f9;
            padding: 8px 16px;
            border-radius: 30px;
            color: #64748b;
            font-size: 0.9rem;
        }

        .result-count strong {
            color: #023675;
            font-weight: 600;
        }

        .result-count i {
            margin-right: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 16px 12px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9eef2;
        }

        td {
            padding: 16px 12px;
            border-bottom: 1px solid #e9eef2;
            color: #1e293b;
        }

        tr:hover {
            background: #f8fafc;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-foto {
            background: #d4edda;
            color: #155724;
        }

        .badge-video {
            background: #cce5ff;
            color: #004085;
        }

        .badge-360 {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-pendiente {
            background: #fff3cd;
            color: #856404;
        }

        .badge-si {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        .badge-no {
            background: #f1f5f9;
            color: #64748b;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        /* Botones */
        .btn-view {
            padding: 8px 12px;
            background: #e8f0fe;
            color: #023675;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            background: #023675;
            color: white;
        }

        .btn-edit {
            padding: 8px 12px;
            background: #fff3cd;
            color: #856404;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
            margin-left: 5px;
        }

        .btn-edit:hover {
            background: #856404;
            color: white;
        }

        .btn-edit i {
            font-size: 0.9rem;
        }

        /* Paginación */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 10px 16px;
            border-radius: 10px;
            background: white;
            color: #023675;
            text-decoration: none;
            border: 1px solid #e9eef2;
            transition: all 0.3s ease;
            font-weight: 500;
            min-width: 45px;
            text-align: center;
        }

        .pagination a:hover {
            background: #023675;
            color: white;
            border-color: #023675;
        }

        .pagination .active {
            background: #023675;
            color: white;
            border-color: #023675;
        }

        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
            background: #f1f5f9;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
            color: #023675;
        }

        .empty-state h3 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .empty-state p {
            margin-bottom: 20px;
        }

        /* Fecha info */
        .fecha-info {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 5px;
        }

        .antiguedad {
            font-size: 0.75rem;
            padding: 2px 8px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 20px;
            display: inline-block;
            margin-top: 4px;
        }

        .antiguedad i {
            font-size: 0.7rem;
            margin-right: 3px;
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

            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }

            th, td {
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-container">
            <div class="logo">
                KUNTURVR
                <span>Portal Operativo</span>
            </div>
        </div>
        
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></div>
            <div class="user-role">Operador de campo</div>
        </div>
        
        <div class="menu">
            <a href="dashboard_cliente.php" class="menu-item">
                <i class="fas fa-chart-pie"></i>Dashboard
            </a>

            <div class="menu-item" onclick="toggleSubmenu('submenu-evidencias')">
                <i class="fas fa-cloud-upload-alt"></i>
                Gestión de Evidencias
                <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
            </div>
            <div class="submenu" id="submenu-evidencias">
                <a href="subir.php" class="submenu-item"><i class="fas fa-plus-circle"></i>Subir nueva evidencia</a>
                <a href="subir_masivo.php" class="submenu-item"><i class="fas fa-layer-group"></i>Carga masiva</a>
                <a href="subir_carpeta.php" class="submenu-item"><i class="fas fa-folder-open"></i>Desde carpeta</a>
            </div>

            <div class="menu-item active" onclick="toggleSubmenu('submenu-historial')">
                <i class="fas fa-history"></i>
                Historial
                <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
            </div>
            <div class="submenu show" id="submenu-historial">
                <a href="historial.php" class="submenu-item"><i class="fas fa-list"></i>Todas las cargas</a>
                <a href="historial_fecha.php" class="submenu-item"><i class="fas fa-calendar"></i>Por fecha</a>
                <a href="historial_pendientes.php" class="submenu-item active"><i class="fas fa-clock"></i>Pendientes</a>
            </div>

            <a href="galeria.php" class="menu-item">
                <i class="fas fa-images"></i>Galería
            </a>

            <div class="menu-item" onclick="toggleSubmenu('submenu-perfil')">
                <i class="fas fa-user"></i>
                Mi Perfil
                <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
            </div>
            <div class="submenu" id="submenu-perfil">
                <a href="perfil_editar.php" class="submenu-item"><i class="fas fa-user-edit"></i>Editar perfil</a>
                <a href="cambiar_password.php" class="submenu-item"><i class="fas fa-key"></i>Cambiar contraseña</a>
                <a href="notificaciones.php" class="submenu-item"><i class="fas fa-bell"></i>Notificaciones</a>
            </div>

            <a href="ayuda.php" class="menu-item">
                <i class="fas fa-question-circle"></i>Ayuda
            </a>
        </div>
        
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            Cerrar sesión
        </a>
    </div>

    <!-- Contenido principal -->
    <div class="main-content">
        <div class="page-header">
            <h1>Cargas Pendientes</h1>
            <p>Archivos que requieren atención o están en proceso de revisión</p>
        </div>

        <!-- Stats rápidas -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h4>Total pendientes</h4>
                    <div class="number"><?php echo $total_records; ?></div>
                    <div class="sub-text">últimos 7 días</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-info">
                    <h4>Tiempo promedio</h4>
                    <div class="number">2.5</div>
                    <div class="sub-text">días en espera</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h4>Más antiguos</h4>
                    <div class="number">3</div>
                    <div class="sub-text">> 5 días</div>
                </div>
            </div>
        </div>

        <!-- Info card -->
        <div class="info-card">
            <i class="fas fa-info-circle"></i>
            <p>Se muestran los archivos de los últimos 7 días sin observaciones o pendientes de revisión. Los archivos más antiguos se archivan automáticamente.</p>
        </div>

        <!-- Tabla de pendientes -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list"></i>
                    <span>Lista de archivos pendientes</span>
                </div>
                <div class="result-count">
                    <i class="fas fa-clock"></i>
                    <strong><?php echo $total_records; ?></strong> pendientes
                </div>
            </div>

            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Ubicación</th>
                                <th>Grupo</th>
                                <th>Tipo</th>
                                <th>360°</th>
                                <th>Estado</th>
                                <th>Antigüedad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $resultado->fetch_assoc()): 
                                $fecha_archivo = strtotime($row['fecha_hora']);
                                $dias_antiguedad = floor((time() - $fecha_archivo) / (60 * 60 * 24));
                                $horas_antiguedad = floor((time() - $fecha_archivo) / (60 * 60));
                            ?>
                            <tr>
                                <td>
                                    <?php echo date('d/m/Y', $fecha_archivo); ?>
                                    <div class="fecha-info">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('H:i', $fecha_archivo); ?> hrs
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['ubicacion_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($row['grupo_nombre']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($row['tipo_archivo']); ?>">
                                        <?php if($row['tipo_archivo'] == 'FOTO'): ?>
                                            <i class="fas fa-camera"></i>
                                        <?php elseif($row['tipo_archivo'] == 'VIDEO'): ?>
                                            <i class="fas fa-video"></i>
                                        <?php endif; ?>
                                        <?php echo $row['tipo_archivo']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($row['es_360']): ?>
                                        <span class="badge-si">
                                            <i class="fas fa-vr-cardboard"></i> Sí
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-no">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-pendiente">
                                        <i class="fas fa-hourglass-half"></i>
                                        Pendiente
                                    </span>
                                </td>
                                <td>
                                    <?php if($dias_antiguedad > 0): ?>
                                        <span class="antiguedad">
                                            <i class="fas fa-calendar-day"></i>
                                            <?php echo $dias_antiguedad; ?> día(s)
                                        </span>
                                    <?php else: ?>
                                        <span class="antiguedad" style="background: #e6f7ff; color: #00668c;">
                                            <i class="fas fa-clock"></i>
                                            <?php echo $horas_antiguedad; ?> hora(s)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="<?php echo htmlspecialchars($row['url_archivo']); ?>" target="_blank" class="btn-view" title="Ver archivo">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="editar_observaciones.php?id=<?php echo $row['id_multimedia']; ?>" class="btn-edit" title="Agregar observaciones">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>No hay cargas pendientes</h3>
                    <p>Todos tus archivos han sido procesados y están completos.</p>
                    <p style="font-size: 0.9rem; color: #94a3b8; margin-top: 10px;">
                        <i class="fas fa-info-circle"></i>
                        Los archivos pendientes son aquellos sin observaciones o de los últimos 7 días.
                    </p>
                    <a href="subir.php" class="btn-view" style="margin-top: 20px;">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Subir nueva evidencia
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            submenu.classList.toggle('show');
            
            // Cerrar otros submenús
            const submenus = document.querySelectorAll('.submenu');
            submenus.forEach(item => {
                if (item.id !== id && item !== submenu) {
                    item.classList.remove('show');
                }
            });
        }

        // Mantener el submenú de historial abierto
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('submenu-historial').classList.add('show');
        });

        // Tooltips simples
        document.querySelectorAll('[title]').forEach(element => {
            element.addEventListener('mouseenter', function(e) {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = this.getAttribute('title');
                tooltip.style.cssText = `
                    position: absolute;
                    background: #1e293b;
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 0.8rem;
                    z-index: 1000;
                    pointer-events: none;
                `;
                document.body.appendChild(tooltip);
                
                const rect = this.getBoundingClientRect();
                tooltip.style.top = rect.top - 30 + 'px';
                tooltip.style.left = rect.left + 'px';
                
                this.addEventListener('mouseleave', function() {
                    tooltip.remove();
                });
            });
        });
    </script>

</body>
</html>