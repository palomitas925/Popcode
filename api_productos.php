<?php
include("conexion.php");

$accion = $_POST['accion'] ?? '';
$id = $_POST['id_producto_inventario'] ?? '';

switch ($accion) {

    // ✅ REGISTRAR PRODUCTO
    case 'registrar':
        $campos = [
            'id_producto_inventario', 'id_subcategoria', 'linea_equipo', 'area', 'departamento_equipo',
            'nombre_posicion_equipo', 'ubicacion', 'estado', 'hostname_equipo', 'tipo_computadora_equipo',
            'modelo', 'etiqueta_servicio_equipo', 'express_code_equipo', 'procesador_equipo',
            'generacion_equipo', 'nucleos', 'memoria_equipo', 'tipo_disco', 'almacenamiento_equipo',
            'sistema_operativo_equipo', 'cargador_equipo', 'IPv4_v6_equipo', 'observaciones'
        ];

        $datos = [];
        foreach ($campos as $campo) {
            $datos[$campo] = $_POST[$campo] ?? null;
        }

        $sql = "INSERT INTO productos (" . implode(",", array_keys($datos)) . ")
                VALUES ('" . implode("','", array_values($datos)) . "')";

        if ($conn->query($sql)) {
            echo json_encode(["ok" => true, "msg" => "Producto registrado"]);
        } else {
            echo json_encode(["ok" => false, "msg" => "Error: " . $conn->error]);
        }
        break;

    // ✅ ACTUALIZAR PRODUCTO
    case 'actualizar':
        $set = [];
        foreach ($_POST as $k => $v) {
            if ($k !== "accion" && $k !== "id_producto_inventario") {
                $set[] = "$k='" . $conn->real_escape_string($v) . "'";
            }
        }
        $sql = "UPDATE productos SET " . implode(",", $set) . " WHERE id_producto_inventario='$id'";
        $conn->query($sql);
        echo json_encode(["ok" => true, "msg" => "Actualizado correctamente"]);
        break;

    // ✅ ELIMINAR PRODUCTO
    case 'eliminar':
        $conn->query("DELETE FROM productos WHERE id_producto_inventario='$id'");
        echo json_encode(["ok" => true, "msg" => "Eliminado correctamente"]);
        break;

    // ✅ LISTAR PRODUCTOS
    case 'listar':
        $result = $conn->query("SELECT * FROM productos");
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode($data);
        break;
}
?>
