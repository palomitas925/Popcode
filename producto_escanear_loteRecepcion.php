<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Mexico_City');

$usuario = $_SESSION['usuario'] ?? 'desconocido';
$mensaje = "";

// Obtener productos registrados
$productos = [];
$resultado = $conn->query("SELECT descripcion FROM productos_escanear ORDER BY descripcion ASC");
while ($row = $resultado->fetch_assoc()) {
  $productos[] = $row['descripcion'];
}

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $producto = trim($_POST['producto'] ?? '');
  $lote = trim($_POST['lote'] ?? 'Recepcion'); // valor por defecto

  if ($producto !== '' && $lote !== '') {
    $_SESSION['producto_seleccionado'] = $producto;
    $_SESSION['lote_generado'] = $lote;
    $_SESSION['operador_actual'] = $usuario;

    header("Location: escaneo_productoRecepcion.php");
    exit;
  } else {
    $mensaje = "⚠️ Por favor selecciona un producto antes de continuar.";
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POP CODE - Selección de Producto</title>
  <link rel="stylesheet" href="estilo_producto_lote.css">
</head>
<body>
  <header>
    <img src="../imagenes/SLIMPOP.png" alt="Logo esquina" class="logo-esquina" style="width:65px; height:auto;">
    <h1>¿Qué producto desea escanear hoy?</h1>
  </header>
  <main>
    <form method="POST">
      <div class="form-card">
        <label for="producto">Producto a escanear:</label>
        <select id="producto" name="producto" required>
          <option value="">-- Selecciona --</option>
          <?php foreach ($productos as $descripcion): ?>
            <option value="<?= htmlspecialchars($descripcion) ?>" <?= isset($_POST['producto']) && $_POST['producto'] === $descripcion ? 'selected' : '' ?>>
              <?= htmlspecialchars($descripcion) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label for="lote">No. Lote:</label>
        <!-- Campo fijo con valor "Recepcion" -->
        <input type="text" id="lote" name="lote" value="Recepcion" readonly required>

        <label for="operador">Operador:</label>
        <input type="text" id="operador" value="<?= htmlspecialchars($usuario) ?>" readonly>

        <div class="button-group">
          <button class="cancel-btn" type="button" onclick="window.location.href='MainSuperRecepcion.php'">Cancelar</button>
          <button class="continue-btn" type="submit">Continuar</button>
        </div>

        <?php if (!empty($mensaje)): ?>
          <div class="resultado"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>
      </div>
    </form>
  </main>

  <img src="../imagenes/carita_feliz_blanca.png" alt="Logo inferior" class="logo-inferior">
</body>
</html>
