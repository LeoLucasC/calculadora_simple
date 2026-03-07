<?php
session_start();
include 'includes/db.php';

// SEGURIDAD: Solo ADMIN
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: index.html");
    exit();
}

// --- LÓGICA DE MENSAJES FLASH (Para que no se duplique al refrescar) ---
$mensaje = '';
$tipo_mensaje = '';

// Si venimos de una redirección con mensaje guardado, lo recuperamos y borramos
if (isset($_SESSION['flash_mensaje'])) {
    $mensaje = $_SESSION['flash_mensaje'];
    $tipo_mensaje = $_SESSION['flash_tipo'];
    unset($_SESSION['flash_mensaje']);
    unset($_SESSION['flash_tipo']);
}

// --- PROCESAR ACCIONES (CRUD) ---
// --- PROCESAR ACCIONES (CRUD) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $razon_social = $conn->real_escape_string($_POST['razon_social']);
    $ruc = $conn->real_escape_string($_POST['ruc']);
    $direccion = $conn->real_escape_string($_POST['direccion']);
    $telefono = $conn->real_escape_string($_POST['telefono']);
    $email = $conn->real_escape_string($_POST['email']);

    // NUEVO: Lógica para subir el Logo
    $logo_sql_crear = "NULL"; 
    $logo_sql_update = ""; 
    
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $nombre_logo = 'logo_empresa_' . time() . '_' . rand(100,999) . '.' . strtolower($ext);
        $ruta_logo = 'uploads/' . $nombre_logo;
        
        // Crear carpeta si por alguna razón no existe
        if (!file_exists('uploads/')) { mkdir('uploads/', 0777, true); }
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $ruta_logo)) {
            $logo_sql_crear = "'$ruta_logo'";
            $logo_sql_update = ", logo_url='$ruta_logo'"; // Solo actualiza si suben uno nuevo
        }
    }

    // ACCIÓN: CREAR
    if ($_POST['action'] == 'crear') {
        $sql = "INSERT INTO clientes (razon_social, ruc, direccion, telefono, email, logo_url, estado) 
                VALUES ('$razon_social', '$ruc', '$direccion', '$telefono', '$email', $logo_sql_crear, 1)";
        
        if ($conn->query($sql)) {
            $_SESSION['flash_mensaje'] = "Cliente creado exitosamente";
            $_SESSION['flash_tipo'] = "success";
        } else {
            $_SESSION['flash_mensaje'] = "Error al crear: " . $conn->error;
            $_SESSION['flash_tipo'] = "error";
        }
        header("Location: admin_clientes.php");
        exit();
    }

    // ACCIÓN: EDITAR
    if ($_POST['action'] == 'editar') {
        $id_cliente = intval($_POST['id_cliente']);
        $sql = "UPDATE clientes SET razon_social='$razon_social', ruc='$ruc', direccion='$direccion', telefono='$telefono', email='$email' $logo_sql_update WHERE id_cliente=$id_cliente";
        
        if ($conn->query($sql)) {
            $_SESSION['flash_mensaje'] = "Cliente actualizado exitosamente";
            $_SESSION['flash_tipo'] = "success";
        } else {
            $_SESSION['flash_mensaje'] = "Error al actualizar: " . $conn->error;
            $_SESSION['flash_tipo'] = "error";
        }
        header("Location: admin_clientes.php");
        exit();
    }
}

// ACCIÓN: TOGGLE ESTADO
if (isset($_GET['toggle']) && isset($_GET['id']) && isset($_GET['estado'])) {
    $id = intval($_GET['id']);
    $nuevo_estado = ($_GET['estado'] == 1) ? 0 : 1;
    $conn->query("UPDATE clientes SET estado = $nuevo_estado WHERE id_cliente = $id");
    header("Location: admin_clientes.php");
    exit();
}

// ACCIÓN: ELIMINAR
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if($conn->query("DELETE FROM clientes WHERE id_cliente = $id")){
        $_SESSION['flash_mensaje'] = "Cliente eliminado permanentemente";
        $_SESSION['flash_tipo'] = "success";
    } else {
        $_SESSION['flash_mensaje'] = "No se puede eliminar. Tiene usuarios asociados.";
        $_SESSION['flash_tipo'] = "error";
    }
    header("Location: admin_clientes.php");
    exit();
}

