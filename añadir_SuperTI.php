<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Supervisor') {
  header("Location: index.html");
  exit;
}

include 'conexion.php';
date_default_timezone_set('America/Mexico_City');

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $codigo = trim($_POST['codigo']);
  $descripcion = trim($_POST['descripcion']);
  $fecha = date("Y-m-d");

  $imagenNombre = null;
  if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $directorioDestino = "imagenes_productos/";
    if (!is_dir($directorioDestino)) {
      mkdir($directorioDestino, 0755, true);
    }

    $extension = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    $imagenNombre = "prod_" . $codigo . "_" . time() . "." . $extension;
    $rutaCompleta = $directorioDestino . $imagenNombre;

    move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaCompleta);
  }

  $stmt = $conn->prepare("INSERT INTO productos_escanear (codigo_barras, descripcion, imagen, fecha) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("ssss", $codigo, $descripcion, $imagenNombre, $fecha);

  if ($stmt->execute()) {
    $mensaje = "✅ Producto registrado exitosamente.";
  } else {
    $mensaje = "❌ Error al registrar el producto: " . $stmt->error;
  }

  $stmt->close();
  $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>POP CODE - Añadir</title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #18bdff;
      color: #333;
    }

    header {
      background-color: #ffffff;
      padding: 20px 30px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    header h1 {
      font-size: 25px;
      color: #0e96cc;
      margin: 0;
    }

    main {
      padding: 30px;
      display: flex;
      justify-content: center;
    }

    .form-card {
      background-color: #fff;
      border-radius: 10px;
      padding: 30px;
      max-width: 800px;
      width: 100%;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
    }

    .form-left,
    .form-right {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    label {
      font-weight: bold;
      color: #0e96cc;
    }

    input[type="text"],
    textarea {
      padding: 10px;
      border: 2px solid #0e96cc;
      border-radius: 8px;
      font-size: 16px;
      width: 100%;
    }

    input[readonly] {
      background-color: #f0f0f0;
      cursor: not-allowed;
    }

    textarea {
      resize: vertical;
      height: 100px;
    }

    input[type="file"] {
      border: none;
      font-size: 16px;
    }

    .preview {
      width: 100%;
      max-height: 400px;
      object-fit: contain;
      border: 2px solid #0e96cc;
      border-radius: 8px;
    }

    .button-group {
      grid-column: span 2;
      display: flex;
      justify-content: space-between;
      margin-top: 20px;
    }

    .button-group button {
      flex: 1;
      margin: 0 10px;
      padding: 12px;
      font-size: 16px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      color: white;
      transition: background-color 0.3s ease;
    }

    .logo-esquina {
      position: fixed;
      bottom: 10px;
      right: 10px;
      width: 80px;
      height: auto;
    }
.logo-superior {
  position: absolute;
  top: 10px;
  left: 20px;
  width: 65px;
  height: auto;
}

    .btn-regresar {
      background-color: #ce1f1f;
    }

    .btn-regresar:hover {
      background-color: #a00;
    }

    .btn-registrar {
      background-color: #078dfa;
    }

    .btn-registrar:hover {
      background-color: #0070c0;
    }

    .btn-reportes {
      background-color: #0e96cc;
    }

    .btn-reportes:hover {
      background-color: #007bb5;
    }

    .mensaje {
      grid-column: span 2;
      text-align: center;
      font-weight: bold;
      margin-top: 20px;
    }
  </style>
</head>
<body>
  <header>
    <img src="../imagenes/SLIMPOP.png" alt="Logo Slimpop" class="logo-superior">
    <h1>Registro de Producto</h1>
  </header>

  <main>
    <form class="form-card" method="POST" enctype="multipart/form-data">
      <div class="form-left">
        <label for="codigo">Código de barras:</label>
        <input type="text" id="codigo" name="codigo" required>

        <label for="descripcion">Descripción:</label>
        <textarea id="descripcion" name="descripcion" placeholder="Escribe la descripción del producto..." required></textarea>
      </div>

      <div class="form-right">
        <label for="imagen">Imagen del producto (600x900):</label>
        <input type="file" id="imagen" name="imagen" accept="image/*" onchange="mostrarImagen(event)" required>
        <img id="preview" class="preview" src="img/carga1.gif" alt="Vista previa del producto">
      </div>

      <div class="button-group">
        <button type="button" class="btn-regresar" onclick="window.location.href='escaneoSuperTI.php'">Regresar</button>
        <button type="submit" class="btn-registrar">Registrar</button>
        <button type="button" class="btn-reportes" onclick="window.location.href='reportes_SuperTI.php'">Reportes</button>
      </div>

      <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?= htmlspecialchars($mensaje) ?></div>
      <?php endif; ?>
    </form>
  </main>

  <img src="../imagenes/carita_feliz_blanca.png" alt="Logo inferior" class="logo-esquina">

  <script>
    function mostrarImagen(event) {
      const file = event.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
          document.getElementById("preview").src = e.target.result;
        };
        reader.readAsDataURL(file);
      }
    }
  </script>
</body>
</html>
