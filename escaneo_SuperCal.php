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
  <title>POP CODE - Escanear</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <header>
    <img src="../imagenes/SLIMPOP.png" alt="Logo SlimPop">
    <h1>¡Bienvenido!</h1>
    <a href="MainSuperCal.php" class="logout-btnn">Regresar</a>
    <a href="logout.php" class="logout-btn">Cerrar sesión</a>
  </header>
  <main>
    <h2>¿Qué deseas realizar?</h2>

    <div class="button-grid">
      <a href="producto_escanear_lote_SuperCal.php" class="action-btn">
        <span>𝄃𝄂𝄀𝄁</span>
        Escaneo
      </a>

      <a href="añadir_SuperCal.php" class="action-btn">
        <span>📝</span>
        Añadir
      </a>
    </div>
  </main>
<div class="spacer">
<div class="spacer">
  <footer>
    <img src="../imagenes/carita_feliz_blanca.png" alt="Logo SlimPop" class="logo-esquina">
  </footer>
  </div>
  </div>
</body>
</html>