// --- OBTENER LISTA DE CLIENTES Y ESTADÍSTICAS ---
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM usuarios WHERE id_cliente = c.id_cliente) as total_usuarios,
        (SELECT COUNT(*) FROM operaciones WHERE id_cliente = c.id_cliente) as total_operaciones
        FROM clientes c 
        ORDER BY c.estado DESC, c.razon_social ASC";

$resultado = $conn->query($sql);

$clientes = [];
$clientes_activos = 0;
$clientes_inactivos = 0;
$total_empresas_global = 0; // <--- AQUÍ INICIALIZAMOS LA VARIABLE QUE FALTABA

while ($row = $resultado->fetch_assoc()) {
    $clientes[] = $row;
    
    // Contar activos/inactivos
    if ($row['estado'] == 1) {
        $clientes_activos++;
    } else {
        $clientes_inactivos++;
    }

    // Sumar las operaciones de este cliente al total global
    // Así la tarjeta "Operaciones Totales" mostrará el dato real
    $total_empresas_global += $row['total_operaciones'];
}

$total_clientes = count($clientes);
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Gestión de Clientes</title>
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
            grid-template-columns: repeat(4, 1fr);
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

        /* Barra de herramientas */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-primary {
            background: #023675;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            border: 1px solid #023675;
        }

        .btn-primary:hover {
            background: #0345a0;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(2, 54, 117, 0.2);
        }

        .btn-secondary {
            background: white;
            color: #023675;
            border: 1px solid #023675;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #f0f7ff;
            transform: translateY(-2px);
        }

        .search-box {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid #e9eef2;
            border-radius: 10px;
            padding: 0 15px;
            width: 300px;
        }

        .search-box i {
            color: #94a3b8;
        }

        .search-box input {
            border: none;
            padding: 12px 10px;
            width: 100%;
            outline: none;
            font-size: 0.95rem;
        }

        /* Tabla de clientes */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            border: 1px solid #e9eef2;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px 10px;
            color: #64748b;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e9eef2;
        }

        td {
            padding: 20px 10px;
            border-bottom: 1px solid #e9eef2;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .cliente-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cliente-avatar {
            width: 45px;
            height: 45px;
            background: rgba(2, 54, 117, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            color: #023675;
        }

        .cliente-detalle h4 {
            font-weight: 600;
            margin-bottom: 3px;
            color: #0f172a;
        }

        .cliente-detalle small {
            color: #64748b;
            font-size: 0.8rem;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge.activo {
            background: #d1fae5;
            color: #065f46;
        }

        .badge.inactivo {
            background: #fee2e2;
            color: #b91c1c;
        }

        .badge.empresa {
            background: #e0f2fe;
            color: #0369a1;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
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

        .btn-icon:hover {
            background: #f8fafc;
            transform: translateY(-2px);
        }

        .btn-icon.edit:hover {
            border-color: #023675;
            color: #023675;
        }

        .btn-icon.toggle:hover {
            border-color: #f59e0b;
            color: #f59e0b;
        }

        .btn-icon.delete:hover {
            border-color: #ef4444;
            color: #ef4444;
        }

        /* Modal */
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
            width: 100%;
            max-width: 500px;
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

        .modal-header h2 {
            font-size: 1.5rem;
            color: #0f172a;
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
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e9eef2;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #023675;
            box-shadow: 0 0 0 3px rgba(2, 54, 117, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .modal-footer {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .modal-footer button {
            flex: 1;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-submit {
            background: #023675;
            color: white;
            border: none;
        }

        .btn-cancel {
            background: white;
            border: 1px solid #e9eef2;
            color: #64748b;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
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
            
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
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
                <i class="fas fa-building"></i>
                Gestión de Clientes
            </h1>
            <div class="breadcrumb">
                <a href="dashboard_admin.php">Dashboard</a> / Clientes
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
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-info">
                    <h4>Total Clientes</h4>
                    <div class="number"><?php echo $total_clientes; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h4>Clientes Activos</h4>
                    <div class="number"><?php echo $clientes_activos; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h4>Clientes Inactivos</h4>
                    <div class="number"><?php echo $clientes_inactivos; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hard-hat"></i>
                </div>
                <div class="stat-info">
                    <h4>Operaciones Totales</h4>
                    <div class="number"><?php echo $total_empresas_global; ?></div>
                </div>
            </div>
        </div>

        <!-- Barra de herramientas -->
        <div class="toolbar">
            <button class="btn-primary" onclick="abrirModalCrear()">
                <i class="fas fa-plus"></i>
                Nueva Empresa
            </button>
            
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="buscarCliente" placeholder="Escribe RUC o Razón Social...">
        </div>
        </div>

        <!-- Tabla de clientes -->
        <div class="table-container">
            <table id="tablaClientes">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>RUC</th>
                        <th>Contacto</th>
                        <th>Estadísticas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td>
                            <div class="cliente-info">
                                <div class="cliente-avatar" style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                    <?php if (!empty($cliente['logo_url']) && file_exists($cliente['logo_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($cliente['logo_url']); ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo substr($cliente['razon_social'], 0, 2); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="cliente-detalle">
                                    <h4><?php echo htmlspecialchars($cliente['razon_social']); ?></h4>
                                    <small><i class="fas fa-calendar"></i> Creado: <?php echo date('d/m/Y', strtotime($cliente['fecha_creacion'])); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><strong><?php echo htmlspecialchars($cliente['ruc']); ?></strong></td>
                        <td>
                            <?php if ($cliente['email']): ?>
                                <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($cliente['email']); ?></div>
                            <?php endif; ?>
                            <?php if ($cliente['telefono']): ?>
                                <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($cliente['telefono']); ?></div>
                            <?php endif; ?>
                            <?php if ($cliente['direccion']): ?>
                                <small><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($cliente['direccion']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 10px;">
                                <span class="badge empresa">
                                    <i class="fas fa-users"></i> <?php echo $cliente['total_usuarios']; ?> usuarios
                                </span>
                                <span class="badge empresa">
                                    <i class="fas fa-hard-hat"></i> <?php echo $cliente['total_operaciones']; ?> ops
                                </span>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?php echo $cliente['estado'] == 1 ? 'activo' : 'inactivo'; ?>">
                                <i class="fas <?php echo $cliente['estado'] == 1 ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                <?php echo $cliente['estado'] == 1 ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon edit" title="Editar" style="background: none; border: none; cursor: pointer; color: #00d4ff;"
                                    onclick="abrirModalEditar(
                                        '<?php echo $cliente['id_cliente']; ?>',
                                        '<?php echo htmlspecialchars($cliente['razon_social'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($cliente['ruc'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($cliente['direccion'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($cliente['telefono'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($cliente['email'], ENT_QUOTES); ?>'
                                    )">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?toggle=1&id=<?php echo $cliente['id_cliente']; ?>&estado=<?php echo $cliente['estado']; ?>" 
                                   class="btn-icon toggle" 
                                   title="<?php echo $cliente['estado'] == 1 ? 'Desactivar' : 'Activar'; ?>"
                                   onclick="return confirm('¿Estás seguro de <?php echo $cliente['estado'] == 1 ? 'desactivar' : 'activar'; ?> este cliente?')">
                                    <i class="fas <?php echo $cliente['estado'] == 1 ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                </a>
                                <a href="?delete&id=<?php echo $cliente['id_cliente']; ?>" 
                                   class="btn-icon delete" 
                                   title="Eliminar"
                                   onclick="return confirm('¿Eliminar permanentemente este cliente? Esta acción no se puede deshacer.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Crear/Editar Cliente -->
    <div class="modal" id="modalCliente" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
        <div class="modal-content" style="background: #0b1624; padding: 30px; border-radius: 10px; width: 90%; max-width: 500px; border: 1px solid #00d4ff; position: relative; margin: 5% auto;">
            <div class="modal-header">
                <h2 id="modalTitle" style="color: #00d4ff; margin-top: 0;">Nueva Empresa</h2>
                <button class="close-modal" onclick="cerrarModal()" style="position: absolute; right: 15px; top: 15px; background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
            </div>
            
            <form method="POST" action="admin_clientes.php" enctype="multipart/form-data">
                <input type="hidden" name="action" id="modalAction" value="crear">
                <input type="hidden" name="id_cliente" id="modalId">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #00d4ff; display: block; margin-bottom: 5px;">Logo de la Empresa (Opcional)</label>
                    <input type="file" name="logo" id="modalLogo" accept="image/*" style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #444; color: white;">
                </div>


                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #00d4ff; display: block; margin-bottom: 5px;">Razón Social *</label>
                    <input type="text" name="razon_social" id="modalRazon" required style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #444; color: white;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #00d4ff; display: block; margin-bottom: 5px;">RUC *</label>
                    <input type="text" name="ruc" id="modalRuc" required style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #444; color: white;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #00d4ff; display: block; margin-bottom: 5px;">Dirección</label>
                    <input type="text" name="direccion" id="modalDireccion" style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #444; color: white;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #00d4ff; display: block; margin-bottom: 5px;">Teléfono</label>
                    <input type="text" name="telefono" id="modalTelefono" style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #444; color: white;">
                </div>
                    
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="color: #00d4ff; display: block; margin-bottom: 5px;">Email</label>
                    <input type="email" name="email" id="modalEmail" style="width: 100%; padding: 8px; background: rgba(0,0,0,0.5); border: 1px solid #444; color: white;">
                </div>
                
                <div class="modal-footer">
                    <button type="submit" style="background: #00d4ff; border: none; padding: 10px 20px; color: black; font-weight: bold; cursor: pointer;">Guardar</button>
                    <button type="button" onclick="cerrarModal()" style="background: transparent; border: 1px solid white; padding: 10px 20px; color: white; cursor: pointer; margin-left: 10px;">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Abrir modal para CREAR (Limpio)
        function abrirModalCrear() {
            document.getElementById('modalTitle').innerText = 'Nueva Empresa';
            document.getElementById('modalAction').value = 'crear';
            document.getElementById('modalId').value = '';
            document.getElementById('modalLogo').value = '';
            
            document.getElementById('modalRazon').value = '';
            document.getElementById('modalRuc').value = '';
            document.getElementById('modalDireccion').value = '';
            document.getElementById('modalTelefono').value = '';
            document.getElementById('modalEmail').value = '';
            
            document.getElementById('modalCliente').style.display = 'flex';
        }

        // Abrir modal para EDITAR (Rellenar)
        function abrirModalEditar(id, razon, ruc, direccion, telefono, email) {
            document.getElementById('modalTitle').innerText = 'Editar Empresa';
            document.getElementById('modalAction').value = 'editar';
            document.getElementById('modalId').value = id;
            document.getElementById('modalLogo').value = '';
            
            document.getElementById('modalRazon').value = razon;
            document.getElementById('modalRuc').value = ruc;
            document.getElementById('modalDireccion').value = direccion;
            document.getElementById('modalTelefono').value = telefono;
            document.getElementById('modalEmail').value = email;
            
            document.getElementById('modalCliente').style.display = 'flex';
        }
        
        // Cerrar modal
        function cerrarModal() {
            document.getElementById('modalCliente').style.display = 'none';
        }
        
       
        // --- BÚSQUEDA EN TIEMPO REAL (LIVE SEARCH) ---
    document.getElementById('buscarCliente').addEventListener('keyup', function() {
        let input = this.value.toLowerCase().trim();
        let filas = document.querySelectorAll('#tablaClientes tbody tr');
        
        filas.forEach(fila => {
            // Obtenemos todo el texto de la fila (Nombre, RUC, Email, etc.)
            let textoFila = fila.innerText.toLowerCase();
            
            // Si el texto de la fila contiene lo que escribiste, se muestra. Si no, se oculta.
            if (textoFila.includes(input)) {
                fila.style.display = ''; 
            } else {
                fila.style.display = 'none';
            }
        });
    });


    document.addEventListener('DOMContentLoaded', function() {
            // Buscar la alerta
            var alerta = document.querySelector('.alert');
            
            // Si existe una alerta, poner un temporizador
            if (alerta) {
                setTimeout(function() {
                    // Efecto de desvanecimiento
                    alerta.style.transition = "opacity 0.5s ease";
                    alerta.style.opacity = "0";
                    
                    // Eliminar del DOM después de que termine la transición
                    setTimeout(function() {
                        alerta.remove();
                    }, 500); 
                }, 3000); // 3000 milisegundos = 3 segundos
            }
        });
    </script>

</body>
</html>