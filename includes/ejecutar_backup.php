<?php
session_start();
// Validar que sea admin
if (!isset($_SESSION['id_usuario']) || $_SESSION['id_rol'] != 1) { 
    header("Location: ../index.html"); 
    exit(); 
}

// 1. Definir rutas (asumiendo que este archivo está en la carpeta /includes)
$fecha = date("Y-m-d_H-i-s");
$nombre_zip = "backup_KunturXR_" . $fecha . ".zip";
$ruta_destino_zip = "../backups/" . $nombre_zip;
$ruta_uploads = "../uploads/";

// Crear carpeta backups si no existe
if (!is_dir("../backups/")) {
    mkdir("../backups/", 0777, true);
}

// 2. Inicializar la clase ZipArchive de PHP
$zip = new ZipArchive();

// 3. Crear el archivo ZIP
if ($zip->open($ruta_destino_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    
    // Verificar si la carpeta uploads existe y tiene archivos
    if (is_dir($ruta_uploads)) {
        // Usar iteradores recursivos para leer carpetas y subcarpetas
        $archivos = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($ruta_uploads),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($archivos as $nombre => $archivo) {
            // Ignorar los directorios "." y ".."
            if (!$archivo->isDir()) {
                // Obtener la ruta real del archivo
                $rutaReal = $archivo->getRealPath();
                
                // Obtener la ruta relativa para mantener la estructura dentro del ZIP
                $rutaRelativa = substr($rutaReal, strlen(realpath($ruta_uploads)) + 1);

                // Añadir el archivo al zip dentro de una carpeta llamada 'uploads'
                $zip->addFile($rutaReal, "uploads/" . $rutaRelativa);
            }
        }
    } else {
        // Si por alguna razón la carpeta uploads no existe, crea una vacía en el zip
        $zip->addEmptyDir('uploads');
    }

    // ========================================================================
    // NOTA PARA TI: Si ya tenías un código aquí que generaba el archivo .sql
    // de la base de datos, puedes generarlo y luego agregarlo al zip con:
    // $zip->addFile("../backups/base_datos.sql", "base_datos.sql");
    // ========================================================================

    // 4. Cerrar y guardar el ZIP
    $zip->close();

    // Redirigir de vuelta al panel con mensaje de éxito
    header("Location: ../admin_backups.php?status=success");
    exit();
} else {
    // Si falla la creación del ZIP
    header("Location: ../admin_backups.php?status=error");
    exit();
}
?>