<?php
session_start();
include 'includes/db.php'; // Incluimos la conexión

if (isset($_SESSION['id_usuario'])) {
    $id_user = $_SESSION['id_usuario'];
    
    // Limpiamos el token de la BD para liberar la cuenta inmediatamente
    // Así tú o cualquier otra persona puede entrar al instante
    $conn->query("UPDATE usuarios SET session_token = NULL, ultimo_acceso = NULL WHERE id_usuario = $id_user");
}

session_unset();
session_destroy();
header("Location: index.html");
exit();
?>