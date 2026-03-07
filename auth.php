<?php
// auth.php - VERSIÓN FINAL DE PRODUCCIÓN
session_start();
include 'includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Recibir datos del formulario
    $usuario = $conn->real_escape_string($_POST['usuario']);
    $password = $_POST['password'];

    // 2. Buscar usuario en la BD
    $sql = "SELECT id_usuario, nombre, password_hash, id_rol, session_token, ultimo_acceso FROM usuarios WHERE usuario = ? AND estado = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $fila = $resultado->fetch_assoc();
        
        // 3. Verificar contraseña
        if (password_verify($password, $fila['password_hash'])) {
            
            // --- ¡LOGIN EXITOSO! ---
            // (Eliminamos el bloqueo. Ahora el último que entra sobreescribe el token)
            
            date_default_timezone_set('America/Lima'); 
            $ahora = date('Y-m-d H:i:s');
            
            session_regenerate_id(true); 
            
            // Guardar el nuevo token de este navegador
            $nuevo_token = session_id();
            $id_us = $fila['id_usuario'];
            
            // Al actualizar este token en la BD, el dispositivo anterior dejará de coincidir 
            // con seguridad.php y su sesión será cerrada automáticamente.
            $conn->query("UPDATE usuarios SET session_token = '$nuevo_token', ultimo_acceso = '$ahora' WHERE id_usuario = $id_us");
            
            // Guardar datos en la sesión local
            $_SESSION['id_usuario'] = $fila['id_usuario'];
            $_SESSION['nombre'] = $fila['nombre'];
            $_SESSION['id_rol'] = $fila['id_rol'];

           // 4. REDIRECCIÓN SEGÚN ROL
           
            if ($fila['id_rol'] == 1) {
                // Si es ADMIN (Rol 1) -> Panel Administrador
                header("Location: dashboard_admin.php");
            } elseif ($fila['id_rol'] == 2) {
                // Si es VIEWER (Rol 2) -> Panel de Visualización (Solo Ver)
                header("Location: ver_grupo_viewer.php");
            } else {
                // Si es CREATOR (Rol 4) -> Panel de Gestión (Subir/Editar)
                header("Location: dashboard_cliente.php");
            }
            exit();

        } else {
            // Contraseña incorrecta
            echo "<script>
                alert('Error: La contraseña es incorrecta.');
                window.location.href='login.php';
            </script>";
        }
    } else {
        // Usuario no encontrado
        echo "<script>
            alert('Error: El usuario no existe.');
            window.location.href='login.php';
        </script>";
    }

    $stmt->close();
    $conn->close();
}
?>