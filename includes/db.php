<?php
// includes/db.php

$host = "localhost";
$user = "root";      // Cambia si tu usuario es diferente
$pass = "";          // Cambia si tienes contraseña en MySQL
$db   = "kunturvr";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión fatal: " . $conn->connect_error);
}

// Forzar caracteres UTF-8 para evitar problemas con tildes
$conn->set_charset("utf8mb4");
?>