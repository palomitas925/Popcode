<?php
session_start();

// Eliminar todas las variables de sesión
$_SESSION = array();

// Si existe una cookie de sesión, eliminarla también
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000, 
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login o página principal
header("Location: index.html");
exit;
?>
