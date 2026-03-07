<?php
// Detectar página actual para marcarla como activa
$pagina = basename($_SERVER['PHP_SELF']);

// Variables por defecto
$foto_perfil = '';
$logo_empresa = 'https://www.kingsoft.pe/kunturvr/imagenes/logo1.png';
$nombre_empresa = 'KUNTUR VR';

// Si hay un usuario en sesión, buscamos su info y la de su empresa
if (!empty($_SESSION['id_usuario'])) {
    if (!isset($conn)) include __DIR__ . '/db.php';
    $id_nav = (int)$_SESSION['id_usuario'];
    
    // Hacemos JOIN con clientes para traer el logo y nombre de la empresa
    $sql_nav = "SELECT u.foto_perfil, c.razon_social, c.logo_url 
                FROM usuarios u 
                LEFT JOIN clientes c ON u.id_cliente = c.id_cliente 
                WHERE u.id_usuario = $id_nav";
                
    $r = $conn->query($sql_nav);
    
    if ($r && $row_nav = $r->fetch_assoc()) {
        
        // 1. Asignar Foto de Perfil del Usuario
        if (!empty($row_nav['foto_perfil']) && file_exists($row_nav['foto_perfil'])) {
            $foto_perfil = $row_nav['foto_perfil'];
            $_SESSION['foto_perfil'] = $foto_perfil; // Guardar en sesión
        }

        // 2. Asignar Logo y Nombre de la Empresa
        if (!empty($row_nav['razon_social'])) {
            $nombre_empresa = $row_nav['razon_social'];
        }
        
        if (!empty($row_nav['logo_url']) && file_exists($row_nav['logo_url'])) {
            $logo_empresa = $row_nav['logo_url'];
        }
    }
}
?>

<style>
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

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
    }
    .sidebar-overlay.show { display: block; }

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
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        font-family: 'Inter', sans-serif;
    }

    .sidebar .logo-container {
        padding: 0 24px 30px 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    
    /* Logo en tamaño normal (sin círculo) */
    .sidebar .logo-container img {
        max-width: 100%;
        height: auto;
        max-height: 80px;
        width: auto;
        object-fit: contain;
    }
    
    .sidebar .logo-text {
        font-size: 1.3rem;
        font-weight: 700;
        letter-spacing: -0.5px;
        color: white;
        line-height: 1.2;
        text-align: center;
    }

    /* ===== USER INFO CON FOTO ===== */
    .sidebar .user-info {
        padding: 20px 24px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 10px;
    }

    /* Contenedor circular de la foto */
    .sidebar .user-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        border: 3px solid rgba(255, 255, 255, 0.35);
        overflow: hidden;
        background: rgba(255, 255, 255, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        transition: border-color 0.3s;
    }

    .sidebar .user-avatar:hover {
        border-color: rgba(255, 255, 255, 0.7);
    }

    /* Foto real */
    .sidebar .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    /* Ícono placeholder si no hay foto */
    .sidebar .user-avatar i {
        font-size: 2rem;
        color: rgba(255, 255, 255, 0.6);
    }

    .sidebar .user-name {
        font-weight: 600;
        font-size: 1rem;
        color: white;
        line-height: 1.3;
    }
    .sidebar .user-role {
        font-size: 0.8rem;
        opacity: 0.65;
        color: white;
        margin-top: -6px;
    }
    /* ================================ */

    .sidebar .menu {
        flex: 1;
        padding: 24px 0;
    }
    .sidebar .menu-item {
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
        font-size: 0.95rem;
    }
    .sidebar .menu-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }
    .sidebar .menu-item.active {
        background: white;
        color: #023675;
        font-weight: 600;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }
    .sidebar .menu-item i {
        width: 20px;
        text-align: center;
        font-size: 1.1rem;
    }
    .sidebar .menu-item.active i {
        color: #023675;
    }

    .sidebar .submenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        margin: 0 8px 0 24px;
    }
    .sidebar .submenu.show { max-height: 300px; }

    .sidebar .submenu-item {
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
    .sidebar .submenu-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }
    .sidebar .submenu-item.active {
        color: white;
        font-weight: 600;
    }

    .sidebar .logout-btn {
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
        font-size: 0.9rem;
    }
    .sidebar .logout-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    @media (max-width: 768px) {
        .mobile-toggle { display: block; }
        .sidebar {
            position: fixed;
            left: 0; top: 0; bottom: 0;
            transform: translateX(-100%);
        }
        .sidebar.show { transform: translateX(0); }
    }
