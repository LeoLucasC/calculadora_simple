<?php
session_start();
include 'includes/db.php';

// --- SEGURIDAD: Solo ADMIN ---
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: dashboard.php");
    exit();
}

// --- LÓGICA 1: ELIMINAR USUARIO ---
if (isset($_GET['delete_id'])) {
    $id_borrar = intval($_GET['delete_id']);
    // Evitar que el admin se borre a sí mismo
    if ($id_borrar != $_SESSION['id_usuario']) {
        $conn->query("DELETE FROM usuarios WHERE id_usuario = $id_borrar");
        header("Location: admin_usuarios.php");
        exit();
    } else {
        echo "<script>alert('No puedes auto-eliminarte.');</script>";
    }
}

// --- LÓGICA 2: EDITAR USUARIO (RECIBIR FORMULARIO DEL MODAL) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id_user = intval($_POST['id_usuario']);
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $usuario = $conn->real_escape_string($_POST['usuario']);
    $id_cliente = intval($_POST['id_cliente']);
    $id_rol = intval($_POST['id_rol']);
    
    // Si escribió contraseña nueva, la actualizamos. Si no, dejamos la vieja.
    if (!empty($_POST['password'])) {
        $pass_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $sql_update = "UPDATE usuarios SET nombre='$nombre', usuario='$usuario', id_cliente=$id_cliente, id_rol=$id_rol, password_hash='$pass_hash' WHERE id_usuario=$id_user";
    } else {
        $sql_update = "UPDATE usuarios SET nombre='$nombre', usuario='$usuario', id_cliente=$id_cliente, id_rol=$id_rol WHERE id_usuario=$id_user";
    }

    if ($conn->query($sql_update)) {
        header("Location: admin_usuarios.php"); // Refrescar página
        exit();
    } else {
        $error = "Error al actualizar: " . $conn->error;
    }
}

// --- CONSULTAS PARA LLENAR LA TABLA Y LOS SELECTS ---
// 1. Lista de usuarios
$sql_users = "SELECT u.id_usuario, u.nombre, u.usuario, u.estado, 
                     u.id_rol, u.id_cliente, /* Necesarios para el modal */
                     r.descripcion as rol, c.razon_social 
              FROM usuarios u 
              INNER JOIN roles r ON u.id_rol = r.id_rol
              INNER JOIN clientes c ON u.id_cliente = c.id_cliente";
$resultado = $conn->query($sql_users);

// 2. Lista de Clientes (Para el Select del Modal)
$clientes = $conn->query("SELECT id_cliente, razon_social FROM clientes");

// 3. Lista de Roles (Para el Select del Modal)
$roles = $conn->query("SELECT id_rol, descripcion FROM roles");



