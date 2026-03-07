<?php
include 'db.php';
// Configuración de nombre
$fecha = date("Ymd_His");
$nombre_archivo = "../backups/db_backup_" . $fecha . ".sql";

// Comando para Windows (XAMPP)
// En Linux sería: mysqldump -u $user -p$pass $db > $nombre_archivo
$comando = "C:\\xampp\\mysql\\bin\\mysqldump.exe --user=$user --password=$pass --host=$host $db > $nombre_archivo";

system($comando, $resultado);

if ($resultado === 0) {
    header("Location: ../admin_backup.php?status=ok");
} else {
    header("Location: ../admin_backup.php?status=error");
}
?>