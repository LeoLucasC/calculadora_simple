<?php
session_start();
include 'includes/db.php';

// SEGURIDAD: Solo ADMIN
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) {
    header("Location: index.html");
    exit();
}

// --- MENSAJES FLASH ---
$mensaje = '';
$tipo_mensaje = '';
if (isset($_SESSION['flash_mensaje'])) {
    $mensaje     = $_SESSION['flash_mensaje'];
    $tipo_mensaje = $_SESSION['flash_tipo'];
    unset($_SESSION['flash_mensaje'], $_SESSION['flash_tipo']);
}

// ============================================================
//  POST: CRUD OPERACIONES
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {

    // ---- OPERACIONES ----
    if ($_POST['action'] == 'crear_operacion' || $_POST['action'] == 'editar_operacion') {
        $id_cliente = intval($_POST['id_cliente']);
        $id_tipo    = intval($_POST['id_tipo_explotacion']);
        $nombre     = $conn->real_escape_string($_POST['nombre']);
        $descripcion= $conn->real_escape_string($_POST['descripcion']);

        if ($_POST['action'] == 'crear_operacion') {
            $sql = "INSERT INTO operaciones (id_cliente, id_tipo_explotacion, nombre, descripcion, estado)
                    VALUES ($id_cliente, $id_tipo, '$nombre', '$descripcion', 1)";
            $msg = "Operación creada correctamente.";
        } else {
            $id = intval($_POST['id_operacion']);
            $sql = "UPDATE operaciones SET id_cliente=$id_cliente, id_tipo_explotacion=$id_tipo,
                    nombre='$nombre', descripcion='$descripcion' WHERE id_operacion=$id";
            $msg = "Operación actualizada correctamente.";
        }
        $_SESSION['flash_mensaje'] = $conn->query($sql) ? $msg : "Error SQL: ".$conn->error;
        $_SESSION['flash_tipo']    = $conn->affected_rows >= 0 && !$conn->error ? 'success' : 'error';
        header("Location: admin_operaciones.php?tab=operaciones"); exit();
    }

    // ---- UBICACIONES ----
    if ($_POST['action'] == 'crear_ubicacion' || $_POST['action'] == 'editar_ubicacion') {
        $id_operacion     = intval($_POST['id_operacion_ubi']);
        $id_tipo_ubi      = intval($_POST['id_tipo_ubicacion']);
        $nombre           = $conn->real_escape_string($_POST['nombre_ubi']);
        $descripcion      = $conn->real_escape_string($_POST['descripcion_ubi']);

        if ($_POST['action'] == 'crear_ubicacion') {
            $sql = "INSERT INTO ubicaciones (id_operacion, id_tipo_ubicacion, nombre, descripcion, estado)
                    VALUES ($id_operacion, $id_tipo_ubi, '$nombre', '$descripcion', 1)";
            $msg = "Ubicación creada correctamente.";
        } else {
            $id = intval($_POST['id_ubicacion']);
            $sql = "UPDATE ubicaciones SET id_operacion=$id_operacion, id_tipo_ubicacion=$id_tipo_ubi,
                    nombre='$nombre', descripcion='$descripcion' WHERE id_ubicacion=$id";
            $msg = "Ubicación actualizada correctamente.";
        }
        $_SESSION['flash_mensaje'] = $conn->query($sql) ? $msg : "Error SQL: ".$conn->error;
        $_SESSION['flash_tipo']    = !$conn->error ? 'success' : 'error';
        header("Location: admin_operaciones.php?tab=ubicaciones"); exit();
    }

    // ---- GRUPOS ----
    if ($_POST['action'] == 'crear_grupo' || $_POST['action'] == 'editar_grupo') {
        $id_ubicacion  = intval($_POST['id_ubicacion_grupo']);
        $id_tipo_grupo = intval($_POST['id_tipo_grupo']);
        $nombre        = $conn->real_escape_string($_POST['nombre_grupo']);
        $descripcion   = $conn->real_escape_string($_POST['descripcion_grupo']);

        if ($_POST['action'] == 'crear_grupo') {
            $sql = "INSERT INTO grupos_operativos (id_ubicacion, id_tipo_grupo, nombre, descripcion, estado)
                    VALUES ($id_ubicacion, $id_tipo_grupo, '$nombre', '$descripcion', 1)";
            $msg = "Grupo operativo creado correctamente.";
        } else {
            $id = intval($_POST['id_grupo']);
            $sql = "UPDATE grupos_operativos SET id_ubicacion=$id_ubicacion, id_tipo_grupo=$id_tipo_grupo,
                    nombre='$nombre', descripcion='$descripcion' WHERE id_grupo=$id";
            $msg = "Grupo operativo actualizado correctamente.";
        }
        $_SESSION['flash_mensaje'] = $conn->query($sql) ? $msg : "Error SQL: ".$conn->error;
        $_SESSION['flash_tipo']    = !$conn->error ? 'success' : 'error';
        header("Location: admin_operaciones.php?tab=grupos"); exit();
    }
}

