<?php
session_start();

// ================== CONEXIÓN A LA BASE DE DATOS ==================
$host = "localhost";
$user = "Omar";
$password = "Palomitas32$";
$dbname = "popcode";
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// ================== PROCESAR FORMULARIO ==================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $no_colaborador = trim($_POST['no_colaborador']);
    $nombre_colaborador = trim($_POST['nombre_colaborador']);
    $rol_colaborador = trim($_POST['rol_colaborador']);
    $area = trim($_POST['area']);
    $contrasena = trim($_POST['contrasena']);
    $fecha = date("Y-m-d");
    $estatus = "Activo";

    // Validar campos obligatorios
    if (empty($no_colaborador) || empty($nombre_colaborador) || empty($rol_colaborador) || empty($area) || empty($contrasena)) {
        $_SESSION['mensaje'] = "❌ Todos los campos son obligatorios.";
        header("Location: usuarios.php");
        exit;
    }

    // Verificar si el usuario ya existe
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE no_colaborador = ?");
    $stmt->bind_param("s", $no_colaborador);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['mensaje'] = "❌ El número de colaborador ya está registrado.";
        $stmt->close();
        $conn->close();
        header("Location: usuarios.php");
        exit;
    }
    $stmt->close();

    // Insertar nuevo usuario
    $stmt = $conn->prepare("INSERT INTO usuarios (no_colaborador, nombre_colaborador, rol_colaborador, area, contrasena, fecha, estatus) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $no_colaborador, $nombre_colaborador, $rol_colaborador, $area, $contrasena, $fecha, $estatus);

    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "✅ Usuario registrado correctamente.";
    } else {
        $_SESSION['mensaje'] = "❌ Error al registrar el usuario: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    header("Location: usuarios.php");
    exit;
} else {
    header("Location: usuarios.php");
    exit;
}