</style>

<button class="mobile-toggle" id="sidebarToggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="logo-container">
        <img src="<?= htmlspecialchars($logo_empresa) ?>?t=<?= time() ?>" alt="Logo Empresa">
        <div class="logo-text">
            <?= htmlspecialchars($nombre_empresa) ?>
        </div>
    </div>

    <div class="user-info">
        <div class="user-avatar">
            <?php if (!empty($foto_perfil)): ?>
                <img src="<?= htmlspecialchars($foto_perfil) ?>?t=<?= time() ?>" alt="Foto de perfil">
            <?php else: ?>
                <i class="fas fa-user-circle"></i>
            <?php endif; ?>
        </div>

        <div class="user-name"><?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></div>
        <div class="user-role">Visualizador Autorizado</div>
    </div>

    <div class="menu">
        <!-- 1. GALERÍA -->
        <a href="viewer_galeria.php" class="menu-item <?= ($pagina == 'viewer_galeria.php') ? 'active' : '' ?>">
            <i class="fas fa-images"></i> Galería
        </a>
        
        <!-- 2. AR -->
        <div class="menu-item" onclick="toggleSubmenu('sub-ar')">
            <i class="fas fa-vr-cardboard"></i> AR
            <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
        </div>

        <div class="submenu <?= ($pagina == 'subir_marcadores.php' || $pagina == 'entorno_ar.php' || $pagina == 'lista_marcadores.php' || $pagina == 'entorno_ar_viewer.php') ? 'show' : '' ?>" id="sub-ar">
            <a href="entorno_ar_viewer.php" 
               class="submenu-item <?= ($pagina == 'entorno_ar_viewer.php' || $pagina == 'entorno_ar.php') ? 'active' : '' ?>">
                <i class="fas fa-cube"></i> Entorno AR con Marcadores
            </a>
        </div>
        
        <!-- 3. XR -->
        <div class="menu-item" onclick="toggleSubmenu('sub-xr')">
            <i class="fas fa-vr-cardboard"></i> XR
            <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 0.8rem;"></i>
        </div>

        <div class="submenu <?= ($pagina == 'galeria_xr_foto_360.php' || $pagina == 'galeria_xr_video_360.php' || $pagina == 'galeria_xr_modelo_3d.php') ? 'show' : '' ?>" id="sub-xr">
            <a href="galeria_xr_foto_360.php" 
               class="submenu-item <?= ($pagina == 'galeria_xr_foto_360.php') ? 'active' : '' ?>">
                <i class="fas fa-camera"></i> GALERIA XR FOTO 360
            </a>
            <a href="galeria_xr_video_360.php" 
               class="submenu-item <?= ($pagina == 'galeria_xr_video_360.php') ? 'active' : '' ?>">
                <i class="fas fa-video"></i> GALERIA XR VIDEO 360
            </a>
            <a href="galeria_xr_modelo_3d.php" 
               class="submenu-item <?= ($pagina == 'galeria_xr_modelo_3d.php') ? 'active' : '' ?>">
                <i class="fas fa-cube"></i> GALERIA XR MODELO 3D
            </a>
        </div>

        <!-- 4. MI PERFIL (al final) -->
        <a href="mi_perfil.php" class="menu-item <?= ($pagina == 'mi_perfil.php') ? 'active' : '' ?>">
            <i class="fas fa-user"></i> Mi Perfil
        </a>
    </div>

    <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
    </a>
</div>

<script>
    function toggleSubmenu(id) {
        document.getElementById(id).classList.toggle('show');
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const btnIcon = document.querySelector('#sidebarToggle i');

        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');

        if (sidebar.classList.contains('show')) {
            btnIcon.classList.replace('fa-bars', 'fa-times');
        } else {
            btnIcon.classList.replace('fa-times', 'fa-bars');
        }
    }

    document.getElementById('sidebarOverlay').addEventListener('click', toggleSidebar);
</script>