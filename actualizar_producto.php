<?php
// Forzar salida JSON y limpiar todo
header('Content-Type: application/json; charset=utf-8');
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

include 'conexion.php';

$response = ['success' => false];

if (isset($_POST['codigo_barras'], $_POST['descripcion'])) {
    $codigo = trim($_POST['codigo_barras']);
    $descripcion = trim($_POST['descripcion']);

    if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === 0) {
        $nombre_img = uniqid() . "_" . basename($_FILES['imagen']['name']);
        $ruta_img = "imagenes_productos/" . $nombre_img;

        if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_img)) {
            $stmt = $conn->prepare("SELECT imagen FROM productos_escanear WHERE codigo_barras = ?");
            $stmt->bind_param("s", $codigo);
            $stmt->execute();
            $stmt->bind_result($img_ant);
            $stmt->fetch();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE productos_escanear SET descripcion = ?, imagen = ? WHERE codigo_barras = ?");
            $stmt->bind_param("sss", $descripcion, $nombre_img, $codigo);
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['nueva_imagen'] = $ruta_img;
                if ($img_ant && file_exists("imagenes_productos/" . $img_ant)) {
                    unlink("imagenes_productos/" . $img_ant);
                }
            }
            $stmt->close();
        }
    } else {
        $stmt = $conn->prepare("UPDATE productos_escanear SET descripcion = ? WHERE codigo_barras = ?");
        $stmt->bind_param("ss", $descripcion, $codigo);
        if ($stmt->execute()) {
            $response['success'] = true;
        }
        $stmt->close();
    }
}

ob_clean(); // eliminar cualquier salida previa
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