// ============================================================
//  GET: ELIMINAR / TOGGLE
// ============================================================
if (isset($_GET['action'])) {
    $tab = $_GET['tab'] ?? 'ubicaciones';

    // -- Eliminar Operacion --
    if ($_GET['action'] == 'delete_op') {
        $id = intval($_GET['id']);
        $check = $conn->query("SELECT COUNT(*) as t FROM ubicaciones WHERE id_operacion=$id")->fetch_assoc();
        if ($check['t'] > 0) {
            $_SESSION['flash_mensaje'] = "No se puede eliminar: tiene {$check['t']} ubicaciones asociadas.";
            $_SESSION['flash_tipo'] = 'error';
        } else {
            $conn->query("DELETE FROM operaciones WHERE id_operacion=$id");
            $_SESSION['flash_mensaje'] = "Operación eliminada.";
            $_SESSION['flash_tipo'] = 'success';
        }
        header("Location: admin_operaciones.php?tab=operaciones"); exit();
    }

    // -- Toggle Operacion --
    if ($_GET['action'] == 'toggle_op') {
        $id     = intval($_GET['id']);
        $estado = ($_GET['estado'] == 1) ? 0 : 1;
        $conn->query("UPDATE operaciones SET estado=$estado WHERE id_operacion=$id");
        header("Location: admin_operaciones.php?tab=operaciones"); exit();
    }

    // -- Eliminar Ubicacion --
    if ($_GET['action'] == 'delete_ubi') {
        $id = intval($_GET['id']);
        $cg = $conn->query("SELECT COUNT(*) as t FROM grupos_operativos WHERE id_ubicacion=$id")->fetch_assoc();
        $cm = $conn->query("SELECT COUNT(*) as t FROM multimedia WHERE id_ubicacion=$id")->fetch_assoc();
        if ($cg['t'] > 0 || $cm['t'] > 0) {
            $_SESSION['flash_mensaje'] = "No se puede eliminar: tiene {$cg['t']} grupos y {$cm['t']} archivos multimedia asociados.";
            $_SESSION['flash_tipo'] = 'error';
        } else {
            $conn->query("DELETE FROM ubicaciones WHERE id_ubicacion=$id");
            $_SESSION['flash_mensaje'] = "Ubicación eliminada.";
            $_SESSION['flash_tipo'] = 'success';
        }
        header("Location: admin_operaciones.php?tab=ubicaciones"); exit();
    }

    // -- Toggle Ubicacion --
    if ($_GET['action'] == 'toggle_ubi') {
        $id     = intval($_GET['id']);
        $estado = ($_GET['estado'] == 1) ? 0 : 1;
        $conn->query("UPDATE ubicaciones SET estado=$estado WHERE id_ubicacion=$id");
        header("Location: admin_operaciones.php?tab=ubicaciones"); exit();
    }

    // -- Eliminar Grupo --
    if ($_GET['action'] == 'delete_grupo') {
        $id = intval($_GET['id']);
        $cm = $conn->query("SELECT COUNT(*) as t FROM multimedia WHERE id_grupo=$id")->fetch_assoc();
        if ($cm['t'] > 0) {
            $_SESSION['flash_mensaje'] = "No se puede eliminar: tiene {$cm['t']} archivos multimedia asociados.";
            $_SESSION['flash_tipo'] = 'error';
        } else {
            $conn->query("DELETE FROM grupos_operativos WHERE id_grupo=$id");
            $_SESSION['flash_mensaje'] = "Grupo eliminado.";
            $_SESSION['flash_tipo'] = 'success';
        }
        header("Location: admin_operaciones.php?tab=grupos"); exit();
    }

    // -- Toggle Grupo --
    if ($_GET['action'] == 'toggle_grupo') {
        $id     = intval($_GET['id']);
        $estado = ($_GET['estado'] == 1) ? 0 : 1;
        $conn->query("UPDATE grupos_operativos SET estado=$estado WHERE id_grupo=$id");
        header("Location: admin_operaciones.php?tab=grupos"); exit();
    }
}

// ============================================================
//  CARGAR DATOS
// ============================================================

// Selects
$clientes    = $conn->query("SELECT id_cliente, razon_social FROM clientes WHERE estado=1 ORDER BY razon_social");
$tipos_exp   = $conn->query("SELECT id_tipo_explotacion, descripcion FROM tipos_explotacion ORDER BY descripcion");
$tipos_ubi   = $conn->query("SELECT id_tipo_ubicacion, descripcion FROM tipos_ubicacion ORDER BY descripcion");
$tipos_grupo = $conn->query("SELECT id_tipo_grupo, descripcion FROM tipos_grupo ORDER BY descripcion");
$todas_ubi   = $conn->query("SELECT u.id_ubicacion, u.nombre, o.nombre as op_nombre FROM ubicaciones u JOIN operaciones o ON u.id_operacion=o.id_operacion ORDER BY o.nombre, u.nombre");

