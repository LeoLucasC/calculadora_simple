<?php
session_start();
// Seguridad: Solo ADMIN (Rol 1) puede entrar aquí
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel | KunturVR</title>
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

        /* Stats cards para admin */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
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
            font-size: 1.8rem;
            color: #023675;
        }

        .stat-info h4 {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 400;
            margin-bottom: 6px;
        }

        .stat-info .number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
        }

        /* Grid de administración */
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }

        .admin-card {
            background: white;
            border-radius: 20px;
            padding: 30px 24px;
            text-align: center;
            border: 1px solid #e9eef2;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
        }

        .admin-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #023675;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .admin-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 35px rgba(2, 54, 117, 0.12);
            border-color: #023675;
        }

        .admin-card:hover::before {
            transform: scaleX(1);
        }

        .card-icon {
            width: 70px;
            height: 70px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .card-icon i {
            font-size: 2.5rem;
            color: #023675;
        }

        .admin-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: #0f172a;
        }

        .admin-card p {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .card-badge {
            display: inline-block;
            margin-top: 16px;
            padding: 5px 12px;
            background: rgba(2, 54, 117, 0.08);
            color: #023675;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Sección de actividad reciente */
        .recent-section {
            margin-top: 50px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .section-title h2 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #0f172a;
        }

        .section-title i {
            color: #023675;
            font-size: 1.4rem;
        }

        .activity-list {
            background: white;
            border-radius: 16px;
            border: 1px solid #e9eef2;
            overflow: hidden;
        }

        .activity-item {
            padding: 16px 24px;
            border-bottom: 1px solid #e9eef2;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .activity-icon i {
            color: #023675;
            font-size: 1rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            margin-bottom: 4px;
            color: #1e293b;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
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
                <span>Admin Panel</span>
            </div>
        </div>

        <div class="user-info">
            <div class="user-name"><?php echo $_SESSION['nombre']; ?></div>
            <div class="user-role">Administrador</div>
        </div>

        <div class="menu">
            <!-- Dashboard principal (activo) -->
            <div class="menu-item active" onclick="window.location.href='dashboard_admin.php'">
                <i class="fas fa-chart-pie"></i>
                Dashboard
            </div>

            <!-- Opción con submenú - Usuarios -->
            <div class="menu-item" onclick="toggleSubmenu('submenu-usuarios')">
                <i class="fas fa-users-cog"></i>
                Gestión de Usuarios
                <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
            </div>
            <div class="submenu" id="submenu-usuarios">
                <div class="submenu-item" onclick="window.location.href='admin_usuarios.php'">
                    <i class="fas fa-list"></i>
                    Lista de usuarios
                </div>
                <div class="submenu-item" onclick="alert('Crear nuevo usuario')">
                    <i class="fas fa-user-plus"></i>
                    Nuevo usuario
                </div>
                <div class="submenu-item" onclick="alert('Gestionar roles')">
                    <i class="fas fa-user-tag"></i>
                    Roles y permisos
                </div>
            </div>

            <!-- Opción con submenú - Clientes -->
            <div class="menu-item" onclick="toggleSubmenu('submenu-clientes')">
                <i class="fas fa-building"></i>
                Clientes
                <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
            </div>
            <div class="submenu" id="submenu-clientes">
                <div class="submenu-item" onclick="alert('Ver todos los clientes')">
                    <i class="fas fa-building"></i>
                    Empresas mineras
                </div>
                <div class="submenu-item" onclick="alert('Nuevo cliente')">
                    <i class="fas fa-plus-circle"></i>
                    Registrar cliente
                </div>
            </div>

            <!-- Opción con submenú - Operaciones -->
            <div class="menu-item" onclick="toggleSubmenu('submenu-operaciones')">
                <i class="fas fa-hard-hat"></i>
                Operaciones
                <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
            </div>
            <div class="submenu" id="submenu-operaciones">
                <div class="submenu-item" onclick="alert('Ver ubicaciones')">
                    <i class="fas fa-map-marker-alt"></i>
                    Zonas de explotación
                </div>
                <div class="submenu-item" onclick="alert('Ver grupos')">
                    <i class="fas fa-users"></i>
                    Grupos operativos
                </div>
            </div>

            <!-- Opción con submenú - Archivos -->
            <div class="menu-item" onclick="toggleSubmenu('submenu-archivos')">
                <i class="fas fa-file-video"></i>
                Archivos
                <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
            </div>
            <div class="submenu" id="submenu-archivos">
                <div class="submenu-item" onclick="alert('Auditoría de archivos')">
                    <i class="fas fa-clipboard-list"></i>
                    Auditoría multimedia
                </div>
                <div class="submenu-item" onclick="alert('Reportes de carga')">
                    <i class="fas fa-chart-bar"></i>
                    Estadísticas de subida
                </div>
            </div>

            <!-- Opción sin submenú -->
            <div class="menu-item" onclick="alert('Configuración del sistema')">
                <i class="fas fa-cog"></i>
                Configuración
            </div>
        </div>

        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            Cerrar sesión
        </a>
    </div>

    <!-- Contenido principal -->
    <div class="main-content">
        <div class="page-header">
            <h1>Centro de Mando (Admin)</h1>
            <p>Bienvenido al panel de administración de KunturVR</p>
        </div>

        <!-- Stats rápidas -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h4>Usuarios activos</h4>
                    <div class="number">24</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-info">
                    <h4>Clientes</h4>
                    <div class="number">12</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hard-hat"></i>
                </div>
                <div class="stat-info">
                    <h4>Operaciones activas</h4>
                    <div class="number">8</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-file-video"></i>
                </div>
                <div class="stat-info">
                    <h4>Archivos este mes</h4>
                    <div class="number">156</div>
                </div>
            </div>
        </div>

        <!-- Grid de administración -->
        <div class="admin-grid">
            <a href="admin_usuarios.php" class="admin-card">
                <div class="card-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h3>Usuarios</h3>
                <p>Gestiona operadores, asignaciones y permisos del sistema.</p>
                <span class="card-badge">Ver, editar, eliminar</span>
            </a>

            <a href="#" class="admin-card" onclick="alert('Gestión de clientes - Próximamente')">
                <div class="card-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h3>Clientes</h3>
                <p>Administra empresas mineras, contratos y puntos de contacto.</p>
                <span class="card-badge">En desarrollo</span>
            </a>

            <a href="#" class="admin-card" onclick="alert('Gestión de operaciones - Próximamente')">
                <div class="card-icon">
                    <i class="fas fa-hard-hat"></i>
                </div>
                <h3>Operaciones</h3>
                <p>Configura zonas de explotación, ubicaciones y grupos operativos.</p>
                <span class="card-badge">En desarrollo</span>
            </a>

            <a href="#" class="admin-card" onclick="alert('Auditoría de archivos - Próximamente')">
                <div class="card-icon">
                    <i class="fas fa-file-video"></i>
                </div>
                <h3>Archivos</h3>
                <p>Audita multimedia subida, reportes y estadísticas de uso.</p>
                <span class="card-badge">En desarrollo</span>
            </a>

            <a href="#" class="admin-card" onclick="alert('Configuración del sistema - Próximamente')">
                <div class="card-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <h3>Configuración</h3>
                <p>Ajustes generales del sistema y parámetros globales.</p>
                <span class="card-badge">En desarrollo</span>
            </a>

            <a href="#" class="admin-card" onclick="alert('Reportes y análisis - Próximamente')">
                <div class="card-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Reportes</h3>
                <p>Análisis de actividad, cargas y rendimiento del sistema.</p>
                <span class="card-badge">En desarrollo</span>
            </a>
        </div>

        <!-- Actividad reciente -->
        <div class="recent-section">
            <div class="section-title">
                <i class="fas fa-history"></i>
                <h2>Actividad reciente</h2>
            </div>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Nuevo usuario registrado: Carlos Mendoza</div>
                        <div class="activity-time">Hace 5 minutos</div>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">25 archivos subidos desde Mina San Cristóbal</div>
                        <div class="activity-time">Hace 2 horas</div>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Configuración de permisos actualizada</div>
                        <div class="activity-time">Hace 3 horas</div>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">Alerta: Espacio de almacenamiento al 85%</div>
                        <div class="activity-time">Hace 5 horas</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            submenu.classList.toggle('show');
            
            // Cerrar otros submenús
            const submenus = document.querySelectorAll('.submenu');
            submenus.forEach(item => {
                if (item.id !== id) {
                    item.classList.remove('show');
                }
            });
        }

        // Mantener el dashboard como activo
        document.addEventListener('DOMContentLoaded', function() {
            // No abrir ningún submenú por defecto en el dashboard admin
        });
    </script>

</body>
</html>