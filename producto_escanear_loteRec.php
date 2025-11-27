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
  $lote = trim($_POST['lote'] ?? '');

  if ($producto !== '' && $lote !== '') {
    $_SESSION['producto_seleccionado'] = $producto;
    $_SESSION['lote_generado'] = $lote;
    $_SESSION['operador_actual'] = $usuario;

    header("Location: escaneo_productoRec.php");
    exit;
  } else {
    $mensaje = "⚠️ Por favor selecciona un producto y genera el lote antes de continuar.";
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
        <input type="text" id="lote" name="lote" readonly required placeholder="Haz clic para generar" onclick="abrirModal()">

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

  <!-- Modal para generar lote -->
  <div class="modal" id="modal">
    <div class="modal-content">
      <label>Tipo de Producto:</label>
      <select id="modal-tipo"><option value="T">T</option><option value="TP">TP</option></select>
      <label>Turno:</label>
      <select id="modal-turno"><option value="A">Matutino (A)</option><option value="B">Vespertino (B)</option><option value="C">Nocturno (C)</option></select>
      <label>Línea:</label>
      <select id="modal-linea"><option value="1">Línea 1</option><option value="2">Línea 2</option><option value="3">Línea 3</option><option value="4">Línea 4</option></select>
      <button onclick="generarLote()">Generar Lote</button>
    </div>
  </div>

  <img src="../imagenes/carita_feliz_blanca.png" alt="Logo inferior" class="logo-inferior">
  <script>
    function abrirModal() { document.getElementById("modal").style.display = "block"; }
    function cerrarModal() { document.getElementById("modal").style.display = "none"; }
    function getJulianDay() {
      const now = new Date(); const start = new Date(now.getFullYear(), 0, 0);
      const diff = now - start; const oneDay = 1000 * 60 * 60 * 24;
      return Math.floor(diff / oneDay).toString().padStart(3, '0');
    }
    function generarLote() {
      const tipo = document.getElementById("modal-tipo").value;
      const turno = document.getElementById("modal-turno").value;
      const linea = document.getElementById("modal-linea").value;
      const juliano = getJulianDay();
      const año = new Date().getFullYear().toString().slice(-2);
      const lote = `${juliano}${año}${tipo}${turno}${linea}`.toUpperCase();
      document.getElementById("lote").value = lote;
      cerrarModal();
    }
    window.onclick = e => { if (e.target === document.getElementById("modal")) cerrarModal(); };
  </script>
</body>
</html>
