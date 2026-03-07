<?php
// Obtener el nombre del archivo actual (ej: admin_configuracion.php)
$pagina = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar">
    <div class="logo-container">
        <div class="logo">
            KUNTURVR
            <span>Panel Administrativo</span>
        </div>
    </div>

    <div class="user-info">
        <div class="user-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></div>
        <div class="user-role">
            <i class="fas fa-shield-alt"></i>
            Administrador
        </div>
    </div>

    <div class="menu">
        <div class="menu-item <?php echo ($pagina == 'dashboard_admin.php') ? 'active' : ''; ?>" 
             onclick="window.location.href='dashboard_admin.php'">
            <i class="fas fa-chart-pie"></i> Dashboard
        </div>

        <div class="menu-item <?php echo ($pagina == 'admin_usuarios.php') ? 'active' : ''; ?>" 
             onclick="window.location.href='admin_usuarios.php'">
            <i class="fas fa-users-cog"></i> Usuarios
        </div>

        <div class="menu-item <?php echo ($pagina == 'admin_clientes.php') ? 'active' : ''; ?>" 
             onclick="window.location.href='admin_clientes.php'">
            <i class="fas fa-building"></i> Empresa
        </div>

        <div class="menu-item <?php echo ($pagina == 'admin_ubicacion.php') ? 'active' : ''; ?>" 
             onclick="window.location.href='admin_ubicacion.php'">
            <i class="fas fa-map-marker-alt"></i> Ubicación
        </div>

        <div class="menu-item <?php echo ($pagina == 'admin_archivos.php') ? 'active' : ''; ?>" 
             onclick="window.location.href='admin_archivos.php'">
            <i class="fas fa-file-video"></i> Archivos
        </div>

        <div class="menu-item <?php echo ($pagina == 'admin_configuracion.php') ? 'active' : ''; ?>" 
             onclick="window.location.href='admin_configuracion.php'">
            <i class="fas fa-cog"></i> Configuración
        </div>

        <div class="menu-item <?php echo ($pagina == 'admin_backup.php') ? 'active' : ''; ?>" 
             onclick="window.location.href='admin_backup.php'">
            <i class="fas fa-database"></i> Respaldos
        </div>
    </div>

    <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
    </a>
</div>