<?php
header('Content-Type: application/json');
include 'conexion.php';

$response = ['success' => false];

if (isset($_POST['codigo_barras'])) {
    $codigo = trim($_POST['codigo_barras']);

    // Obtener imagen
    $stmt = $conn->prepare("SELECT imagen FROM productos_escanear WHERE codigo_barras = ?");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $stmt->bind_result($imagen);
    $stmt->fetch();
    $stmt->close();

    // Eliminar registro
    $stmt = $conn->prepare("DELETE FROM productos_escanear WHERE codigo_barras = ?");
    $stmt->bind_param("s", $codigo);
    if ($stmt->execute()) {
        $response['success'] = true;
        if ($imagen && file_exists("imagenes_productos/" . $imagen)) {
            unlink("imagenes_productos/" . $imagen);
        }
    }
    $stmt->close();
}

$conn->close();
echo json_encode($response);
exit;
?>
