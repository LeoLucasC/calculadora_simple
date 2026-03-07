<?php
session_start();
// SEGURIDAD: Si no está logueado, fuera.
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.html");
    exit();
}
// SOLO PARA ROL VIEWER (id_rol = 2)
if ($_SESSION['id_rol'] != 2) {
    // Si es ADMIN o CREATOR, redirigir a su panel correspondiente
    if ($_SESSION['id_rol'] == 1) {
        header("Location: dashboard_admin.php");
    } elseif ($_SESSION['id_rol'] == 4) {
        header("Location: dashboard_creator.php");
    }
    exit();
}

// Incluir la conexión a la base de datos
include 'includes/db.php';
$id_user = $_SESSION['id_usuario'];
$mensaje = ''; // Para guardar alertas de éxito o error

// ==========================================
// 1. PROCESAR ACTUALIZACIÓN DE DATOS O CLAVE
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // =========================================
    // CASO 0: Subir Foto de Perfil
    // =========================================
    if (isset($_POST['action']) && $_POST['action'] == 'update_photo') {
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
            $file     = $_FILES['foto_perfil'];
            $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 3 * 1024 * 1024; // 3 MB

            if (!in_array($file['type'], $allowed)) {
                $mensaje = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Solo se permiten imágenes JPG, PNG, GIF o WEBP.<span class="alert-close" onclick="this.parentElement.remove()">&times;</span></div>';
            } elseif ($file['size'] > $max_size) {
                $mensaje = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> La imagen no debe superar los 3 MB.<span class="alert-close" onclick="this.parentElement.remove()">&times;</span></div>';
            } else {
                // Crear carpeta uploads/ si no existe
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Nombre único para evitar conflictos
                $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename  = 'perfil_' . $id_user . '_' . time() . '.' . $ext;
                $dest      = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    // Eliminar foto anterior si existe y no es la de placeholder
                    $res_old = $conn->query("SELECT foto_perfil FROM usuarios WHERE id_usuario = $id_user");
                    $old     = $res_old->fetch_assoc();
                    if (!empty($old['foto_perfil']) && file_exists($old['foto_perfil'])) {
                        unlink($old['foto_perfil']);
                    }

                    // Guardar nueva ruta en BD
                    $dest_esc = $conn->real_escape_string($dest);
                    $conn->query("UPDATE usuarios SET foto_perfil='$dest_esc' WHERE id_usuario=$id_user");
                    $_SESSION['foto_perfil'] = $dest_esc; // Actualizar sesión
                    $mensaje = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ¡Foto de perfil actualizada con éxito!<span class="alert-close" onclick="this.parentElement.remove()">&times;</span></div>';
                } else {
                    $mensaje = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Error al mover el archivo. Verifica permisos de la carpeta uploads/.<span class="alert-close" onclick="this.parentElement.remove()">&times;</span></div>';
                }
            }
        } else {
            $mensaje = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> No se recibió ninguna imagen.<span class="alert-close" onclick="this.parentElement.remove()">&times;</span></div>';
        }
    }

    // CASO A: Actualizar Datos Personales
    if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        $nombre    = $conn->real_escape_string($_POST['nombre']);
        $telefono  = $conn->real_escape_string($_POST['telefono']);
        $direccion = $conn->real_escape_string($_POST['direccion']);
        
        $sql_update = "UPDATE usuarios SET nombre='$nombre', telefono='$telefono', direccion='$direccion' WHERE id_usuario=$id_user";
        if ($conn->query($sql_update)) {
            // Actualizar nombre en sesión
            $_SESSION['nombre'] = $nombre;
            $mensaje = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ¡Datos personales actualizados con éxito!<span class="alert-close" onclick="this.parentElement.remove()">&times;</span></div>';
        } else {
            $mensaje = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Error al actualizar datos.</div>';
        }
    }
    
    // CASO B: Actualizar Contraseña
    if (isset($_POST['action']) && $_POST['action'] == 'update_password') {
        $old_pass     = $_POST['old_pass'];
        $new_pass     = $_POST['new_pass'];
        $confirm_pass = $_POST['confirm_pass'];
        
        $columna_bd = 'password_hash'; 
        
        $sql_check       = "SELECT $columna_bd FROM usuarios WHERE id_usuario = $id_user";
        $resultado_check = $conn->query($sql_check);
        $row_check       = $resultado_check->fetch_assoc();
        $pass_bd         = $row_check[$columna_bd];
        
        $pass_correcta = false;
        if (password_verify($old_pass, $pass_bd) || $pass_bd === $old_pass) {
            $pass_correcta = true;
        }
        
        if (!$pass_correcta) {
            $mensaje = '<div class="alert alert-warning"><i class="fas fa-times-circle"></i> La contraseña actual es incorrecta.</div>';
        } elseif ($new_pass !== $confirm_pass) {
            $mensaje = '<div class="alert alert-warning"><i class="fas fa-times-circle"></i> Las nuevas contraseñas no coinciden.</div>';
        } else {
            if (strpos($pass_bd, '$2y$') === 0) {
                $new_pass_to_save = password_hash($new_pass, PASSWORD_DEFAULT);
            } else {
                $new_pass_to_save = $conn->real_escape_string($new_pass);
            }
            $conn->query("UPDATE usuarios SET $columna_bd='$new_pass_to_save' WHERE id_usuario=$id_user");
            $mensaje = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ¡Contraseña actualizada con éxito!<span class="alert-close" onclick="this.parentElement.remove()">&times;</span></div>';
        }
    }
}

