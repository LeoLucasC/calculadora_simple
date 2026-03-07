<?php
session_start();
include 'includes/db.php';
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) { header("Location: index.html"); exit(); }

// Lógica para listar archivos en la carpeta /backups
$directorio = 'backups/';
if (!is_dir($directorio)) { mkdir($directorio, 0777, true); }
$archivos = array_diff(scandir($directorio), array('..', '.'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>KunturVR | Gestión de Respaldos</title>
    </head>
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
<body>
    <?php include 'includes/navbar_admin.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-history"></i> Copias de Seguridad</h1>
            <p>Respalda la base de datos y gestiona programaciones automáticas.</p>
        </div>

        <div class="stats-container">
            <div class="stat-card" onclick="location.href='includes/ejecutar_backup.php'" style="cursor:pointer; border: 2px solid #023675;">
                <div class="stat-icon"><i class="fas fa-file-export"></i></div>
                <div class="stat-info">
                    <h4>Manual</h4>
                    <div class="number" style="font-size:1.2rem;">Respaldar Ahora</div>
                </div>
            </div>
        </div>

        <div class="config-panel active">
            <h2><i class="fas fa-clock"></i> Programación Automática</h2>
            <form action="includes/guardar_config_backup.php" method="POST" style="margin-top:20px;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Hora de ejecución diaria:</label>
                        <input type="time" name="hora_backup" class="form-control" value="00:00">
                    </div>
                    <button type="submit" class="btn-add">Guardar Programación</button>
                </div>
                <small style="color: #64748b;">Nota: El servidor debe tener configurado un CRON JOB para ejecutar esta tarea.</small>
            </form>
        </div>

        <div class="config-panel active">
            <h2><i class="fas fa-archive"></i> Historial de Respaldos</h2>
            <table class="table-mini">
                <thead>
                    <tr>
                        <th>Nombre del Archivo</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($archivos as $archivo): ?>
                    <tr>
                        <td><?= $archivo ?></td>
                        <td><?= date("d/m/Y H:i", filemtime($directorio.$archivo)) ?></td>
                        <td>
                            <a href="backups/<?= $archivo ?>" class="btn-icon-small" download><i class="fas fa-download"></i></a>
                            <a href="borrar_backup.php?file=<?= $archivo ?>" class="btn-icon-small delete"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>