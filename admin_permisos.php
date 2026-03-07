<?php
session_start();
include 'includes/db.php';

// --- SEGURIDAD: Solo ADMIN ---
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: index.html");
    exit();
}

// Recibir el ID del usuario a gestionar
if (!isset($_GET['id'])) {
    header("Location: admin_usuarios.php");
    exit();
}
$target_user = intval($_GET['id']);

// --- LÓGICA 1: ASIGNAR NUEVO PERMISO ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_permiso') {
    $id_ubi = intval($_POST['id_ubicacion']);
    $id_gru = intval($_POST['id_grupo']);
    $nivel  = $conn->real_escape_string($_POST['nivel_acceso']);

    // Verificar si ya tiene permiso para ese grupo
    $check = $conn->query("SELECT id_permiso FROM permisos_usuarios WHERE id_usuario = $target_user AND id_grupo = $id_gru");
    
    if ($check->num_rows > 0) {
        // Si ya existe, lo actualizamos (ej. pasó de 'ver' a 'subir')
        $conn->query("UPDATE permisos_usuarios SET nivel_acceso = '$nivel' WHERE id_usuario = $target_user AND id_grupo = $id_gru");
    } else {
        // Si no existe, lo creamos
        $conn->query("INSERT INTO permisos_usuarios (id_usuario, id_ubicacion, id_grupo, nivel_acceso) VALUES ($target_user, $id_ubi, $id_gru, '$nivel')");
    }
    header("Location: admin_permisos.php?id=$target_user&success=1");
    exit();
}

// --- LÓGICA 2: ELIMINAR PERMISO ---
if (isset($_GET['del_permiso'])) {
    $id_del = intval($_GET['del_permiso']);
    $conn->query("DELETE FROM permisos_usuarios WHERE id_permiso = $id_del AND id_usuario = $target_user");
    header("Location: admin_permisos.php?id=$target_user&deleted=1");
    exit();
}

// --- CONSULTAS ---
// Datos del usuario
$datos_user = $conn->query("SELECT nombre, usuario, id_rol FROM usuarios WHERE id_usuario = $target_user")->fetch_assoc();

// Lista de permisos actuales
$sql_permisos = "SELECT p.id_permiso, p.nivel_acceso, u.nombre as ubicacion_nombre, g.nombre as grupo_nombre 
                 FROM permisos_usuarios p
                 INNER JOIN ubicaciones u ON p.id_ubicacion = u.id_ubicacion
                 INNER JOIN grupos_operativos g ON p.id_grupo = g.id_grupo
                 WHERE p.id_usuario = $target_user";
$permisos = $conn->query($sql_permisos);

