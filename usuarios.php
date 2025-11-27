<?php
session_start();

// ================== VERIFICAR SESIÓN ==================
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    header("Location: index.html");
    exit();
}
$admin_usuario = $_SESSION['usuario'];

// ================== CONEXIÓN A LA BASE DE DATOS ==================
$host = "localhost";
$user = "Omar";
$password = "Palomitas32$";
$dbname = "popcode";
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// ================== ACTUALIZAR ESTATUS ==================
if (isset($_POST['accion']) && $_POST['accion'] === 'toggle_estatus') {
    $no_colaborador = $_POST['no_colaborador'];
    $nuevoEstatus = $_POST['nuevo_estatus'];

    $update = $conn->prepare("UPDATE usuarios SET estatus = ? WHERE no_colaborador = ?");
    $update->bind_param("ss", $nuevoEstatus, $no_colaborador);
    $update->execute();
    exit(json_encode(["success" => true]));
}

// ================== ACTUALIZAR DATOS DE USUARIO ==================
if (isset($_POST['accion']) && $_POST['accion'] === 'editar_usuario') {
    $no_colaborador = $_POST['no_colaborador'];
    $nombre = $_POST['nombre_colaborador'];
    $rol = $_POST['rol_colaborador'];
    $area = $_POST['area'];
    $contrasena = $_POST['contrasena'];

    $stmt = $conn->prepare("UPDATE usuarios SET nombre_colaborador=?, rol_colaborador=?, area=?, contrasena=? WHERE no_colaborador=?");
    $stmt->bind_param("sssss", $nombre, $rol, $area, $contrasena, $no_colaborador);
    $stmt->execute();
    exit(json_encode(["success" => true]));
}

// ================== VALIDAR CONTRASEÑA DE ADMIN ==================
if (isset($_POST['accion']) && $_POST['accion'] === 'validar_admin') {
    $contrasena_admin = $_POST['contrasena_admin'];

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE nombre_colaborador=? AND rol_colaborador='Administrador' LIMIT 1");
    $stmt->bind_param("s", $admin_usuario);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && $res['contrasena'] === $contrasena_admin) {
        exit(json_encode(["valido" => true]));
    } else {
        exit(json_encode(["valido" => false]));
    }
}

// ================== CONSULTA DE USUARIOS ==================
$filtro = "";
$params = [];
$where = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && (!isset($_POST['accion']))) {
    $tipoUsuario = $_POST['tipoUsuario'] ?? "todos";
    $buscar = trim($_POST['buscar'] ?? "");

    if ($tipoUsuario !== "todos") {
        $where[] = "rol_colaborador = ?";
        $params[] = $tipoUsuario;
    }

    if (!empty($buscar)) {
        $where[] = "(nombre_colaborador LIKE ? OR no_colaborador LIKE ?)";
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
    }

    if (count($where) > 0) {
        $filtro = " AND " . implode(" AND ", $where);
    }
}

$sql = "SELECT * FROM usuarios WHERE 1 $filtro";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$totalUsuarios = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>POP CODE - Usuarios Registrados</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="est_usuarios.css">
<style>
/* Ajustes para bordes y scroll */
.tabla-scroll {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ccc;
    border-radius: 6px;
}
#tabla-usuarios th, #tabla-usuarios td {
    padding: 8px;
    text-align: center;
    border-bottom: 1px solid #ddd;
}
.modal {
    display: none;
    position: fixed;
    z-index: 999;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
}
.modal-content {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    width: 400px;
}
.password-container {
    position: relative;
}
.password-container .ojo {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
}
</style>
</head>

<body>
<header>
    <div class="admin-info">
<a href="../MainAdmin.php">
        <img src="../imagenes/SLIMPOP.png" alt="Logo Slimpop">
</a>
        <h1>Administrador: <?= htmlspecialchars($admin_usuario) ?></h1>
    </div>

    <div class="header-buttons">
        <button class="btn-header btn-regresar" type="button" onclick="window.location.href='MainAdmin.php'">
            <i class="fas fa-arrow-left"></i> Regresar
        </button>
        <button class="btn-header btn-cerrar" type="button" onclick="window.location.href='index.html'">
            <i class="fas fa-sign-out-alt"></i> Cerrar sesión
        </button>
    </div>
