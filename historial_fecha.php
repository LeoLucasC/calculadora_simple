<?php
session_start();
include 'includes/db.php';

// Seguridad
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario'];

// Procesar filtro de fechas
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d', strtotime('-30 days'));
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');

// Paginación
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Consulta para contar el total de registros con filtro de fechas
$total_query = "SELECT COUNT(*) as total FROM multimedia 
                WHERE id_usuario = $id_usuario 
                AND DATE(fecha_hora) BETWEEN '$fecha_desde' AND '$fecha_hasta'";
$total_result = $conn->query($total_query);
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// Consulta para obtener los registros filtrados por fecha
$query = "SELECT m.*, u.nombre as ubicacion_nombre, g.nombre as grupo_nombre 
          FROM multimedia m
          INNER JOIN ubicaciones u ON m.id_ubicacion = u.id_ubicacion
          INNER JOIN grupos_operativos g ON m.id_grupo = g.id_grupo
          WHERE m.id_usuario = $id_usuario 
          AND DATE(m.fecha_hora) BETWEEN '$fecha_desde' AND '$fecha_hasta'
          ORDER BY m.fecha_hora DESC
          LIMIT $offset, $limit";
$resultado = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial por Fecha | KunturVR</title>
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

        /* Filtros de fecha */
        .filtros-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid #e9eef2;
        }

        .filtros-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: #0f172a;
            font-weight: 600;
        }

        .filtros-title i {
            color: #023675;
            font-size: 1.2rem;
        }

        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filtro-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filtro-group label {
            font-weight: 500;
            color: #1e293b;
            font-size: 0.9rem;
        }

        .filtro-group label i {
            color: #023675;
            margin-right: 8px;
        }

        .filtro-input {
            padding: 12px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            color: #1e293b;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        .filtro-input:focus {
            outline: none;
            border-color: #023675;
            box-shadow: 0 0 0 3px rgba(2, 54, 117, 0.1);
            background: white;
        }

        .btn-filtrar {
            padding: 12px 24px;
            background: #023675;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            height: fit-content;
        }

        .btn-filtrar:hover {
            background: #0347a3;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(2, 54, 117, 0.2);
        }

        .btn-reset {
            padding: 12px 24px;
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            height: fit-content;
        }

        .btn-reset:hover {
            background: #e2e8f0;
            color: #1e293b;
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

        /* Info fecha */
        .fecha-info {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 5px;
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

            .filtros-grid {
                grid-template-columns: 1fr;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }

            th, td {
                white-space: nowrap;
            }

            .btn-filtrar, .btn-reset {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .pagination {
                gap: 4px;
            }
            
            .pagination a, .pagination span {
                padding: 8px 12px;
                min-width: 35px;
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
                <a href="historial_fecha.php" class="submenu-item active"><i class="fas fa-calendar"></i>Por fecha</a>
                <a href="historial_pendientes.php" class="submenu-item"><i class="fas fa-clock"></i>Pendientes</a>
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
            <h1>Historial por Fecha</h1>
            <p>Consulta tus cargas filtrando por rango de fechas</p>
        </div>

        <!-- Filtros de fecha -->
        <div class="filtros-container">
            <div class="filtros-title">
                <i class="fas fa-filter"></i>
                <span>Filtros de búsqueda</span>
            </div>
            
            <form method="GET" action="historial_fecha.php" class="filtros-grid">
                <div class="filtro-group">
                    <label><i class="fas fa-calendar-alt"></i>Fecha desde</label>
                    <input type="date" name="fecha_desde" class="filtro-input" 
                           value="<?php echo htmlspecialchars($fecha_desde); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="filtro-group">
                    <label><i class="fas fa-calendar-check"></i>Fecha hasta</label>
                    <input type="date" name="fecha_hasta" class="filtro-input" 
                           value="<?php echo htmlspecialchars($fecha_hasta); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-filtrar">
                        <i class="fas fa-search"></i>
                        Filtrar
                    </button>
                    
                    <a href="historial_fecha.php" class="btn-reset">
                        <i class="fas fa-undo"></i>
                        Limpiar
                    </a>
                </div>
            </form>
        </div>

        <!-- Resultados -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list"></i>
                    <span>Resultados del <?php echo date('d/m/Y', strtotime($fecha_desde)); ?> al <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?></span>
                </div>
                <div class="result-count">
                    <i class="fas fa-file"></i>
                    <strong><?php echo $total_records; ?></strong> archivos encontrados
                </div>
            </div>

            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Ubicación</th>
                                <th>Grupo</th>
                                <th>Tipo</th>
                                <th>360°</th>
                                <th>Observaciones</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($row['fecha_hora'])); ?>
                                    <div class="fecha-info">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('H:i', strtotime($row['fecha_hora'])); ?> hrs
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
                                    <?php 
                                    $obs = htmlspecialchars($row['observaciones']);
                                    echo strlen($obs) > 40 ? substr($obs, 0, 40) . '...' : ($obs ?: '-');
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo htmlspecialchars($row['url_archivo']); ?>" target="_blank" class="btn-view">
                                        <i class="fas fa-eye"></i>
                                        Ver
                                    </a>
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
                        <a href="?page=<?php echo $page-1; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>">
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
                            <a href="?page=<?php echo $i; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&fecha_desde=<?php echo urlencode($fecha_desde); ?>&fecha_hasta=<?php echo urlencode($fecha_hasta); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No hay cargas en este período</h3>
                    <p>No se encontraron archivos entre el <?php echo date('d/m/Y', strtotime($fecha_desde)); ?> y el <?php echo date('d/m/Y', strtotime($fecha_hasta)); ?></p>
                    <p style="font-size: 0.9rem; margin-top: 10px;">
                        <i class="fas fa-info-circle"></i>
                        Prueba con un rango de fechas diferente
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

        // Validar fechas
        document.addEventListener('DOMContentLoaded', function() {
            const fechaDesde = document.querySelector('input[name="fecha_desde"]');
            const fechaHasta = document.querySelector('input[name="fecha_hasta"]');
            
            if (fechaDesde && fechaHasta) {
                fechaDesde.addEventListener('change', function() {
                    fechaHasta.min = this.value;
                });
                
                fechaHasta.addEventListener('change', function() {
                    if (this.value < fechaDesde.value) {
                        alert('La fecha hasta no puede ser menor que la fecha desde');
                        this.value = fechaDesde.value;
                    }
                });
            }

            // Mantener submenú de historial abierto
            document.getElementById('submenu-historial').classList.add('show');
        });

        // Prevenir envío de fechas inválidas
        document.querySelector('form').addEventListener('submit', function(e) {
            const fechaDesde = document.querySelector('input[name="fecha_desde"]').value;
            const fechaHasta = document.querySelector('input[name="fecha_hasta"]').value;
            
            if (fechaDesde && fechaHasta && fechaHasta < fechaDesde) {
                e.preventDefault();
                alert('La fecha hasta no puede ser menor que la fecha desde');
            }
        });
    </script>

</body>
</html>