// Para los selects del formulario
$ubicaciones = $conn->query("SELECT id_ubicacion, nombre FROM ubicaciones WHERE estado=1");
$grupos_all = $conn->query("SELECT id_grupo, nombre, id_ubicacion FROM grupos_operativos WHERE estado=1");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Permisos de Usuario</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1e293b; display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }
        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { font-size: 2rem; font-weight: 600; color: #0f172a; margin-bottom: 8px; }
        .page-header p { color: #64748b; font-size: 1rem; }
        
        .btn-volver { background: white; color: #023675; border: 1px solid #e2e8f0; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 500; transition: 0.3s; }
        .btn-volver:hover { background: #f1f5f9; }

        .form-container, .table-container { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: 1px solid #e9eef2; margin-bottom: 30px; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 0.9rem; color: #475569; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: inherit; }
        .btn-primary { background: #023675; color: white; padding: 11px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s; height: 42px; }
        .btn-primary:hover { background: #0349a3; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 16px 12px; font-weight: 600; font-size: 0.9rem; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e9eef2; }
        td { padding: 16px 12px; border-bottom: 1px solid #e9eef2; }
        .badge-ver { background: #e0f2fe; color: #0284c7; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge-subir { background: #dcfce7; color: #15803d; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .btn-delete { color: #ef4444; background: #fee2e2; padding: 8px 12px; border-radius: 8px; text-decoration: none; transition: 0.3s; }
        .btn-delete:hover { background: #fecaca; }

        @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }

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
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .btn-primary {
            background: #023675;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: #0347a3;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(2, 54, 117, 0.2);
        }

        /* Tabla */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9eef2;
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
            display: inline-block;
        }

        .bg-admin {
            background: #fff3cd;
            color: #856404;
        }

        .bg-user {
            background: #d4edda;
            color: #155724;
        }

        .bg-supervisor {
            background: #cce5ff;
            color: #004085;
        }

        /* Botones de acción */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-edit {
            background: #e8f0fe;
            color: #023675;
        }

        .btn-edit:hover {
            background: #023675;
            color: white;
            transform: translateY(-2px);
        }

        .btn-delete {
            background: #fee8e8;
            color: #dc3545;
        }

        .btn-delete:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 550px;
            margin: 50px auto;
            border-radius: 24px;
            padding: 35px;
            position: relative;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 24px;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.3s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: #f1f5f9;
        }

        .close-modal:hover {
            color: #0f172a;
            background: #e2e8f0;
        }

        .modal-content h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-content h2 i {
            color: #023675;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1e293b;
            font-size: 0.95rem;
        }

        .form-group label i {
            color: #023675;
            margin-right: 8px;
            width: 18px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.95rem;
            color: #1e293b;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: #023675;
            box-shadow: 0 0 0 3px rgba(2, 54, 117, 0.1);
            background: white;
        }

        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 48px;
        }

        .btn-save {
            width: 100%;
            padding: 14px;
            background: #023675;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-save:hover {
            background: #0347a3;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(2, 54, 117, 0.3);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            text-decoration: none;
            margin-top: 25px;
            font-size: 0.95rem;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #023675;
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
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .table-container {
                padding: 15px;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar_admin.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>Permisos de Acceso</h1>
                <p>Gestionando a: <strong style="color:#023675;"><?php echo htmlspecialchars($datos_user['nombre']); ?> (<?php echo htmlspecialchars($datos_user['usuario']); ?>)</strong></p>
            </div>
            <a href="admin_usuarios.php" class="btn-volver"><i class="fas fa-arrow-left"></i> Volver a Usuarios</a>
        </div>

        <?php if(isset($_GET['success'])) echo '<div style="background:#dcfce7; color:#15803d; padding:15px; border-radius:8px; margin-bottom:20px;">Permiso asignado correctamente.</div>'; ?>
        <?php if(isset($_GET['deleted'])) echo '<div style="background:#fee2e2; color:#b91c1c; padding:15px; border-radius:8px; margin-bottom:20px;">Permiso revocado.</div>'; ?>

        <div class="form-container">
            <h3 style="margin-bottom: 20px; color: #0f172a;"><i class="fas fa-plus-circle"></i> Asignar Nuevo Permiso</h3>
            <form action="admin_permisos.php?id=<?php echo $target_user; ?>" method="POST">
                <input type="hidden" name="action" value="add_permiso">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Ubicación</label>
                        <select name="id_ubicacion" id="select_ubicacion" class="form-control" required>
                            <option value="">Seleccione Ubicación...</option>
                            <?php while($u = $ubicaciones->fetch_assoc()): ?>
                                <option value="<?php echo $u['id_ubicacion']; ?>"><?php echo $u['nombre']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Grupo Operativo</label>
                        <select name="id_grupo" id="select_grupo" class="form-control" required>
                            <option value="">Primero seleccione ubicación...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nivel de Acceso</label>
                        <select name="nivel_acceso" class="form-control" required>
                            <option value="ver">Solo Ver (Lectura)</option>
                            <option value="subir">Subir Evidencia (Escritura)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn-primary">Añadir Permiso</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-container">
            <h3 style="margin-bottom: 20px; color: #0f172a;"><i class="fas fa-list"></i> Permisos Actuales</h3>
            <?php if ($permisos->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Ubicación</th>
                            <th>Grupo Operativo</th>
                            <th>Nivel de Acceso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($p = $permisos->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo $p['ubicacion_nombre']; ?></strong></td>
                            <td><?php echo $p['grupo_nombre']; ?></td>
                            <td>
                                <span class="badge-<?php echo $p['nivel_acceso']; ?>">
                                    <i class="fas <?php echo $p['nivel_acceso'] == 'subir' ? 'fa-upload' : 'fa-eye'; ?>"></i> 
                                    <?php echo strtoupper($p['nivel_acceso']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="admin_permisos.php?id=<?php echo $target_user; ?>&del_permiso=<?php echo $p['id_permiso']; ?>" 
                                   class="btn-delete" 
                                   onclick="return confirm('¿Seguro que deseas quitarle el acceso a este grupo?');" title="Revocar Permiso">
                                    <i class="fas fa-trash-alt"></i> Revocar
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #64748b;">
                    <i class="fas fa-shield-alt" style="font-size: 3rem; margin-bottom: 10px; opacity: 0.5;"></i>
                    <p>Este usuario no tiene accesos asignados aún. No podrá ver ni subir nada.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const gruposData = [
            <?php 
            while($g = $grupos_all->fetch_assoc()) {
                echo "{ id: " . $g['id_grupo'] . ", nombre: '" . addslashes($g['nombre']) . "', id_ubicacion: " . $g['id_ubicacion'] . " },\n";
            }
            ?>
        ];

        document.getElementById('select_ubicacion').addEventListener('change', function() {
            const idUbi = this.value;
            const selectGrupos = document.getElementById('select_grupo');
            
            selectGrupos.innerHTML = '<option value="">Seleccione un grupo...</option>';
            
            const gruposFiltrados = gruposData.filter(g => g.id_ubicacion == idUbi);
            gruposFiltrados.forEach(g => {
                const opt = document.createElement('option');
                opt.value = g.id;
                opt.textContent = g.nombre;
                selectGrupos.appendChild(opt);
            });
        });
    </script>
</body>
</html>