// --- LÓGICA 3: CREAR USUARIO (NUEVO) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create') {
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $usuario = $conn->real_escape_string($_POST['usuario']);
    $id_cliente = intval($_POST['id_cliente']);
    $id_rol = intval($_POST['id_rol']);
    $password = $_POST['password'];

    // Validar que el usuario no exista
    $check = $conn->query("SELECT id_usuario FROM usuarios WHERE usuario = '$usuario'");
    
    if ($check->num_rows > 0) {
        echo "<script>alert('Error: El usuario ya existe.');</script>";
    } else {
        // Encriptar contraseña (obligatoria al crear)
        $pass_hash = password_hash($password, PASSWORD_BCRYPT);
        
        $sql_insert = "INSERT INTO usuarios (id_cliente, id_rol, nombre, usuario, password_hash, estado) 
                       VALUES ($id_cliente, $id_rol, '$nombre', '$usuario', '$pass_hash', 1)";
        
        if ($conn->query($sql_insert)) {
            header("Location: admin_usuarios.php");
            exit();
        } else {
            echo "Error SQL: " . $conn->error;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Usuarios | KunturVR</title>
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

    <!-- Contenido principal -->
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1>Gestionar Usuarios</h1>
                <p>Control de personal y permisos del sistema</p>
            </div>
            <button class="btn-primary" onclick="abrirModalCrear()">
                <i class="fas fa-user-plus"></i>
                Nuevo Usuario
            </button>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Empresa</th>
                        <th>Rol</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><span style="font-weight: 600;">#<?php echo $row['id_usuario']; ?></span></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 32px; height: 32px; background: rgba(2, 54, 117, 0.08); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user" style="color: #023675; font-size: 0.9rem;"></i>
                                </div>
                                <?php echo $row['nombre']; ?>
                            </div>
                        </td>
                        <td><?php echo $row['usuario']; ?></td>
                        <td><?php echo $row['razon_social']; ?></td>
                        <td>
                            <span class="badge 
                                <?php 
                                if ($row['rol'] == 'ADMIN') echo 'bg-admin';
                                elseif ($row['rol'] == 'SUPERVISOR') echo 'bg-supervisor';
                                else echo 'bg-user';
                                ?>">
                                <?php echo $row['rol']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="admin_permisos.php?id=<?php echo $row['id_usuario']; ?>" 
                                   class="btn-icon" 
                                   style="background: #e0f2fe; color: #0284c7; text-decoration: none; padding: 8px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;"
                                   title="Gestionar Permisos de Visualización y Carga">
                                   <i class="fas fa-shield-alt"></i>
                                </a>
                                <button class="btn-icon btn-edit" 
                                    onclick="abrirModal(
                                        '<?php echo $row['id_usuario']; ?>',
                                        '<?php echo htmlspecialchars($row['nombre'], ENT_QUOTES); ?>',
                                        '<?php echo $row['usuario']; ?>',
                                        '<?php echo $row['id_cliente']; ?>',
                                        '<?php echo $row['id_rol']; ?>'
                                    )"
                                    title="Editar usuario">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <a href="admin_usuarios.php?delete_id=<?php echo $row['id_usuario']; ?>" 
                                   class="btn-icon btn-delete" 
                                   onclick="return confirm('¿Estás seguro de eliminar a <?php echo $row['usuario']; ?>?')"
                                   title="Eliminar usuario">
                                   <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div style="text-align: center; margin-top: 25px;">
                <a href="dashboard_admin.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    Volver al Panel
                </a>
            </div>
        </div>
    </div>

    <!-- Modal de edición -->
    <div id="modalEditar" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" onclick="cerrarModal()">&times;</span>
            <h2>
                <i class="fas fa-user-edit"></i>
                Editar Usuario
            </h2>
            
            <form action="admin_usuarios.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_id" name="id_usuario">

                <div class="form-group">
                    <label><i class="fas fa-user"></i>Nombre Completo</label>
                    <input type="text" id="edit_nombre" name="nombre" class="form-control" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-signature"></i>Usuario (Login)</label>
                    <input type="text" id="edit_usuario" name="usuario" class="form-control" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-building"></i>Empresa / Cliente</label>
                    <select id="edit_cliente" name="id_cliente" class="form-control" required>
                        <?php 
                        // Reseteamos el puntero del while de clientes para reusarlo
                        $clientes->data_seek(0); 
                        while($c = $clientes->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $c['id_cliente']; ?>"><?php echo $c['razon_social']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-tag"></i>Rol de Sistema</label>
                    <select id="edit_rol" name="id_rol" class="form-control" required>
                        <?php 
                        $roles->data_seek(0); 
                        while($r = $roles->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $r['id_rol']; ?>"><?php echo $r['descripcion']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-key"></i>Contraseña (Opcional)</label>
                    <input type="password" name="password" class="form-control" placeholder="Dejar vacío para no cambiar">
                    <small style="color: #94a3b8; display: block; margin-top: 5px; font-size: 0.8rem;">
                        <i class="fas fa-info-circle"></i> Solo si desea cambiar la contraseña
                    </small>
                </div>

                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i>
                    GUARDAR CAMBIOS
                </button>
            </form>
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

        // Función para abrir el modal y rellenar los datos
        function abrirModal(id, nombre, usuario, idCliente, idRol) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nombre').value = nombre;
            document.getElementById('edit_usuario').value = usuario;
            document.getElementById('edit_cliente').value = idCliente;
            document.getElementById('edit_rol').value = idRol;
            
            document.getElementById('modalEditar').style.display = 'block';
            
            // Prevenir scroll en el body
            document.body.style.overflow = 'hidden';
        }

        // Función para cerrar
        function cerrarModal() {
            document.getElementById('modalEditar').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Cerrar si clicamos fuera del modal
        window.onclick = function(event) {
            if (event.target == document.getElementById('modalEditar')) {
                cerrarModal();
            }
        }

        // Cerrar con tecla ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                cerrarModal();
            }
        });

