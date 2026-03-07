<?php
// miniatura.php - Redimensiona imágenes pesadas al vuelo para la galería
$ruta = isset($_GET['ruta']) ? urldecode($_GET['ruta']) : '';

if (!file_exists($ruta)) { exit; }

$ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png'])) { 
    // Si no es imagen, devolvemos el archivo original
    header('Content-Type: image/jpeg');
    readfile($ruta);
    exit; 
}

// Ancho máximo para la tarjeta de galería (aumentado a 800 para mayor nitidez)
$max_width = 800;

list($width, $height) = getimagesize($ruta);

// Si ya es pequeña, la devolvemos tal cual
if ($width <= $max_width) {
    header('Content-Type: image/jpeg');
    readfile($ruta);
    exit;
}

// Calcular nuevo tamaño manteniendo proporción
$ratio = $width / $height;
$new_width = $max_width;
$new_height = $max_width / $ratio;

// Crear lienzo y comprimir
$thumb = imagecreatetruecolor($new_width, $new_height);

if ($ext == 'jpg' || $ext == 'jpeg') { 
    $source = imagecreatefromjpeg($ruta); 
    header('Content-Type: image/jpeg'); 
} elseif ($ext == 'png') { 
    // Mantener transparencia en PNG
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $source = imagecreatefrompng($ruta); 
    header('Content-Type: image/png'); 
}

// Copiar y redimensionar
imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

// Imprimir imagen optimizada
if ($ext == 'jpg' || $ext == 'jpeg') { imagejpeg($thumb, null, 90); } // Calidad subida a 90%
elseif ($ext == 'png') { imagepng($thumb, null, 7); }

// Liberar memoria del servidor
imagedestroy($thumb);
imagedestroy($source);
?>