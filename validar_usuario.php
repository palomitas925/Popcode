<?php
header('Content-Type: application/json');

// Obtener datos JSON desde el cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

// Extraer usuario y contraseña del JSON recibido
$usuario = $data['usuario'] ?? '';
$contrasena = $data['contrasena'] ?? '';

/*
// === Validación de reCAPTCHA ===
$captcha = $data['captcha'] ?? '';

if (!$captcha) {
    echo json_encode(["exito" => false, "error" => "Captcha no enviado"]);
    exit;
}

$secret = "TU_SECRET_KEY_AQUI";

$respuesta = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$captcha");
$respuesta = json_decode($respuesta, true);

if (!$respuesta["success"]) {
    echo json_encode(["exito" => false, "error" => "Captcha inválido"]);
    exit;
}
*/
// Parámetros de conexión
$servername = "localhost";
$username = "Omar";
$password = "Palomitas32$";
$dbname = "popcode";

// Crear conexión
$conexion = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conexion->connect_error) {
  echo json_encode(["exito" => false, "error" => "Error de conexión"]);
  exit;
}

$conexion->set_charset("utf8");

// Consulta segura: ahora también obtenemos el estatus
$sql = "SELECT rol_colaborador, area, estatus 
        FROM usuarios 
        WHERE nombre_colaborador = ? AND contrasena = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ss", $usuario, $contrasena);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

  // Validar si está desactivado/inactivo
  if (strtolower($row['estatus']) === 'desactivado' || strtolower($row['estatus']) === 'inactivo') {
    echo json_encode([
      "exito" => false,
      "error" => "Tu cuenta está desactivada. Contacta al administrador 😜."
    ]);
    exit;
  }

  // Si el usuario está activo, permitir el inicio de sesión
  session_start();
  $_SESSION['usuario'] = $usuario;
  $_SESSION['rol'] = $row["rol_colaborador"];
  $_SESSION['area'] = $row["area"];

  echo json_encode([
    "exito" => true,
    "rol_colaborador" => $row["rol_colaborador"],
    "area" => $row["area"]
  ]);

} else {
  echo json_encode(["exito" => false, "error" => "Usuario o Contraseña incorrecta ❌"]);
}

$conexion->close();
?>