// FUNCIÓN 1: ABRIR PARA CREAR (LIMPIO)
    function abrirModalCrear() {
        // 1. Limpiar todos los campos inputs
        document.getElementById('edit_id').value = '';
        document.getElementById('edit_nombre').value = '';
        document.getElementById('edit_usuario').value = '';
        document.getElementById('edit_cliente').selectedIndex = 0;
        document.getElementById('edit_rol').selectedIndex = 0;
        document.getElementsByName('password')[0].value = ''; // Limpiar pass

        // 2. Cambiar la acción a 'create' (IMPORTANTE)
        document.getElementsByName('action')[0].value = 'create';

        // 3. Cambiar título visualmente
        document.querySelector('#modalEditar h2').innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Usuario';

        // 4. Mostrar modal
        document.getElementById('modalEditar').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    // FUNCIÓN 2: ABRIR PARA EDITAR (CON DATOS)
    function abrirModal(id, nombre, usuario, idCliente, idRol) {
        // Rellenar datos
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nombre').value = nombre;
        document.getElementById('edit_usuario').value = usuario;
        document.getElementById('edit_cliente').value = idCliente;
        document.getElementById('edit_rol').value = idRol;
        
        // Asegurar que la acción sea 'edit' y el título sea 'Editar'
        document.getElementsByName('action')[0].value = 'edit';
        document.querySelector('#modalEditar h2').innerHTML = '<i class="fas fa-user-edit"></i> Editar Usuario';
        
        document.getElementById('modalEditar').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }


    // FUNCIÓN 1: ABRIR PARA CREAR (LIMPIO)
    function abrirModalCrear() {
        // 1. Limpiar todos los campos inputs
        document.getElementById('edit_id').value = '';
        document.getElementById('edit_nombre').value = '';
        document.getElementById('edit_usuario').value = '';
        document.getElementById('edit_cliente').selectedIndex = 0;
        document.getElementById('edit_rol').selectedIndex = 0;
        document.getElementsByName('password')[0].value = ''; // Limpiar pass

        // 2. Cambiar la acción a 'create' (IMPORTANTE)
        document.getElementsByName('action')[0].value = 'create';

        // 3. Cambiar título visualmente
        document.querySelector('#modalEditar h2').innerHTML = '<i class="fas fa-user-plus"></i> Nuevo Usuario';

        // 4. Mostrar modal
        document.getElementById('modalEditar').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    // FUNCIÓN 2: ABRIR PARA EDITAR (CON DATOS)
    function abrirModal(id, nombre, usuario, idCliente, idRol) {
        // Rellenar datos
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nombre').value = nombre;
        document.getElementById('edit_usuario').value = usuario;
        document.getElementById('edit_cliente').value = idCliente;
        document.getElementById('edit_rol').value = idRol;
        
        // Asegurar que la acción sea 'edit' y el título sea 'Editar'
        document.getElementsByName('action')[0].value = 'edit';
        document.querySelector('#modalEditar h2').innerHTML = '<i class="fas fa-user-edit"></i> Editar Usuario';
        
        document.getElementById('modalEditar').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }


    </script>

</body>
</html>