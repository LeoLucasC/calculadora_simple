<?php
session_start();
include 'includes/db.php';

// SEGURIDAD: Solo ADMIN
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: index.html");
    exit();
}

// --- 1. RECUPERAR MENSAJE DE LA SESIÓN (Si existe) ---
$mensaje = '';
$tipo_mensaje = '';

if (isset($_SESSION['flash_mensaje'])) {
    $mensaje = $_SESSION['flash_mensaje'];
    $tipo_mensaje = $_SESSION['flash_tipo'];
    // Borramos el mensaje para que no salga otra vez al recargar
    unset($_SESSION['flash_mensaje']);
    unset($_SESSION['flash_tipo']);
}

// --- 2. LÓGICA DE ACTUALIZACIÓN (POST) ---
$tablas_permitidas = [
    'tipos_grupo'       => 'id_tipo_grupo',
    'tipos_ubicacion'   => 'id_tipo_ubicacion',
    'roles'             => 'id_rol'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $tabla = $_POST['tabla'];
    $descripcion = strtoupper($conn->real_escape_string($_POST['descripcion'])); 
    
    if (array_key_exists($tabla, $tablas_permitidas)) {
        $campo_id = $tablas_permitidas[$tabla];

        // CREAR
        if ($_POST['action'] == 'crear') {
            $sql = "INSERT INTO $tabla (descripcion) VALUES ('$descripcion')";
            if ($conn->query($sql)) {
                $_SESSION['flash_mensaje'] = "Registro agregado correctamente.";
                $_SESSION['flash_tipo'] = "success";
            } else {
                $_SESSION['flash_mensaje'] = "Error: " . $conn->error;
                $_SESSION['flash_tipo'] = "error";
            }
        }
        // EDITAR
        elseif ($_POST['action'] == 'editar') {
            $id = intval($_POST['id']);
            $sql = "UPDATE $tabla SET descripcion = '$descripcion' WHERE $campo_id = $id";
            if ($conn->query($sql)) {
                $_SESSION['flash_mensaje'] = "Registro actualizado.";
                $_SESSION['flash_tipo'] = "success";
            } else {
                $_SESSION['flash_mensaje'] = "Error: " . $conn->error;
                $_SESSION['flash_tipo'] = "error";
            }
        }
    }
    // ¡LA CLAVE! Redireccionar para limpiar el formulario
    header("Location: admin_configuracion.php");
    exit();
}

// --- 3. LÓGICA DE ELIMINAR (GET) ---
if (isset($_GET['delete_tipo'])) {
    $tabla = $_GET['tabla'];
    $id = intval($_GET['id']);

    if (array_key_exists($tabla, $tablas_permitidas)) {
        $campo_id = $tablas_permitidas[$tabla];
        
        if ($conn->query("DELETE FROM $tabla WHERE $campo_id = $id")) {
            $_SESSION['flash_mensaje'] = "Registro eliminado permanentemente.";
            $_SESSION['flash_tipo'] = "success";
        } else {
            $_SESSION['flash_mensaje'] = "No se puede eliminar: Registro en uso.";
            $_SESSION['flash_tipo'] = "error";
        }
    }
    // Redireccionar también al borrar
    header("Location: admin_configuracion.php");
    exit();
}

// --- 4. CONSULTAS DE DATOS ---
$tipos_grupo = $conn->query("SELECT * FROM tipos_grupo ORDER BY descripcion")->fetch_all(MYSQLI_ASSOC);
$tipos_ubicacion = $conn->query("SELECT * FROM tipos_ubicacion ORDER BY descripcion")->fetch_all(MYSQLI_ASSOC);
$roles = $conn->query("SELECT * FROM roles ORDER BY id_rol")->fetch_all(MYSQLI_ASSOC);