// ==========================================
// 2. OBTENER DATOS PARA MOSTRAR EN PANTALLA
// ==========================================
$sql_user = "SELECT u.*, r.descripcion as rol_nombre 
             FROM usuarios u 
             JOIN roles r ON u.id_rol = r.id_rol 
             WHERE u.id_usuario = $id_user";
$user_data = $conn->query($sql_user)->fetch_assoc();

// Contar total de marcadores AR para VIEWER
$res_marcadores = $conn->query("SELECT COUNT(*) as total FROM marcadores_ar WHERE id_usuario = $id_user");
$total_marcadores = 0;
if ($res_marcadores) {
    $total_marcadores = $res_marcadores->fetch_assoc()['total'];
}

// Contar total de archivos multimedia
$res_total_multimedia = $conn->query("SELECT COUNT(*) as total FROM multimedia WHERE id_usuario = $id_user");
$total_multimedia = 0;
if ($res_total_multimedia) {
    $total_multimedia = $res_total_multimedia->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>KunturVR | Mi Perfil (VIEWER)</title>
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

        .main-content {
            flex: 1;
            padding: 30px 40px;
            overflow-y: auto;
            transition: margin-left 0.3s ease;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h1 i {
            color: #023675;
        }

        .page-header p {
            color: #64748b;
            font-size: 1rem;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
        }

        .profile-sidebar {
            background: white;
            border-radius: 16px;
            border: 1px solid #e9eef2;
            overflow: hidden;
            height: fit-content;
        }

        .profile-cover {
            height: 80px;
            background: linear-gradient(135deg, #023675 0%, #0349a3 100%);
        }

        /* ===================== FOTO DE PERFIL ===================== */
        .profile-photo {
            text-align: center;
            margin-top: -50px;
            padding: 0 20px;
        }

        .photo-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto;
        }

        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            background: #f1f5f9;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        /* Ícono placeholder cuando no hay foto */
        .profile-img-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid white;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .profile-img-placeholder i {
            font-size: 52px;
            color: #cbd5e1;
        }

        .photo-upload-btn {
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 30px;
            height: 30px;
            background: #023675;
            border: 2px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 10;
        }

        .photo-upload-btn:hover {
            background: #0349a3;
            transform: scale(1.1);
        }

        .photo-upload-btn i {
            font-size: 0.75rem;
            pointer-events: none;
        }

        /* Input file oculto */
        #input_foto {
            display: none;
        }

        /* Preview al seleccionar */
        .photo-preview-hint {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 6px;
            min-height: 18px;
        }
        /* =========================================================== */

        .profile-name {
            margin-top: 10px;
            text-align: center;
        }

        .profile-name h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .profile-name p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 16px;
        }

        .profile-stats {
            display: flex;
            justify-content: center;
            gap: 24px;
            padding: 16px 0;
            border-top: 1px solid #e9eef2;
            border-bottom: 1px solid #e9eef2;
            margin: 0 20px;
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #023675;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 4px;
        }

        .profile-info {
            padding: 20px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #e9eef2;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-icon {
            width: 36px;
            height: 36px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #023675;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: #1e293b;
        }

        .profile-main {
            background: white;
            border-radius: 16px;
            border: 1px solid #e9eef2;
            padding: 30px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e9eef2;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1e293b;
            font-size: 0.9rem;
        }

        .form-group label i {
            color: #023675;
            margin-right: 6px;
            width: 16px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #023675;
            box-shadow: 0 0 0 3px rgba(2, 54, 117, 0.1);
            background: white;
        }

        .form-control[readonly] {
            background: #f1f5f9;
            cursor: not-allowed;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9eef2;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #023675;
            color: white;
        }

        .btn-primary:hover {
            background: #0349a3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(2, 54, 117, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #1e293b;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }

        .alert-success {
            background: #dff0d8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .alert-warning {
            background: #fff3e0;
            color: #f57c00;
            border: 1px solid #ffe0b2;
        }

        .alert i { font-size: 1.2rem; }

        .alert-close {
            margin-left: auto;
            cursor: pointer;
            font-size: 1.2rem;
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .alert-close:hover { opacity: 1; }

        .profile-tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            border-bottom: 1px solid #e9eef2;
            padding-bottom: 12px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            color: #64748b;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn i { font-size: 1rem; }

        .tab-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .tab-btn.active {
            background: #023675;
            color: white;
        }

        .tab-pane {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-pane.active { display: block; }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        /* Spinner de carga de foto */
        .photo-loading {
            display: none;
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.75);
            align-items: center;
            justify-content: center;
        }

        .photo-loading.show { display: flex; }

        .photo-loading i {
            color: #023675;
            font-size: 1.4rem;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to   { transform: rotate(360deg); }
        }

        /* Badge para rol */
        .role-badge {
            background: rgba(2, 54, 117, 0.1);
            color: #023675;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .role-badge i {
            font-size: 0.8rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .profile-container { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            body { flex-direction: column; }

            .main-content {
                margin-left: 0;
                padding: 80px 20px 30px 20px;
                width: 100%;
            }

            .page-header h1 { font-size: 1.8rem; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; justify-content: center; }
            .profile-tabs { flex-wrap: wrap; }
            .tab-btn { flex: 1; justify-content: center; }
        }

        @media (max-width: 480px) {
            .main-content { padding: 70px 15px 20px 15px; }
            .page-header h1 { font-size: 1.5rem; }
            .profile-main { padding: 20px; }
            .section-title { font-size: 1.1rem; }
        }
    </style>
</head>
<body>

    <!-- Incluimos el navbar específico para VIEWER -->
    <?php include 'includes/navar_viewer.php'; ?>

    <div class="main-content" id="main-content">
        <div class="page-header">
            <h1><i class="fas fa-user-circle"></i> Mi Perfil</h1>
            <p>Gestiona tu información personal en el sistema</p>
        </div>

        <?= $mensaje ?>

        <div class="profile-tabs">
            <button class="tab-btn active" onclick="showTab('personal', event)">
                <i class="fas fa-user"></i> Información Personal
            </button>
            <button class="tab-btn" onclick="showTab('security', event)">
                <i class="fas fa-shield-alt"></i> Seguridad
            </button>
        </div>

        <div class="tab-pane active" id="tab-personal">
            <div class="profile-container">

                <!-- ===== SIDEBAR CON FOTO ===== -->
                <div class="profile-sidebar">
                    <div class="profile-cover"></div>
                    <div class="profile-photo">

                        <!-- Formulario silencioso para subir la foto -->
                        <form id="form_foto" action="" method="POST" enctype="multipart/form-data" style="display:none;">
                            <input type="hidden" name="action" value="update_photo">
                            <input type="file" name="foto_perfil" id="input_foto" accept="image/jpeg,image/png,image/gif,image/webp">
                        </form>

                        <div class="photo-container">
                            <?php if (!empty($user_data['foto_perfil']) && file_exists($user_data['foto_perfil'])): ?>
                                <img src="<?= htmlspecialchars($user_data['foto_perfil']) ?>?t=<?= time() ?>"
                                     alt="Foto de perfil"
                                     class="profile-img"
                                     id="foto_preview">
                            <?php else: ?>
                                <div class="profile-img-placeholder" id="foto_placeholder">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <img src="" alt="Foto de perfil" class="profile-img" id="foto_preview"
                                     style="display:none;">
                            <?php endif; ?>

                            <!-- Spinner de carga -->
                            <div class="photo-loading" id="photo_loading">
                                <i class="fas fa-spinner"></i>
                            </div>

                            <!-- Botón lápiz -->
                            <div class="photo-upload-btn" onclick="document.getElementById('input_foto').click()" title="Cambiar foto">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>

                        <p class="photo-preview-hint" id="foto_hint">JPG, PNG o GIF · Máx. 3 MB</p>

                        <div class="profile-name">
                            <h3><?= htmlspecialchars($user_data['nombre']) ?></h3>
                            <p><span class="role-badge"><i class="fas fa-eye"></i> <?= htmlspecialchars($user_data['rol_nombre']) ?></span></p>
                        </div>

                        <div class="profile-stats">
                            <div class="stat">
                                <div class="stat-value"><?= $total_marcadores ?></div>
                                <div class="stat-label">Marcadores AR</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value"><?= $total_multimedia ?></div>
                                <div class="stat-label">Archivos</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ===== FIN SIDEBAR ===== -->

                <div class="profile-main">
                    <h3 class="section-title"><i class="fas fa-edit"></i> Datos de la Cuenta</h3>
                    <form action="" method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label><i class="fas fa-user"></i> Nombre Completo</label>
                                <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($user_data['nombre']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-id-badge"></i> Usuario (Login)</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['usuario']) ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Teléfono</label>
                                <input type="tel" name="telefono" class="form-control" value="<?= htmlspecialchars($user_data['telefono'] ?? '') ?>" placeholder="Ej: 987654321">
                            </div>
                            <div class="form-group full-width">
                                <label><i class="fas fa-map-marker-alt"></i> Dirección de Residencia</label>
                                <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($user_data['direccion'] ?? '') ?>" placeholder="Calle, Distrito, Ciudad">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="tab-pane" id="tab-security">
            <div class="profile-main">
                <h3 class="section-title"><i class="fas fa-lock"></i> Cambiar Contraseña</h3>
                <form action="" method="POST">
                    <input type="hidden" name="action" value="update_password">
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Contraseña Actual</label>
                            <input type="password" name="old_pass" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Nueva Contraseña</label>
                            <input type="password" name="new_pass" class="form-control" id="new_pass" required>
                        </div>
                        <div class="form-group">
                            <label>Confirmar Nueva Contraseña</label>
                            <input type="password" name="confirm_pass" class="form-control" id="confirm_pass" required>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Actualizar Contraseña</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
        // --- GESTIÓN DE TABS ---
        function showTab(tabName, event) {
            document.querySelectorAll('.tab-pane').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
            if (event) event.currentTarget.classList.add('active');
        }

        // --- VALIDACIÓN DE PASSWORD EN TIEMPO REAL ---
        const pass    = document.getElementById('new_pass');
        const confirm = document.getElementById('confirm_pass');

        function validar() {
            if (confirm.value && pass.value !== confirm.value) {
                confirm.style.borderColor = '#ef4444';
            } else {
                confirm.style.borderColor = '#e2e8f0';
            }
        }
        if (pass && confirm) {
            pass.addEventListener('input', validar);
            confirm.addEventListener('input', validar);
        }

        // --- AUTO-CERRAR ALERTAS ---
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(a => a.remove());
        }, 4000);

        // =============================================
        // LÓGICA DE SUBIDA DE FOTO DE PERFIL
        // =============================================
        const inputFoto    = document.getElementById('input_foto');
        const formFoto     = document.getElementById('form_foto');
        const fotoPreview  = document.getElementById('foto_preview');
        const fotoPH       = document.getElementById('foto_placeholder');
        const fotoHint     = document.getElementById('foto_hint');
        const photoLoading = document.getElementById('photo_loading');

        if (inputFoto) {
            inputFoto.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) return;

                // Validación en cliente (complementaria a la del servidor)
                const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowed.includes(file.type)) {
                    fotoHint.textContent = '⚠ Solo JPG, PNG, GIF o WEBP';
                    fotoHint.style.color = '#f57c00';
                    return;
                }
                if (file.size > 3 * 1024 * 1024) {
                    fotoHint.textContent = '⚠ La imagen supera los 3 MB';
                    fotoHint.style.color = '#f57c00';
                    return;
                }

                // Mostrar preview instantáneo
                const reader = new FileReader();
                reader.onload = function (e) {
                    fotoPreview.src = e.target.result;
                    fotoPreview.style.display = 'block';
                    if (fotoPH) fotoPH.style.display = 'none';
                };
                reader.readAsDataURL(file);

                // Mostrar spinner y enviar formulario
                fotoHint.textContent = 'Subiendo…';
                fotoHint.style.color = '#023675';
                photoLoading.classList.add('show');
                formFoto.submit();
            });
        }
    </script>
</body>
</html>