</header>

<div class="contenedor invertido">
<div class="panel-derecho">
    <h2>Usuarios Registrados</h2>

    <form method="POST" autocomplete="off">
        <label>Usuarios a mostrar:</label>
        <select name="tipoUsuario">
            <option value="todos">Todos</option>
            <option value="Administrador">Administrador</option>
            <option value="Supervisor">Supervisor</option>
            <option value="Operador">Operadores</option>
        </select>

        <label>Buscar:</label>
        <input type="text" name="buscar" placeholder="Nombre o número de colaborador">

        <div class="botones">
            <button type="submit">Filtrar</button>
            <button type="button" id="btn-reporte">Generar Reporte</button>
        </div>

        <div class="tabla-scroll">
        <table id="tabla-usuarios">
            <thead>
                <tr>
                    <th>No. Colaborador</th>
                    <th>Nombre</th>
                    <th>Rol</th>
                    <th>Área</th>
                    <th>Contraseña</th>
                    <th>Estatus</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr 
                    data-no="<?= htmlspecialchars($row['no_colaborador']) ?>"
                    data-nombre="<?= htmlspecialchars($row['nombre_colaborador']) ?>"
                    data-rol="<?= htmlspecialchars($row['rol_colaborador']) ?>"
                    data-area="<?= htmlspecialchars($row['area']) ?>"
                    data-contra="<?= htmlspecialchars($row['contrasena']) ?>"
                    data-estatus="<?= htmlspecialchars($row['estatus']) ?>"
                >
                    <td><?= htmlspecialchars($row['no_colaborador']) ?></td>
                    <td><?= htmlspecialchars($row['nombre_colaborador']) ?></td>
                    <td><?= htmlspecialchars($row['rol_colaborador']) ?></td>
                    <td><?= htmlspecialchars($row['area']) ?></td>
                    <td>******** <i class="fa fa-eye ver-pass" style="cursor:pointer;color:#0e96cc;"></i></td>
                    <td class="estatus"><?= htmlspecialchars($row['estatus']) ?></td>
                    <td>
                        <button type="button" class="editar-btn-tabla"><i class="fas fa-edit"></i> Editar</button>
                        <button type="button" class="inhabilitar-btn <?= $row['estatus'] === 'Desactivado' ? 'activar' : 'desactivar' ?>">
                            <i class="fas <?= $row['estatus'] === 'Desactivado' ? 'fa-user-check' : 'fa-user-slash' ?>"></i>
                            <?= $row['estatus'] === 'Desactivado' ? 'Activar' : 'Desactivar' ?>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7">No se encontraron usuarios.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </form>
</div>

<div class="panel-izquierdo">
    <div class="contador"><i class="fas fa-users"></i> Usuarios registrados: <?= $totalUsuarios ?></div>
    <h2>Registrar nuevo usuario</h2>

    <form method="POST" action="registrar_usuario.php" autocomplete="off">
        <label>Número de colaborador:</label>
        <input type="text" name="no_colaborador" required>

        <label>Nombre:</label>
        <input type="text" name="nombre_colaborador" required>

        <label>Rol:</label>
        <select name="rol_colaborador" required>
            <option value="">-Seleccionar Rol--</option>
            <option value="Administrador">Administrador</option>
            <option value="Supervisor">Supervisor</option>
            <option value="Operador">Operadores</option>
        </select>

        <label>Área:</label>
        <select name="area" required>
            <option value="">--Seleccionar Área--</option>
            <option value="TI">Tecnologías de la Información (T.I)</option>
            <option value="TH">Talento Humano (T.H)</option>
            <option value="Mant">Mantenimiento</option>
            <option value="Cal">Calidad</option>
            <option value="Almac">Almacén</option>
            <option value="Recepcion">Recepción</option>
        </select>

        <label>Contraseña:</label>
        <input type="text" name="contrasena" required>

        <div class="botones">
            <button type="submit" class="registrar-btn"><i class="fas fa-user-plus"></i> Registrar</button>
        </div>
    </form>
