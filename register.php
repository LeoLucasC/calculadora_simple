<?php
// register.php
include 'includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibir y limpiar datos
    $nombre = $conn->real_escape_string($_POST['nombre']);
    $usuario = $conn->real_escape_string($_POST['usuario']);
    $id_cliente = intval($_POST['id_cliente']);
    $id_rol = intval($_POST['id_rol']);
    $password = $_POST['password'];

    // 1. Verificar si el usuario ya existe
    $check = $conn->query("SELECT id_usuario FROM usuarios WHERE usuario = '$usuario'");
    if ($check->num_rows > 0) {
        echo "<script>
            alert('Error: El usuario ya existe en el sistema KunturVR.');
            window.location.href='dashboard_cliente.php'; // O el nombre de tu archivo login
        </script>";
        exit();
    }

    // 2. Encriptar contraseña (Nunca guardar texto plano)
    $pass_hash = password_hash($password, PASSWORD_BCRYPT);

    // 3. Insertar en la BD
    $sql = "INSERT INTO usuarios (id_cliente, id_rol, nombre, usuario, password_hash) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $id_cliente, $id_rol, $nombre, $usuario, $pass_hash);

    if ($stmt->execute()) {
        echo "<script>
            alert('Registro exitoso. Bienvenido a la red, Operador.');
            window.location.href='dashboard_cliente.php';
        </script>";
    } else {
        echo "Error en el sistema: " . $conn->error;
    }
    
    $stmt->close();
    $conn->close();
}
?>