<?php
// includes/seguridad.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Si no hay variable de sesión, pa' fuera
if (!isset($_SESSION['id_usuario'])) {
    header("Location: index.html");
    exit();
}

// Asegurarnos de tener conexión a la BD
require_once 'db.php'; 

$id_user = $_SESSION['id_usuario'];
$mi_token = session_id(); // El ID único de la pestaña/navegador actual

// 2. Consultamos qué token tiene guardado la Base de Datos
$res_sec = $conn->query("SELECT session_token FROM usuarios WHERE id_usuario = $id_user");

if ($res_sec && $row_sec = $res_sec->fetch_assoc()) {
    
    // 3. Si el token de la BD NO coincide con el de este navegador...
    // Significa que alguien más logró entrar o mi sesión fue eliminada
    if ($row_sec['session_token'] !== $mi_token) {
        session_unset();
        session_destroy();
        
        echo "<script>
            alert('Tu sesión fue cerrada porque se inició sesión desde otro dispositivo o por inactividad.');
            window.location.href='index.html';
        </script>";
        exit();
        
    } else {
        // 4. Si coincide, actualizamos la hora para que el sistema sepa que seguimos activos
        date_default_timezone_set('America/Lima');
        $conn->query("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = $id_user");
    }
}
?>