</div>
</div>

<!-- MODAL DE EDICIÓN -->
<div id="modal-editar" class="modal">
<div class="modal-content">
    <span class="close">&times;</span>
    <h2>Editar Usuario</h2>
    <form id="form-editar" autocomplete="off">
        <label>No. Colaborador:</label>
        <input type="text" name="no_colaborador" readonly>

        <label>Nombre:</label>
        <input type="text" name="nombre_colaborador" required>

        <label>Rol:</label>
        <select name="rol_colaborador" required>
            <option value="Administrador">Administrador</option>
            <option value="Supervisor">Supervisor</option>
            <option value="Operador">Operadores</option>
        </select>

        <label>Área:</label>
        <select name="area" required>
            <option value="TI">Tecnologías de la Información (T.I)</option>
            <option value="TH">Talento Humano (T.H)</option>
            <option value="Mant">Mantenimiento</option>
            <option value="Cal">Calidad</option>
            <option value="Almac">Almacén</option>
        </select>

        <label>Estatus:</label>
        <input type="text" name="estatus" readonly>

        <label>Contraseña:</label>
        <div class="password-container">
            <input type="password" name="contrasena" required>
            <i class="fa fa-eye ojo"></i>
        </div>

        <div class="botones">
            <button type="submit" class="guardar-btn-modal"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </form>
</div>
</div>
  <img src="../imagenes/carita_feliz_blanca.png" alt="Logo esquina" class="logo-esquina" style="width:65px; height:auto;">

<script>
// =================== MODAL DE EDICIÓN ===================
const modal = document.getElementById('modal-editar');
const closeBtn = modal.querySelector('.close');
const formEditar = document.getElementById('form-editar');

// Abrir modal al hacer clic en editar
document.querySelectorAll('.editar-btn-tabla').forEach(btn => {
  btn.addEventListener('click', e => {
    const fila = e.target.closest('tr');
    formEditar.no_colaborador.value = fila.dataset.no;
    formEditar.nombre_colaborador.value = fila.dataset.nombre;
    formEditar.rol_colaborador.value = fila.dataset.rol;
    formEditar.area.value = fila.dataset.area;
    formEditar.contrasena.value = fila.dataset.contra;
    formEditar.estatus.value = fila.dataset.estatus;
    modal.style.display = 'flex';
  });
});

// Cerrar modal
closeBtn.onclick = () => modal.style.display = 'none';
window.onclick = e => { if (e.target == modal) modal.style.display = 'none'; };

// =================== GUARDAR CAMBIOS ===================
formEditar.addEventListener('submit', async e => {
  e.preventDefault();
  const data = new FormData(formEditar);
  data.append('accion', 'editar_usuario');

  const res = await fetch('', { method: 'POST', body: data });
  const result = await res.json();
  if (result.success) {
    alert('Usuario actualizado correctamente.');
    location.reload();
  } else {
    alert('Error al actualizar usuario.');
  }
});

// =================== TOGGLE ESTATUS ===================
document.querySelectorAll(".inhabilitar-btn").forEach(btn => {
  btn.addEventListener("click", async () => {
    const fila = btn.closest("tr");
    const noColaborador = fila.dataset.no;
    const celdaEstatus = fila.querySelector(".estatus");
    const estatusActual = celdaEstatus.textContent.trim();
    const nuevoEstatus = estatusActual === "Activo" ? "Desactivado" : "Activo";

    const formData = new FormData();
    formData.append("accion", "toggle_estatus");
    formData.append("no_colaborador", noColaborador);
    formData.append("nuevo_estatus", nuevoEstatus);

    const respuesta = await fetch("", { method: "POST", body: formData });
    const data = await respuesta.json();
    if (data.success) location.reload();
  });
});

