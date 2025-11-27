<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Supervisor') {
  header("Location: index.html");
  exit;
}
$usuario_nombre = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>POP CODE - Recepcion</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <header>
    <img src="../imagenes/SLIMPOP.png" alt="Logo SlimPop">
    <h1>Operador</h1>
    <a href="logout.php" class="logout-btn">Cerrar sesión</a>
  </header>

  <main>
    <h2>¡Bienvenido, <?php echo htmlspecialchars( $usuario_nombre); ?>!</h2>
    <p>¿Qué deseas hacer hoy?</p>

    <div class="button-grid">
      <a href="producto_escanear_loteRecepcion.php" class="action-btn">
        <span>📦</span>
        Recepción
        </a>
      <a href="producto_escanear_loteRec.php" class="action-btn">
        <span>𝄃𝄂𝄀𝄁</span>
        Escanear
      </a>

    </div>
  </main>

  <footer>
    <img src="../imagenes/carita_feliz_blanca.png" alt="Logo SlimPop" class="logo-esquina">
  </footer>

</body>
</html>