// Estadísticas
$stats = [
    'total_grupo' => count($tipos_grupo),
    'total_ubicacion' => count($tipos_ubicacion),
    'total_roles' => count($roles)
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Configuración del Sistema</title>
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
            grid-template-columns: repeat(3, 1fr);
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

        /* Tabs de configuración */
        .config-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 12px 24px;
            background: white;
            border: 1px solid #e9eef2;
            border-radius: 10px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-btn i {
            font-size: 1.1rem;
        }

        .tab-btn:hover {
            border-color: #023675;
            color: #023675;
        }

        .tab-btn.active {
            background: #023675;
            color: white;
            border-color: #023675;
        }

        /* Paneles */
        .config-panel {
            display: none;
            background: white;
            border-radius: 20px;
            padding: 30px;
            border: 1px solid #e9eef2;
            margin-bottom: 30px;
        }

        .config-panel.active {
            display: block;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .panel-header h2 {
            font-size: 1.3rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Tablas de configuración */
        .table-mini {
            width: 100%;
            border-collapse: collapse;
        }

        .table-mini th {
            text-align: left;
            padding: 12px 10px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            border-bottom: 2px solid #e9eef2;
        }

        .table-mini td {
            padding: 15px 10px;
            border-bottom: 1px solid #e9eef2;
        }

        .table-mini tr:hover td {
            background: #f8fafc;
        }

        .id-badge {
            background: #e9eef2;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
        }

        .action-buttons-small {
            display: flex;
            gap: 5px;
        }

        .btn-icon-small {
            width: 28px;
            height: 28px;
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
        }

        .btn-icon-small:hover {
            background: #f8fafc;
        }

        .btn-icon-small.edit:hover {
            border-color: #023675;
            color: #023675;
        }

        .btn-icon-small.delete:hover {
            border-color: #ef4444;
            color: #ef4444;
        }

        /* Formulario de nuevo tipo */
        .add-form {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .add-form h3 {
            font-size: 1rem;
            margin-bottom: 15px;
            color: #0f172a;
        }

        .form-row {
            display: flex;
            gap: 10px;
        }

        .form-row input {
            flex: 1;
            padding: 10px 12px;
            border: 1px solid #e9eef2;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .form-row input:focus {
            outline: none;
            border-color: #023675;
        }

        .btn-add {
            background: #023675;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-add:hover {
            background: #0345a0;
        }

        /* Configuración general */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .setting-item {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
        }

        .setting-item label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #0f172a;
        }

        .setting-item input, .setting-item select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e9eef2;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .setting-item input:focus, .setting-item select:focus {
            outline: none;
            border-color: #023675;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
        }

        /* Actividad reciente */
        .activity-list {
            margin-top: 20px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #e9eef2;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: rgba(2, 54, 117, 0.08);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #023675;
        }

        .activity-details {
            flex: 1;
        }

        .activity-details strong {
            display: block;
            margin-bottom: 3px;
            color: #0f172a;
        }

        .activity-details small {
            color: #64748b;
            font-size: 0.8rem;
        }

        .activity-time {
            color: #94a3b8;
            font-size: 0.8rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .settings-grid {
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
            
            .config-tabs {
                flex-direction: column;
            }
            
            .form-row {
                flex-direction: column;
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
                <i class="fas fa-cog"></i>
                Configuración del Sistema
            </h1>
            <div class="breadcrumb">
                <a href="dashboard_admin.php">Dashboard</a> / Configuración
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
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h4>Roles</h4>
                    <div class="number"><?php echo $stats['total_roles']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="stat-info">
                    <h4>Tipos Grupo</h4>
                    <div class="number"><?php echo $stats['total_grupo']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-map-pin"></i>
                </div>
                <div class="stat-info">
                    <h4>Tipos Ubicación</h4>
                    <div class="number"><?php echo $stats['total_ubicacion']; ?></div>
                </div>
            </div>
        </div>

        <!-- Tabs de configuración -->
        <div class="config-tabs">
            <button class="tab-btn active" onclick="cambiarTab('grupo')">
                <i class="fas fa-layer-group"></i>
                Tipos Grupo
            </button>
            <button class="tab-btn" onclick="cambiarTab('ubicacion')">
                <i class="fas fa-map-marker-alt"></i>
                Tipos Ubicación
            </button>
            <button class="tab-btn" onclick="cambiarTab('roles')">
                <i class="fas fa-user-tag"></i>
                Roles
            </button>
        </div>

        <!-- Panel Tipos Grupo -->
        <div class="config-panel active" id="panel-grupo">
            <div class="panel-header">
                <h2><i class="fas fa-layer-group" style="color: #023675;"></i> Tipos de Grupo Operativo</h2>
            </div>
            
            <table class="table-mini">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tipos_grupo as $tipo): ?>
                    <tr>
                        <td><span class="id-badge"><?php echo $tipo['id_tipo_grupo']; ?></span></td>
                        <td><?php echo htmlspecialchars($tipo['descripcion']); ?></td>
                        <td>
                            <div class="action-buttons-small">
                                <a href="#edit" class="btn-icon-small edit" onclick="editarTipo('grupo', <?php echo $tipo['id_tipo_grupo']; ?>, '<?php echo $tipo['descripcion']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete_tipo&tabla=tipos_grupo&id=<?php echo $tipo['id_tipo_grupo']; ?>" 
                                   class="btn-icon-small delete" 
                                   onclick="return confirm('¿Eliminar este tipo de grupo?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
        </div>

        <!-- Panel Tipos Ubicación -->
        <div class="config-panel" id="panel-ubicacion">
            <div class="panel-header">
                <h2><i class="fas fa-map-marker-alt" style="color: #023675;"></i> Tipos de Ubicación</h2>
            </div>
            
            <table class="table-mini">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tipos_ubicacion as $tipo): ?>
                    <tr>
                        <td><span class="id-badge"><?php echo $tipo['id_tipo_ubicacion']; ?></span></td>
                        <td><?php echo htmlspecialchars($tipo['descripcion']); ?></td>
                        <td>
                            <div class="action-buttons-small">
                                <a href="#edit" class="btn-icon-small edit" onclick="editarTipo('ubicacion', <?php echo $tipo['id_tipo_ubicacion']; ?>, '<?php echo $tipo['descripcion']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete_tipo&tabla=tipos_ubicacion&id=<?php echo $tipo['id_tipo_ubicacion']; ?>" 
                                   class="btn-icon-small delete" 
                                   onclick="return confirm('¿Eliminar este tipo de ubicación?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="add-form">
                <h3><i class="fas fa-plus-circle" style="color: #023675;"></i> Agregar nuevo tipo</h3>
                <form method="POST" class="form-row">
                    <input type="hidden" name="action" value="crear">
                    <input type="hidden" name="tabla" value="tipos_ubicacion">
                    <input type="text" name="descripcion" placeholder="Ej: ZONA, BOCAMINA" required>
                    <button type="submit" class="btn-add">Agregar</button>
                </form>
            </div>
        </div>

        <!-- Panel Roles -->
        <div class="config-panel" id="panel-roles">
            <div class="panel-header">
                <h2><i class="fas fa-user-tag" style="color: #023675;"></i> Roles del Sistema</h2>
            </div>
            
            <table class="table-mini">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $rol): ?>
                    <tr>
                        <td><span class="id-badge"><?php echo $rol['id_rol']; ?></span></td>
                        <td><?php echo htmlspecialchars($rol['descripcion']); ?></td>
                        <td>
                            <div class="action-buttons-small">
                                <a href="#edit" class="btn-icon-small edit" onclick="editarTipo('roles', <?php echo $rol['id_rol']; ?>, '<?php echo $rol['descripcion']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($rol['id_rol'] > 4): // No eliminar roles por defecto ?>
                                <a href="?delete_tipo&tabla=roles&id=<?php echo $rol['id_rol']; ?>" 
                                   class="btn-icon-small delete" 
                                   onclick="return confirm('¿Eliminar este rol?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Formulario de agregar rol ELIMINADO -->
        </div>
    </div>

    <!-- Modal Editar -->
    <div class="modal" id="modalEditar">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2>Editar registro</h2>
                <button class="close-modal" onclick="cerrarModalEditar()">&times;</button>
            </div>
            
            <form method="POST" id="formEditar">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="tabla" id="edit_tabla">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" id="edit_descripcion" required>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Actualizar
                    </button>
                    <button type="button" class="btn-cancel" onclick="cerrarModalEditar()">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function cambiarTab(tab) {
            // Desactivar todos los tabs
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Ocultar todos los paneles
            document.querySelectorAll('.config-panel').forEach(panel => {
                panel.classList.remove('active');
            });
            
            // Activar tab clickeado
            event.target.classList.add('active');
            
            // Activar panel seleccionado
            document.getElementById('panel-' + tab).classList.add('active');
        }
        
        function editarTipo(tabla, id, descripcion) {
            document.getElementById('edit_tabla').value = 'tipos_' + tabla;
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_descripcion').value = descripcion;
            document.getElementById('modalEditar').classList.add('active');
        }
        
        function cerrarModalEditar() {
            document.getElementById('modalEditar').classList.remove('active');
        }
        
        // Cerrar modal con Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalEditar();
            }
        });
        
        // Cerrar modal al hacer clic fuera
        window.onclick = function(event) {
            let modal = document.getElementById('modalEditar');
            if (event.target == modal) {
                cerrarModalEditar();
            }
        }
    </script>

    <style>
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
            padding: 30px;
            animation: modalSlide 0.3s ease;
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
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #0f172a;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e9eef2;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #023675;
        }
        
        .modal-footer {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn-submit {
            flex: 1;
            background: #023675;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn-cancel {
            flex: 1;
            background: white;
            border: 1px solid #e9eef2;
            color: #64748b;
            padding: 10px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>

</body>
</html>