// Operaciones (solo para datos necesarios en otros selects)
$res_ops = $conn->query("
    SELECT o.*, c.razon_social as cliente_nombre, te.descripcion as tipo_nombre,
           (SELECT COUNT(*) FROM ubicaciones WHERE id_operacion=o.id_operacion) as total_ubi
    FROM operaciones o
    JOIN clientes c ON o.id_cliente=c.id_cliente
    JOIN tipos_explotacion te ON o.id_tipo_explotacion=te.id_tipo_explotacion
    ORDER BY o.estado DESC, o.nombre ASC
");
$data_ops = [];
while ($r = $res_ops->fetch_assoc()) {
    $data_ops[] = $r;
}

// Ubicaciones
$res_ubis = $conn->query("
    SELECT u.*, o.nombre as op_nombre, tu.descripcion as tipo_nombre,
           (SELECT COUNT(*) FROM grupos_operativos WHERE id_ubicacion=u.id_ubicacion) as total_grupos,
           (SELECT COUNT(*) FROM multimedia WHERE id_ubicacion=u.id_ubicacion) as total_media
    FROM ubicaciones u
    JOIN operaciones o ON u.id_operacion=o.id_operacion
    JOIN tipos_ubicacion tu ON u.id_tipo_ubicacion=tu.id_tipo_ubicacion
    ORDER BY u.estado DESC, o.nombre, u.nombre
");
$data_ubis = [];
$total_ubis = $activas_ubis = 0;
while ($r = $res_ubis->fetch_assoc()) {
    $data_ubis[] = $r;
    $total_ubis++;
    if ($r['estado'] == 1) $activas_ubis++;
}

// Grupos
$res_grupos = $conn->query("
    SELECT g.*, u.nombre as ubi_nombre, tg.descripcion as tipo_nombre,
           (SELECT COUNT(*) FROM multimedia WHERE id_grupo=g.id_grupo) as total_media
    FROM grupos_operativos g
    JOIN ubicaciones u ON g.id_ubicacion=u.id_ubicacion
    JOIN tipos_grupo tg ON g.id_tipo_grupo=tg.id_tipo_grupo
    ORDER BY g.estado DESC, u.nombre, g.nombre
");
$data_grupos = [];
$total_grupos = $activos_grupos = 0;
while ($r = $res_grupos->fetch_assoc()) {
    $data_grupos[] = $r;
    $total_grupos++;
    if ($r['estado'] == 1) $activos_grupos++;
}

// Historial general
$res_hist = $conn->query("
    SELECT m.id_multimedia, m.tipo_archivo, m.es_360, m.url_archivo, m.fecha_hora, m.observaciones,
           u_usr.nombre as usuario_nombre, rol.descripcion as rol_nombre,
           ubi.nombre as ubicacion_nombre,
           grp.nombre as grupo_nombre,
           op.nombre as operacion_nombre,
           cli.razon_social as cliente_nombre
    FROM multimedia m
    JOIN usuarios u_usr ON m.id_usuario = u_usr.id_usuario
    JOIN roles rol ON u_usr.id_rol = rol.id_rol
    JOIN ubicaciones ubi ON m.id_ubicacion = ubi.id_ubicacion
    JOIN grupos_operativos grp ON m.id_grupo = grp.id_grupo
    JOIN operaciones op ON ubi.id_operacion = op.id_operacion
    JOIN clientes cli ON op.id_cliente = cli.id_cliente
    ORDER BY m.fecha_hora DESC
    LIMIT 200
");
$data_hist = [];
while ($r = $res_hist->fetch_assoc()) $data_hist[] = $r;

// Tab activa
$active_tab = $_GET['tab'] ?? 'ubicaciones';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Gestión de Operaciones</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #1e293b;
            display: flex;
            min-height: 100vh;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #023675 0%, #0345a0 100%);
            color: white;
            padding: 30px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 20px rgba(2,54,117,0.2);
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .logo-container { padding: 0 24px 30px; border-bottom: 1px solid rgba(255,255,255,0.15); margin-bottom: 20px; }
        .logo { font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg,#fff,#a5d8ff); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .logo span { font-size: .85rem; display: block; font-weight: 400; opacity: .7; margin-top: 5px; -webkit-text-fill-color: rgba(255,255,255,.7); }
        .user-info { padding: 0 24px 20px; border-bottom: 1px solid rgba(255,255,255,.15); }
        .user-name { font-weight: 600; font-size: 1.1rem; margin-bottom: 4px; }
        .user-role { font-size: .85rem; opacity: .7; }
        .menu { flex: 1; padding: 20px 0; }
        .menu-item { padding: 12px 24px; margin: 4px 8px; border-radius: 8px; color: rgba(255,255,255,.8); transition: all .3s; cursor: pointer; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .menu-item i { width: 20px; font-size: 1.1rem; }
        .menu-item:hover { background: rgba(255,255,255,.15); color: white; transform: translateX(4px); }
        .menu-item.active { background: white; color: #023675; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,.15); }
        .menu-item.active i { color: #023675; }
        .logout-btn { margin: 20px 24px; padding: 12px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); border-radius: 8px; color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all .3s; }
        .logout-btn:hover { background: rgba(255,255,255,.2); }

        /* ===== MAIN ===== */
        .main-content { flex: 1; padding: 30px 40px; overflow-y: auto; }

        .page-header { margin-bottom: 25px; background: white; padding: 22px 30px; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,.04); border: 1px solid #e9eef2; display: flex; align-items: center; justify-content: space-between; }
        .page-header h1 { font-size: 1.8rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 12px; }
        .page-header h1 i { color: #023675; }
        .breadcrumb { color: #64748b; font-size: .9rem; margin-top: 6px; }
        .breadcrumb a { color: #023675; text-decoration: none; }

        /* ===== ALERT ===== */
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; animation: slideDown .3s ease; font-size: .95rem; }
        .alert.success { background: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
        .alert.error   { background: #fee2e2; border-left: 4px solid #ef4444; color: #b91c1c; }
        @keyframes slideDown { from { opacity:0; transform:translateY(-10px); } to { opacity:1; transform:translateY(0); } }

        /* ===== STATS ===== */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 14px; padding: 18px 20px; border: 1px solid #e9eef2; display: flex; align-items: center; gap: 14px; }
        .stat-icon { width: 48px; height: 48px; background: rgba(2,54,117,.08); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .stat-icon i { font-size: 1.5rem; color: #023675; }
        .stat-info h4 { font-size: .8rem; color: #64748b; font-weight: 400; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .4px; }
        .stat-info .number { font-size: 1.6rem; font-weight: 700; color: #0f172a; line-height: 1; }

        /* ===== TABS ===== */
        .tabs-nav { display: flex; gap: 4px; background: white; border-radius: 14px; padding: 6px; margin-bottom: 24px; border: 1px solid #e9eef2; width: fit-content; }
        .tab-btn { padding: 10px 22px; border-radius: 10px; border: none; background: none; font-weight: 500; font-size: .9rem; color: #64748b; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all .25s; white-space: nowrap; }
        .tab-btn i { font-size: .95rem; }
        .tab-btn:hover { background: #f1f5f9; color: #1e293b; }
        .tab-btn.active { background: #023675; color: white; box-shadow: 0 4px 12px rgba(2,54,117,.25); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* ===== TOOLBAR ===== */
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; gap: 12px; flex-wrap: wrap; }
        .toolbar-left { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn-primary { background: #023675; color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: .9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all .3s; }
        .btn-primary:hover { background: #0345a0; transform: translateY(-1px); box-shadow: 0 8px 18px rgba(2,54,117,.2); }
        .search-box { display: flex; align-items: center; background: white; border: 1px solid #e9eef2; border-radius: 10px; padding: 0 14px; min-width: 240px; }
        .search-box i { color: #94a3b8; }
        .search-box input { border: none; padding: 10px 8px; width: 100%; outline: none; font-size: .9rem; background: transparent; }

        /* ===== TABLE ===== */
        .table-wrap { background: white; border-radius: 16px; padding: 20px; border: 1px solid #e9eef2; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px 10px; color: #64748b; font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid #e9eef2; white-space: nowrap; }
        td { padding: 16px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fafbfd; }

        .item-title { font-weight: 600; color: #0f172a; margin-bottom: 3px; }
        .item-sub { font-size: .8rem; color: #64748b; }

        .badge { padding: 4px 12px; border-radius: 20px; font-size: .75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
        .badge.activo   { background: #d1fae5; color: #065f46; }
        .badge.inactivo { background: #fee2e2; color: #b91c1c; }
        .badge.tipo     { background: #e0f2fe; color: #0369a1; }
        .badge.ubi      { background: #f3e8ff; color: #6b21a8; }
        .badge.grupo    { background: #fff7ed; color: #9a3412; }
        .badge.foto     { background: #dbeafe; color: #1d4ed8; }
        .badge.video    { background: #fce7f3; color: #9d174d; }
        .badge.modelo   { background: #d1fae5; color: #065f46; }
        .badge.vr       { background: #fef9c3; color: #713f12; }

        .actions { display: flex; gap: 6px; }
        .btn-ic { width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e9eef2; background: white; color: #64748b; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: .85rem; transition: all .2s; }
        .btn-ic:hover { transform: translateY(-1px); }
        .btn-ic.edit:hover   { border-color: #023675; color: #023675; background: #f0f7ff; }
        .btn-ic.toggle:hover { border-color: #f59e0b; color: #f59e0b; background: #fffbeb; }
        .btn-ic.del:hover    { border-color: #ef4444; color: #ef4444; background: #fef2f2; }

        .empty { text-align: center; padding: 40px; color: #94a3b8; font-size: .95rem; }
        .empty i { font-size: 2.5rem; display: block; margin-bottom: 10px; opacity: .4; }

        /* ===== HISTORIAL ===== */
        .hist-filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .filter-sel { padding: 10px 14px; border: 1px solid #e9eef2; border-radius: 10px; font-size: .88rem; color: #1e293b; background: white; cursor: pointer; min-width: 160px; }
        .filter-sel:focus { outline: none; border-color: #023675; }

        .hist-card { display: flex; gap: 14px; padding: 14px 0; border-bottom: 1px solid #f1f5f9; align-items: flex-start; }
        .hist-card:last-child { border-bottom: none; }
        .hist-icon { width: 42px; height: 42px; border-radius: 10px; background: rgba(2,54,117,.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .hist-icon i { color: #023675; font-size: 1.1rem; }
        .hist-body { flex: 1; min-width: 0; }
        .hist-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 5px; }
        .hist-user { font-weight: 600; font-size: .95rem; color: #0f172a; }
        .hist-date { font-size: .8rem; color: #94a3b8; white-space: nowrap; }
        .hist-meta { font-size: .82rem; color: #475569; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .hist-meta span { display: inline-flex; align-items: center; gap: 4px; }
        .hist-obs { margin-top: 5px; font-size: .82rem; color: #64748b; font-style: italic; }

        /* ===== MODAL ===== */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 2000; justify-content: center; align-items: center; padding: 20px; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: white; border-radius: 18px; width: 100%; max-width: 520px; padding: 30px; animation: modalIn .25s ease; max-height: 90vh; overflow-y: auto; }
        @keyframes modalIn { from { opacity:0; transform:translateY(-20px); } to { opacity:1; transform:translateY(0); } }
        .modal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; }
        .modal-head h2 { font-size: 1.3rem; font-weight: 700; color: #0f172a; }
        .close-btn { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #94a3b8; transition: color .2s; }
        .close-btn:hover { color: #1e293b; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 7px; font-weight: 500; font-size: .9rem; color: #0f172a; }
        .form-control { width: 100%; padding: 11px 14px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: .92rem; font-family: 'Inter', sans-serif; background: #f8fafc; transition: all .2s; }
        .form-control:focus { outline: none; border-color: #023675; box-shadow: 0 0 0 3px rgba(2,54,117,.1); background: white; }
        textarea.form-control { resize: vertical; min-height: 72px; }
        .modal-foot { display: flex; gap: 12px; margin-top: 24px; }
        .modal-foot button { flex: 1; padding: 12px; border-radius: 10px; font-weight: 600; cursor: pointer; font-size: .9rem; transition: all .2s; }
        .btn-save   { background: #023675; color: white; border: none; }
        .btn-save:hover { background: #0345a0; }
        .btn-cancel { background: white; border: 1px solid #e9eef2; color: #64748b; }
        .btn-cancel:hover { background: #f8fafc; }

        @media (max-width: 1200px) { .stats-row { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { width:100%; height:auto; position:relative; }
            .main-content { padding: 20px; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .tabs-nav { width: 100%; overflow-x: auto; }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar_admin.php'; ?>

<div class="main-content">

    <div class="page-header">
        <div>
            <h1><i class="fas fa-hard-hat"></i> Gestión de Operaciones</h1>
            <div class="breadcrumb"><a href="dashboard_admin.php">Dashboard</a> / Operaciones</div>
        </div>
    </div>

    <?php if ($mensaje): ?>
    <div class="alert <?= $tipo_mensaje ?>">
        <i class="fas <?= $tipo_mensaje=='success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        <?= htmlspecialchars($mensaje) ?>
    </div>
    <?php endif; ?>

    <!-- Stats globales -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
            <div class="stat-info"><h4>Ubicaciones</h4><div class="number"><?= $total_ubis ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users-cog"></i></div>
            <div class="stat-info"><h4>Grupos Operativos</h4><div class="number"><?= $total_grupos ?></div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-photo-video"></i></div>
            <div class="stat-info"><h4>Archivos Subidos</h4><div class="number"><?= count($data_hist) ?>+</div></div>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs-nav">
        <button class="tab-btn <?= $active_tab=='ubicaciones'?'active':'' ?>" onclick="switchTab('ubicaciones')">
            <i class="fas fa-map-marker-alt"></i> Ubicaciones
        </button>
        <button class="tab-btn <?= $active_tab=='grupos'?'active':'' ?>" onclick="switchTab('grupos')">
            <i class="fas fa-users-cog"></i> Grupos Operativos
        </button>
        <button class="tab-btn <?= $active_tab=='historial'?'active':'' ?>" onclick="switchTab('historial')">
            <i class="fas fa-history"></i> Historial General
        </button>
    </div>

    <!-- ========== TAB: UBICACIONES ========== -->
    <div class="tab-pane <?= $active_tab=='ubicaciones'?'active':'' ?>" id="tab-ubicaciones">
        <div class="toolbar">
            <button class="btn-primary" onclick="abrirModal('modalUbi')">
                <i class="fas fa-plus"></i> Nueva Ubicación
            </button>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Buscar ubicación..." oninput="filtrar('tablaUbis', this.value)">
            </div>
        </div>
        <div class="table-wrap">
            <table id="tablaUbis">
                <thead>
                    <tr>
                        <th>Ubicación</th>
                        <th>Operación</th>
                        <th>Tipo</th>
                        <th>Grupos</th>
                        <th>Archivos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data_ubis as $u): ?>
                <tr>
                    <td>
                        <div class="item-title"><?= htmlspecialchars($u['nombre']) ?></div>
                        <div class="item-sub"><?= htmlspecialchars(substr($u['descripcion'],0,45)) ?></div>
                    </td>
                    <td><?= htmlspecialchars($u['op_nombre']) ?></td>
                    <td><span class="badge ubi"><?= htmlspecialchars($u['tipo_nombre']) ?></span></td>
                    <td><?= $u['total_grupos'] ?></td>
                    <td><?= $u['total_media'] ?></td>
                    <td>
                        <span class="badge <?= $u['estado']==1?'activo':'inactivo' ?>">
                            <i class="fas <?= $u['estado']==1?'fa-check-circle':'fa-times-circle' ?>"></i>
                            <?= $u['estado']==1?'Activo':'Inactivo' ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <button class="btn-ic edit" title="Editar"
                                onclick="editarUbi(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?action=toggle_ubi&id=<?= $u['id_ubicacion'] ?>&estado=<?= $u['estado'] ?>"
                               class="btn-ic toggle" title="Activar/Desactivar">
                                <i class="fas fa-power-off"></i>
                            </a>
                            <a href="?action=delete_ubi&id=<?= $u['id_ubicacion'] ?>"
                               class="btn-ic del" title="Eliminar"
                               onclick="return confirm('¿Eliminar esta ubicación? Solo se podrá si no tiene grupos ni archivos multimedia.')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($data_ubis)): ?>
                <tr><td colspan="7"><div class="empty"><i class="fas fa-map-marker-alt"></i>Sin ubicaciones registradas.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========== TAB: GRUPOS ========== -->
    <div class="tab-pane <?= $active_tab=='grupos'?'active':'' ?>" id="tab-grupos">
        <div class="toolbar">
            <button class="btn-primary" onclick="abrirModal('modalGrupo')">
                <i class="fas fa-plus"></i> Nuevo Grupo
            </button>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Buscar grupo..." oninput="filtrar('tablaGrupos', this.value)">
            </div>
        </div>
        <div class="table-wrap">
            <table id="tablaGrupos">
                <thead>
                    <tr>
                        <th>Grupo Operativo</th>
                        <th>Ubicación</th>
                        <th>Tipo</th>
                        <th>Archivos</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data_grupos as $g): ?>
                <tr>
                    <td>
                        <div class="item-title"><?= htmlspecialchars($g['nombre']) ?></div>
                        <div class="item-sub"><?= htmlspecialchars(substr($g['descripcion'],0,45)) ?></div>
                    </td>
                    <td><?= htmlspecialchars($g['ubi_nombre']) ?></td>
                    <td><span class="badge grupo"><?= htmlspecialchars($g['tipo_nombre']) ?></span></td>
                    <td><?= $g['total_media'] ?></td>
                    <td>
                        <span class="badge <?= $g['estado']==1?'activo':'inactivo' ?>">
                            <i class="fas <?= $g['estado']==1?'fa-check-circle':'fa-times-circle' ?>"></i>
                            <?= $g['estado']==1?'Activo':'Inactivo' ?>
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <button class="btn-ic edit" title="Editar"
                                onclick="editarGrupo(<?= htmlspecialchars(json_encode($g), ENT_QUOTES) ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?action=toggle_grupo&id=<?= $g['id_grupo'] ?>&estado=<?= $g['estado'] ?>"
                               class="btn-ic toggle" title="Activar/Desactivar">
                                <i class="fas fa-power-off"></i>
                            </a>
                            <a href="?action=delete_grupo&id=<?= $g['id_grupo'] ?>"
                               class="btn-ic del" title="Eliminar"
                               onclick="return confirm('¿Eliminar este grupo? Solo se podrá si no tiene archivos multimedia asociados.')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($data_grupos)): ?>
                <tr><td colspan="6"><div class="empty"><i class="fas fa-users-cog"></i>Sin grupos registrados.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========== TAB: HISTORIAL ========== -->
    <div class="tab-pane <?= $active_tab=='historial'?'active':'' ?>" id="tab-historial">

        <div class="hist-filters">
            <div class="search-box" style="min-width:220px;">
                <i class="fas fa-search"></i>
                <input type="text" id="histSearch" placeholder="Buscar usuario, zona..." oninput="filtrarHist()">
            </div>
            <select class="filter-sel" id="histTipo" onchange="filtrarHist()">
                <option value="">Todos los tipos</option>
                <option value="FOTO">Foto</option>
                <option value="VIDEO">Video</option>
                <option value="MODELO_3D">Modelo 3D</option>
            </select>
            <select class="filter-sel" id="histCliente" onchange="filtrarHist()">
                <option value="">Todos los clientes</option>
                <?php
                $clientes->data_seek(0);
                while ($c = $clientes->fetch_assoc()):
                ?>
                <option value="<?= htmlspecialchars($c['razon_social']) ?>"><?= htmlspecialchars($c['razon_social']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="table-wrap">
            <div id="histContainer">
            <?php foreach ($data_hist as $h):
                $icono = match($h['tipo_archivo']) {
                    'VIDEO'     => 'fa-video',
                    'MODELO_3D' => 'fa-cube',
                    default     => 'fa-image',
                };
                $badge_tipo = match($h['tipo_archivo']) {
                    'VIDEO'     => 'video',
                    'MODELO_3D' => 'modelo',
                    default     => 'foto',
                };
            ?>
            <div class="hist-card"
                 data-tipo="<?= $h['tipo_archivo'] ?>"
                 data-cliente="<?= htmlspecialchars($h['cliente_nombre']) ?>"
                 data-texto="<?= strtolower(htmlspecialchars($h['usuario_nombre'].' '.$h['ubicacion_nombre'].' '.$h['grupo_nombre'].' '.$h['operacion_nombre'])) ?>">

                <div class="hist-icon"><i class="fas <?= $icono ?>"></i></div>

                <div class="hist-body">
                    <div class="hist-top">
                        <span class="hist-user">
                            <?= htmlspecialchars($h['usuario_nombre']) ?>
                            <span class="badge tipo" style="font-size:.7rem; margin-left:6px;"><?= $h['rol_nombre'] ?></span>
                        </span>
                        <span class="hist-date"><i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($h['fecha_hora'])) ?></span>
                    </div>
                    <div class="hist-meta">
                        <span class="badge <?= $badge_tipo ?>"><i class="fas <?= $icono ?>"></i> <?= $h['tipo_archivo'] ?></span>
                        <?php if ($h['es_360']): ?>
                        <span class="badge vr"><i class="fas fa-vr-cardboard"></i> 360°/VR</span>
                        <?php endif; ?>
                        <span><i class="fas fa-building" style="color:#94a3b8;"></i> <?= htmlspecialchars($h['cliente_nombre']) ?></span>
                        <span><i class="fas fa-hard-hat" style="color:#94a3b8;"></i> <?= htmlspecialchars($h['operacion_nombre']) ?></span>
                        <span><i class="fas fa-map-marker-alt" style="color:#94a3b8;"></i> <?= htmlspecialchars($h['ubicacion_nombre']) ?></span>
                        <span><i class="fas fa-users" style="color:#94a3b8;"></i> <?= htmlspecialchars($h['grupo_nombre']) ?></span>
                    </div>
                    <?php if (!empty($h['observaciones'])): ?>
                    <div class="hist-obs">"<?= htmlspecialchars($h['observaciones']) ?>"</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($data_hist)): ?>
            <div class="empty"><i class="fas fa-history"></i>No hay actividad registrada.</div>
            <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /main-content -->

<!-- ========== MODAL: UBICACION ========== -->
<div class="modal-overlay" id="modalUbi">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="titleUbi"><i class="fas fa-map-marker-alt"></i> Nueva Ubicación</h2>
            <button class="close-btn" onclick="cerrarModal('modalUbi')">&times;</button>
        </div>
        <form method="POST" action="admin_operaciones.php">
            <input type="hidden" name="action" id="actionUbi" value="crear_ubicacion">
            <input type="hidden" name="id_ubicacion" id="idUbi">

            <div class="form-group">
                <label>Operación *</label>
                <select name="id_operacion_ubi" id="ubiOperacion" class="form-control" required>
                    <option value="">Seleccionar operación...</option>
                    <?php foreach ($data_ops as $op): ?>
                    <option value="<?= $op['id_operacion'] ?>"><?= htmlspecialchars($op['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tipo de Ubicación *</label>
                <select name="id_tipo_ubicacion" id="ubiTipo" class="form-control" required>
                    <option value="">Seleccionar tipo...</option>
                    <?php $tipos_ubi->data_seek(0); while ($t=$tipos_ubi->fetch_assoc()): ?>
                    <option value="<?= $t['id_tipo_ubicacion'] ?>"><?= htmlspecialchars($t['descripcion']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Nombre de la Ubicación *</label>
                <input type="text" name="nombre_ubi" id="ubiNombre" class="form-control" placeholder="Ej: Plataforma A1" required>
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion_ubi" id="ubiDesc" class="form-control"></textarea>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalUbi')">Cancelar</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- ========== MODAL: GRUPO ========== -->
<div class="modal-overlay" id="modalGrupo">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="titleGrupo"><i class="fas fa-users-cog"></i> Nuevo Grupo Operativo</h2>
            <button class="close-btn" onclick="cerrarModal('modalGrupo')">&times;</button>
        </div>
        <form method="POST" action="admin_operaciones.php">
            <input type="hidden" name="action" id="actionGrupo" value="crear_grupo">
            <input type="hidden" name="id_grupo" id="idGrupo">

            <div class="form-group">
                <label>Ubicación *</label>
                <select name="id_ubicacion_grupo" id="grupoUbicacion" class="form-control" required>
                    <option value="">Seleccionar ubicación...</option>
                    <?php
                    $todas_ubi->data_seek(0);
                    while ($u = $todas_ubi->fetch_assoc()):
                    ?>
                    <option value="<?= $u['id_ubicacion'] ?>"><?= htmlspecialchars($u['op_nombre'].' › '.$u['nombre']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Tipo de Grupo *</label>
                <select name="id_tipo_grupo" id="grupoTipo" class="form-control" required>
                    <option value="">Seleccionar tipo...</option>
                    <?php $tipos_grupo->data_seek(0); while ($t=$tipos_grupo->fetch_assoc()): ?>
                    <option value="<?= $t['id_tipo_grupo'] ?>"><?= htmlspecialchars($t['descripcion']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Nombre del Grupo *</label>
                <input type="text" name="nombre_grupo" id="grupoNombre" class="form-control" placeholder="Ej: Cuadrilla de Avance 01" required>
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion_grupo" id="grupoDesc" class="form-control"></textarea>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn-cancel" onclick="cerrarModal('modalGrupo')">Cancelar</button>
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
// ===== TABS =====
function switchTab(name) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.currentTarget.classList.add('active');
    history.replaceState(null, '', '?tab=' + name);
}

// ===== MODALES =====
function abrirModal(id) { document.getElementById(id).classList.add('open'); }
function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }

// Cerrar al click fuera
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ===== EDITAR UBICACION =====
function editarUbi(d) {
    document.getElementById('titleUbi').innerHTML      = '<i class="fas fa-edit"></i> Editar Ubicación';
    document.getElementById('actionUbi').value         = 'editar_ubicacion';
    document.getElementById('idUbi').value             = d.id_ubicacion;
    document.getElementById('ubiOperacion').value      = d.id_operacion;
    document.getElementById('ubiTipo').value           = d.id_tipo_ubicacion;
    document.getElementById('ubiNombre').value         = d.nombre;
    document.getElementById('ubiDesc').value           = d.descripcion;
    abrirModal('modalUbi');
}

// ===== EDITAR GRUPO =====
function editarGrupo(d) {
    document.getElementById('titleGrupo').innerHTML   = '<i class="fas fa-edit"></i> Editar Grupo Operativo';
    document.getElementById('actionGrupo').value      = 'editar_grupo';
    document.getElementById('idGrupo').value          = d.id_grupo;
    document.getElementById('grupoUbicacion').value   = d.id_ubicacion;
    document.getElementById('grupoTipo').value        = d.id_tipo_grupo;
    document.getElementById('grupoNombre').value      = d.nombre;
    document.getElementById('grupoDesc').value        = d.descripcion;
    abrirModal('modalGrupo');
}

// ===== FILTRO TABLA =====
function filtrar(tablaId, texto) {
    const q = texto.toLowerCase();
    document.querySelectorAll('#' + tablaId + ' tbody tr').forEach(tr => {
        tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ===== FILTRO HISTORIAL =====
function filtrarHist() {
    const q       = document.getElementById('histSearch').value.toLowerCase();
    const tipo    = document.getElementById('histTipo').value;
    const cliente = document.getElementById('histCliente').value;

    document.querySelectorAll('.hist-card').forEach(card => {
        const matchTexto   = card.dataset.texto.includes(q);
        const matchTipo    = !tipo    || card.dataset.tipo    === tipo;
        const matchCliente = !cliente || card.dataset.cliente === cliente;
        card.style.display = (matchTexto && matchTipo && matchCliente) ? '' : 'none';
    });
}

// ===== AUTO-CERRAR ALERTAS =====
document.addEventListener('DOMContentLoaded', () => {
    const a = document.querySelector('.alert');
    if (a) setTimeout(() => { a.style.transition='opacity .5s'; a.style.opacity='0'; setTimeout(()=>a.remove(),500); }, 3500);
});
</script>
</body>
</html>