// =================== VER CONTRASEÑA ===================
document.querySelectorAll('.ver-pass').forEach(eye => {
  eye.addEventListener('click', async e => {
    const fila = e.target.closest('tr');
    const contrasenaReal = fila.dataset.contra;

    const contrasenaAdmin = prompt('Introduce la contraseña del administrador para ver la contraseña:');
    if (!contrasenaAdmin) return;

    const formData = new FormData();
    formData.append('accion', 'validar_admin');
    formData.append('contrasena_admin', contrasenaAdmin);

    const resp = await fetch('', { method: 'POST', body: formData });
    const data = await resp.json();
    if (data.valido) {
      alert('Contraseña: ' + contrasenaReal);
    } else {
      alert('Contraseña del administrador incorrecta');
    }
  });
});

// =================== VER/OCULTAR CONTRASEÑA MODAL (CON VALIDACIÓN) ===================
document.querySelector('.ojo').addEventListener('click', async e => {
    const input = formEditar.querySelector('input[name="contrasena"]');

    // pedir contraseña del administrador
    const contrasenaAdmin = prompt("Ingrese contraseña del administrador para ver la contraseña:");

    if (!contrasenaAdmin) return;

    const formData = new FormData();
    formData.append('accion', 'validar_admin');
    formData.append('contrasena_admin', contrasenaAdmin);

    const resp = await fetch('', { method: 'POST', body: formData });
    const data = await resp.json();

    if (!data.valido) {
        alert("Contraseña del administrador incorrecta");
        return;
    }
    // Si es correcta → mostrar u ocultar la contraseña
    input.type = (input.type === "password") ? "text" : "password";
});

</script>
</body>

<script>
function descargarPDF() {

    // ===== OCULTAR MOMENTANEAMENTE LA COLUMNA "ACCIONES" =====
    const indexAcciones = [...document.querySelectorAll("#tabla-usuarios th")]
        .findIndex(th => th.innerText.trim() === "Acciones");

    let columnasOcultadas = [];

    if (indexAcciones >= 0) {
        document.querySelectorAll(`#tabla-usuarios tr`).forEach(tr => {
            columnasOcultadas.push({
                tr: tr,
                celda: tr.children[indexAcciones],
                display: tr.children[indexAcciones].style.display
            });
            tr.children[indexAcciones].style.display = "none";
        });
    }

    // Obtener tabla sin columna Acciones
    const tablaHTML = document.getElementById("tabla-usuarios").outerHTML;

    // Restaurar columna en pantalla
    columnasOcultadas.forEach(obj => {
        obj.celda.style.display = obj.display;
    });

    // ===== CREACIÓN DEL PDF =====
    const contenido = `
        <html>
        <head>
<style>
    body {
        font-family: Arial;
        margin: 40px;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .logo {
        position: absolute;
        right: 20px;
        top: 10px;
        width: 70px;
    }

    h1 {
        margin-top: 100px;   /* Empuja el título debajo del logo */
        font-size: 20px;
        text-align: left;
        margin-bottom: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    th {
        background: #00AEEF !important;
        color: white !important;
        padding: 12px;
        font-size: 14px;
        border: none;
    }

    td {
        background: #F2F2F2 !important;
        padding: 12px;
        font-size: 12px;
        border: none;
        text-align: center;
    }

    tr:nth-child(even) td {
        background: #E9E9E9 !important;
    }

    @media print {
        body, th, td {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>


        </head>

        <body>

            <img src="../imagenes/SLIMPOP.png" class="logo">

            <h1>Reporte de Usuarios Registrados</h1>

            ${tablaHTML}

        </body>
        </html>
    `;

    const ventana = window.open("", "_blank");
    ventana.document.write(contenido);
    ventana.document.close();

    ventana.onload = function () {
        ventana.print();
        ventana.close();
    };
}
</script>
<script>
document.getElementById("btn-reporte").addEventListener("click", function(e) {
    e.preventDefault();  // Evita que el formulario se envíe
    descargarPDF();      // Llama al generador PDF
});
</script>

</html>
