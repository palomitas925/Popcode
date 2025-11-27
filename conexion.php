<?php
$servername = "localhost";
$username = "Omar";
$password = "Palomitas32$";
$dbname = "popcode";

$conn = @new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_errno) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Error de conexión con la base de datos.'
    ]);
    exit;
}

$conn->set_charset("utf8");
?>
