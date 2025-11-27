<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'conexion.php';
date_default_timezone_set('America/Mexico_City');

$usuario = $_SESSION['usuario'] ?? 'desconocido';
$rol = $_SESSION['rol'] ?? 'sin rol';
$producto_esperado = $_SESSION['producto_seleccionado'] ?? '';
$lote = $_SESSION['lote_generado'] ?? '';
$descripcion = '';
$imagen = 'img/carga.webp'; // Imagen por defecto
$estado = '';
$alerta_sonora = false;

$directorioImagenes = 'imagenes_productos/';

if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST['codigo_barras'])) {
    $codigo = trim($_POST['codigo_barras']);
    $operador = $usuario . " (" . $rol . ")";

    $consulta = $conn->prepare("SELECT descripcion, imagen FROM productos_escanear WHERE codigo_barras = ?");
    $consulta->bind_param("s", $codigo);
    $consulta->execute();
    $resultado = $consulta->get_result();

    // Valores por defecto si no se encuentra
    $estado  = "Fallido";
    $descripcion = "No encontrado";
    $imagen = "img/dialogerror.png";
    $alerta_sonora = true;

    if ($resultado->num_rows > 0) {
        $datos = $resultado->fetch_assoc();
        $descripcion = $datos['descripcion'];
        $imagenNombre = $datos['imagen'];

        // Construir ruta completa si existe imagen
        if (!empty($imagenNombre) && file_exists($directorioImagenes . $imagenNombre)) {
            $imagen = $directorioImagenes . $imagenNombre;
        } else {
            $imagen = "img/placeholder.png"; // fallback
        }

        if ($descripcion === $producto_esperado) {
            $estado = "Exitoso";
            $alerta_sonora = false;
        }
    }

    // Registrar el escaneo (incluso si no se encontró el producto)
    $registro = $conn->prepare("INSERT INTO registros_escaneos (
        codigo_barras_producto, descripcion_producto, lote_escaneado,
        fecha_escaneo, hora_escaneo, estado_escaneo, nombre_colaborador_usuarios
    ) VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, ?)");
    $registro->bind_param("sssss", $codigo, $descripcion, $lote, $estado, $operador);
    $registro->execute();
    $registro->close();

    $consulta->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <img src="../imagenes/slimpop-blanca.png" alt="Logo esquina" class="logo-esquina" style="width:65px; height:auto;">
    <meta charset="UTF-8">
    <title>POPCODE - Escaneo</title>
    <link rel="stylesheet" href="estilo_esc_prod.css">
</head>
<body>
  <div class="contenedor">
    <div class="panel-izquierdo">
      <h1>Escaneo de Productos</h1>
      <p><strong>Producto esperado:</strong> <?= htmlspecialchars($producto_esperado) ?></p>
      <p><strong>Lote:</strong> <?= htmlspecialchars($lote) ?></p>

      <form method="POST" autocomplete="off">
        <label>Código de barras:</label>
        <input type="text" name="codigo_barras" autofocus required maxlength="13">

        <label>Descripción:</label>
        <input type="text" value="<?= htmlspecialchars($descripcion) ?>" readonly>

        <div class="focos">
          <div class="foco <?= $estado === 'Exitoso' ? 'verde' : ($estado === 'Fallido' ? 'rojo' : '') ?>"></div>
          <?php if ($estado): ?>
            <p style="font-weight:bold; color:<?= $estado === 'Exitoso' ? 'green' : 'red'; ?>;">
              <?= htmlspecialchars($estado) ?>
            </p>
          <?php endif; ?>
        </div>

        <div class="botones">
          <button type="button" onclick="location.href='producto_escanear_loteRecepcion.php'">Regresar</button>
          <button type="button" onclick="location.href='registro_escaneosRecepcion.php'">Registros</button>
        </div>
      </form>
    </div>

    <div class="panel-derecho">
      <label>Imagen:</label>
      <div class="imagen">
        <img src="<?= htmlspecialchars($imagen) ?>" alt="Imagen del producto" width="200">
      </div>
    </div>
  </div>

  <img src="../imagenes/carita_feliz_blanca.png" alt="Logo inferior" class="logo-inferior">

  <script>
    document.querySelector("input[name='codigo_barras']").addEventListener("change", function () {
      this.form.submit();
    });

    <?php if ($alerta_sonora): ?>
      const audio = new Audio('error.mp3');
      audio.play();
    <?php endif; ?>
  </script>
</body>
</html>
