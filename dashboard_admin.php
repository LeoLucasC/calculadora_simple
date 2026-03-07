<?php
session_start();
include 'includes/db.php';

// SEGURIDAD: Solo ADMIN
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: index.html");
    exit();
}

// --- CONSULTAS PARA ESTADÍSTICAS REALES ---
// 1. Total Usuarios
$sql_users = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE estado = 1");
$total_users = $sql_users->fetch_assoc()['total'];

// 2. Total Clientes (Empresas)
$sql_clientes = $conn->query("SELECT COUNT(*) as total FROM clientes WHERE estado = 1");
$total_clientes = $sql_clientes->fetch_assoc()['total'];

// 3. Total Operaciones
$sql_ops = $conn->query("SELECT COUNT(*) as total FROM operaciones WHERE estado = 1");
$total_ops = $sql_ops->fetch_assoc()['total'];

// 4. Total Archivos
$sql_archivos = $conn->query("SELECT COUNT(*) as total FROM multimedia");
$total_archivos = $sql_archivos->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>KunturVR | Panel Administrativo</title>
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
            display: flex;
            align-items: center;
            gap: 5px;
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
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h1 i {
            color: #023675;
            font-size: 2.5rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 1.1rem;
            margin-top: 5px;
        }

        /* Stats cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
        }

        /* Grid de administración */
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .admin-card {
            background: white;
            border-radius: 24px;
            padding: 35px 25px;
            text-align: center;
            border: 1px solid #e9eef2;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
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
            transform: translateY(-8px);
            box-shadow: 0 25px 40px rgba(2, 54, 117, 0.12);
            border-color: #023675;
        }

        .admin-card:hover::before {
            transform: scaleX(1);
        }

        .card-icon-wrapper {
            width: 90px;
            height: 90px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            transition: all 0.3s ease;
        }

        .admin-card:hover .card-icon-wrapper {
            background: #023675;
            transform: scale(1.05);
        }

        .card-icon-wrapper i {
            font-size: 3rem;
            color: #023675;
            transition: all 0.3s ease;
        }

        .admin-card:hover .card-icon-wrapper i {
            color: white;
        }

        .admin-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .admin-card p {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .card-badge {
            display: inline-block;
            padding: 6px 16px;
            background: rgba(2, 54, 117, 0.08);
            color: #023675;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .admin-card:hover .card-badge {
            background: #023675;
            color: white;
        }

        /* Quick actions */
        .quick-actions {
            margin-top: 50px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .section-title h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #0f172a;
        }

        .section-title i {
            color: #023675;
            font-size: 1.3rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-item {
            background: white;
            border: 1px solid #e9eef2;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .action-item:hover {
            border-color: #023675;
            background: #f8fafc;
            transform: translateX(5px);
        }

        .action-icon {
            width: 40px;
            height: 40px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-icon i {
            color: #023675;
            font-size: 1.2rem;
        }

        .action-text {
            font-weight: 500;
            color: #1e293b;
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
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

  <?php include 'includes/navbar_admin.php'; ?>

   <div class="main-content">
        <div class="page-header">
            <h1>
                <i class="fas fa-crown"></i>
                Panel Administrativo
            </h1>
            <p>Bienvenido, <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong>. Aquí tienes el resumen del sistema en tiempo real.</p>
        </div>

        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h4>Usuarios Activos</h4>
                    <div class="number"><?php echo $total_users; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-info">
                    <h4>Empresas</h4>
                    <div class="number"><?php echo $total_clientes; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-hard-hat"></i></div>
                <div class="stat-info">
                    <h4>Operaciones</h4>
                    <div class="number"><?php echo $total_ops; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-video"></i></div>
                <div class="stat-info">
                    <h4>Archivos</h4>
                    <div class="number"><?php echo $total_archivos; ?></div>
                </div>
            </div>
        </div>

        <div class="admin-grid">
            <div class="admin-card" onclick="window.location.href='admin_usuarios.php'">
                <div class="card-icon-wrapper"><i class="fas fa-users-cog"></i></div>
                <h2>Gestionar Usuarios</h2>
                <p>Crear cuentas, editar roles y accesos.</p>
                <span class="card-badge"><i class="fas fa-arrow-right"></i> Acceder</span>
            </div>

            <div class="admin-card" onclick="window.location.href='admin_clientes.php'">
                <div class="card-icon-wrapper"><i class="fas fa-building"></i></div>
                <h2>Empresas Mineras</h2>
                <p>Administrar clientes y razones sociales.</p>
                <span class="card-badge"><i class="fas fa-arrow-right"></i> Acceder</span>
            </div>

            <div class="admin-card" onclick="window.location.href='admin_operaciones.php'">
                <div class="card-icon-wrapper"><i class="fas fa-hard-hat"></i></div>
                <h2>Operaciones</h2>
                <p>Gestión de minas, tajos y zonas operativas.</p>
                <span class="card-badge"><i class="fas fa-arrow-right"></i> Acceder</span>
            </div>

            <div class="admin-card" onclick="window.location.href='admin_archivos.php'">
                <div class="card-icon-wrapper"><i class="fas fa-file-video"></i></div>
                <h2>Multimedia</h2>
                <p>Auditoría de fotos y videos 360° subidos.</p>
                <span class="card-badge"><i class="fas fa-arrow-right"></i> Acceder</span>
            </div>

            <div class="admin-card" onclick="window.location.href='admin_configuracion.php'">
                <div class="card-icon-wrapper"><i class="fas fa-cogs"></i></div>
                <h2>Configuración</h2>
                <p>Roles, Tipos de Explotación y Maestras.</p>
                <span class="card-badge"><i class="fas fa-arrow-right"></i> Acceder</span>
            </div>
        </div>

        <div class="quick-actions">
            <div class="section-title">
                <i class="fas fa-bolt"></i>
                <h3>Accesos Directos</h3>
            </div>
            <div class="actions-grid">
                <div class="action-item" onclick="window.location.href='admin_usuarios.php'">
                    <div class="action-icon"><i class="fas fa-user-plus"></i></div>
                    <span class="action-text">Crear Usuario</span>
                </div>
                <div class="action-item" onclick="window.location.href='admin_clientes.php'">
                    <div class="action-icon"><i class="fas fa-building"></i></div>
                    <span class="action-text">Nueva Empresa</span>
                </div>
                <div class="action-item" onclick="window.location.href='admin_operaciones.php'">
                    <div class="action-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <span class="action-text">Nueva Operación</span>
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

        // No abrir ningún submenú por defecto
        document.addEventListener('DOMContentLoaded', function() {
            // Dashboard activo por defecto
        });
    </script>

</body>
</html>