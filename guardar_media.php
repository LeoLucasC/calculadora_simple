<?php
session_start();
include 'includes/db.php';

// 1. Logs de Debugging
function escribir_log($mensaje) {
    $fecha = date("Y-m-d H:i:s");
    file_put_contents("debug_upload.txt", "[$fecha] $mensaje" . PHP_EOL, FILE_APPEND);
}

if (!isset($_SESSION['id_usuario'])) { escribir_log("Error: Sesión no iniciada"); header("Location: index.html"); exit(); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // CASO CRÍTICO: Si $_FILES está vacío pero el método es POST, el archivo superó post_max_size
    if (empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $max_size = ini_get('post_max_size');
        escribir_log("ERROR: El archivo es demasiado grande. Superó el post_max_size de $max_size");
        header("Location: subir.php?status=error&msg=El video es demasiado pesado para el servidor (Max: $max_size)");
        exit();
    }

    if (isset($_FILES['archivo'])) {
        $archivo = $_FILES['archivo'];
        $error_code = $archivo['error'];

        // Verificar errores nativos de PHP
        if ($error_code !== UPLOAD_ERR_OK) {
            $errores_php = [
                1 => 'El archivo excede upload_max_filesize en php.ini',
                2 => 'El archivo excede MAX_FILE_SIZE especificado en el formulario',
                3 => 'El archivo se subió parcialmente',
                4 => 'No se subió ningún archivo',
                6 => 'Falta la carpeta temporal',
                7 => 'Error al escribir en el disco'
            ];
            $msg_error = $errores_php[$error_code] ?? 'Error desconocido';
            escribir_log("ERROR PHP Code $error_code: $msg_error");
            header("Location: subir.php?status=error&msg=$msg_error");
            exit();
        }

        // Procesar datos
        $id_usuario = $_SESSION['id_usuario'];
        $id_ubicacion = intval($_POST['id_ubicacion']);
        $id_grupo = intval($_POST['id_grupo']);
        $tipo = $_POST['tipo_archivo'];
        $es_360 = isset($_POST['es_360']) ? 1 : 0;
        $obs = $conn->real_escape_string($_POST['observaciones']);

        $nombreOriginal = $archivo['name'];
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $nombreUnico = uniqid('media_', true) . '.' . $extension;
        $rutaFinal = 'uploads/' . $nombreUnico;

        if (move_uploaded_file($archivo['tmp_name'], $rutaFinal)) {
            $sql = "INSERT INTO multimedia (id_usuario, id_ubicacion, id_grupo, tipo_archivo, url_archivo, es_360, fecha_hora, observaciones) 
                    VALUES ($id_usuario, $id_ubicacion, $id_grupo, '$tipo', '$rutaFinal', $es_360, NOW(), '$obs')";
            
            if ($conn->query($sql)) {
                escribir_log("ÉXITO: $nombreOriginal subido correctamente como $nombreUnico");
                header("Location: subir.php?status=ok");
            } else {
                escribir_log("ERROR SQL: " . $conn->error);
                header("Location: subir.php?status=error&msg=Error en base de datos");
            }
        } else {
            escribir_log("ERROR: No se pudo mover el archivo a /uploads. Verifique permisos de carpeta.");
            header("Location: subir.php?status=error&msg=Error de permisos en servidor");
        }
    }
}
?>