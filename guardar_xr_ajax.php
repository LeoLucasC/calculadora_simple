<?php
session_start();
include 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_usuario = intval($_SESSION['id_usuario']);
    $json_data = $_POST['json_data'] ?? '';
    $nombre_proyecto = $_POST['nombre_proyecto'] ?? 'Proyecto XR 360';

    if (empty($json_data)) {
        echo json_encode(['status' => 'error', 'message' => 'El JSON está vacío.']);
        exit();
    }

    // Proteger el JSON de inyecciones SQL
    $json_escaped = $conn->real_escape_string($json_data);
    $nombre_escaped = $conn->real_escape_string($nombre_proyecto);

    // Verificar si el usuario ya tiene un proyecto guardado (para actualizarlo en lugar de crear 1000 copias)
    $check = $conn->query("SELECT id_proyecto FROM proyectos_xr WHERE id_usuario = $id_usuario AND nombre_proyecto = '$nombre_escaped' LIMIT 1");
    
    if ($check && $check->num_rows > 0) {
        // ACTUALIZAR proyecto existente
        $id_proyecto = $check->fetch_assoc()['id_proyecto'];
        $sql = "UPDATE proyectos_xr SET json_config = '$json_escaped', nombre_proyecto = '$nombre_escaped' WHERE id_proyecto = $id_proyecto";
    } else {
        // CREAR proyecto nuevo
        $sql = "INSERT INTO proyectos_xr (id_usuario, nombre_proyecto, json_config) VALUES ($id_usuario, '$nombre_escaped', '$json_escaped')";
    }

    if ($conn->query($sql)) {
        echo json_encode(['status' => 'ok', 'message' => 'Proyecto guardado con éxito']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error BD: ' . $conn->error]);
    }
}
?>