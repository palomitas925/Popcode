<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'conexion.php'; // usa $conn de tipo mysqli

/**
 * Función auxiliar: Reordenar campos garantizando:
 * 1. Identificador SIEMPRE primero
 * 2. Cantidad SIEMPRE segundo
 * 3. Resto de campos después
 * 
 * @param array $campos - array de nombres de campos
 * @param string $identificador - nombre del campo identificador
 * @return array - campos reordenados
 */
function reordenarCampos($campos, $identificador) {
    if (!is_array($campos)) {
        $campos = [];
    }
    
    // Normalizar y limpiar
    $campos = array_map('trim', $campos);
    $campos = array_filter($campos, function($c) { return $c !== ''; });
    $campos = array_values($campos); // reindexar
    
    // Paso 1: Remover identificador y Cantidad del array si existen
    $resultado = [];
    foreach ($campos as $c) {
        $cLower = mb_strtolower($c);
        $idLower = mb_strtolower($identificador ?? '');
        
        // Saltar si es el identificador o Cantidad
        if ($cLower === $idLower || $cLower === 'cantidad') {
            continue;
        }
        
        $resultado[] = $c;
    }
    
    // Paso 2: Construir array en orden correcto
    // Primero: Identificador
    $ordenado = [];
    if ($identificador) {
        $ordenado[] = trim($identificador);
    }
    
    // Segundo: Cantidad (SIEMPRE)
    $ordenado[] = 'Cantidad';
    
    // Tercero: Resto de campos
    $ordenado = array_merge($ordenado, $resultado);
    
    return $ordenado;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    try {
        // === Obtener lista de inventarios ===
        if ($action === 'getInventarios') {
            $sql = "SELECT id, nombre FROM inventarios";
            $result = $conn->query($sql);
            if (!$result) {
                throw new Exception("Error en la consulta: " . $conn->error);
            }
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            exit;
        }

        // === Obtener categorías de un inventario ===
        if ($action === 'getCategorias') {
            $inventario_id = isset($_POST['id_inventario']) ? $_POST['id_inventario'] : 0;
            $inventario_id = (int)filter_var($inventario_id, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
            if ($inventario_id <= 0) {
                echo json_encode([]);
                exit;
            }

            $stmt = $conn->prepare("SELECT id, nombre FROM categorias WHERE inventario_id = ?");
            $stmt->bind_param("i", $inventario_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            $stmt->close();
            exit;
        }

        // === Obtener campos de una categoría ===
        if ($action === 'getCampos') {
            $categoria_id = isset($_POST['id_categoria']) ? $_POST['id_categoria'] : 0;
            $categoria_id = (int)filter_var($categoria_id, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
            if ($categoria_id <= 0) {
                echo json_encode([]);
                exit;
            }

            $stmt = $conn->prepare("SELECT nombre FROM campos WHERE id_categoria = ? ORDER BY orden ASC, id ASC");
            $stmt->bind_param("i", $categoria_id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            $stmt->close();
            exit;
        }

        // === Obtener registros de una categoría ===
        if ($action === 'getRegistros') {
            $categoria_id = isset($_POST['id_categoria']) ? $_POST['id_categoria'] : 0;
            $categoria_id = (int)filter_var($categoria_id, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
            if ($categoria_id <= 0) {
                echo json_encode([]);
                exit;
            }

            $stmt = $conn->prepare("SELECT * FROM registros WHERE categoria_id = ? ORDER BY id DESC");
            $stmt->bind_param("i", $categoria_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Decodificar JSON si existe columna 'datos'
            foreach ($rows as &$row) {
                if (isset($row['datos'])) {
                    $json = json_decode($row['datos'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                        $row = array_merge(['id' => $row['id']], $json);
                    }
                }
            }
            echo json_encode($rows);
            exit;
        }

        // === Crear inventario + categoría opcional + campos opcionales ===
        if ($action === 'crearInventario') {
            $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
            $identificador = isset($_POST['identificador']) ? trim($_POST['identificador']) : '';
            $categoria = isset($_POST['categoria']) ? trim($_POST['categoria']) : '';
            $camposRaw = $_POST['campos'] ?? null;
            $campos = [];

            // Normalizar campos: puede venir como JSON string o como array
            if ($camposRaw !== null) {
                if (is_string($camposRaw)) {
                    $decoded = json_decode($camposRaw, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $campos = $decoded;
                    } else {
                        $campos = array_filter(array_map('trim', explode(',', $camposRaw)));
                    }
                } elseif (is_array($camposRaw)) {
                    $campos = $camposRaw;
                }
            }

            // Reordenar campos: GARANTIZAR orden correcto (identificador primero, Cantidad segundo)
            $campos = reordenarCampos($campos, $identificador);

            if ($nombre === '') {
                throw new Exception('El nombre del inventario es requerido.');
            }

            // Normalizar nombre para búsqueda (opcional: case-insensitive)
            // Buscar inventario existente con mismo nombre
            $stmtCheck = $conn->prepare("SELECT id, identificador FROM inventarios WHERE nombre = ? LIMIT 1");
            if (!$stmtCheck) throw new Exception("Prepare check inventario falló: " . $conn->error);
            $stmtCheck->bind_param("s", $nombre);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            $existingInv = $resCheck->fetch_assoc();
            $stmtCheck->close();

            // Empezar transacción (abriremos siempre para consistencia)
            $conn->begin_transaction();
            try {
                if ($existingInv) {
                    // Inventario ya existe: usaremos ese id
                    $id_inventario = (int)$existingInv['id'];
                    $inventarioObj = [
                        'id' => $id_inventario,
                        'nombre' => $nombre,
                        'identificador' => $existingInv['identificador'] ?? $identificador
                    ];

                    $categoriaObj = null;
                    if ($categoria !== '') {
                        // verificar si la categoría ya existe en ese inventario
                        $stmtCatCheck = $conn->prepare("SELECT id FROM categorias WHERE inventario_id = ? AND nombre = ? LIMIT 1");
                        if (!$stmtCatCheck) throw new Exception("Prepare check categoria falló: " . $conn->error);
                        $stmtCatCheck->bind_param("is", $id_inventario, $categoria);
                        $stmtCatCheck->execute();
                        $resCatCheck = $stmtCatCheck->get_result();
                        $existingCat = $resCatCheck->fetch_assoc();
                        $stmtCatCheck->close();

                        if ($existingCat) {
                            // categoría ya existe: devolverla (no insertar campos nuevos automáticamente)
                            $id_categoria = (int)$existingCat['id'];
                            $categoriaObj = ['id' => $id_categoria, 'nombre' => $categoria, 'campos' => []];
                        } else {
                            // crear nueva categoría bajo inventario existente
                            $stmt2 = $conn->prepare("INSERT INTO categorias (inventario_id, nombre) VALUES (?, ?)");
                            if (!$stmt2) throw new Exception("Prepare categorias falló: " . $conn->error);
                            $stmt2->bind_param("is", $id_inventario, $categoria);
                            if (!$stmt2->execute()) {
                                $stmt2->close();
                                throw new Exception("Insert categoria falló: " . $stmt2->error);
                            }
                            $id_categoria = $conn->insert_id;
                            $stmt2->close();

                            // insertar campos asociados a la nueva categoría
                            if (!empty($campos) && is_array($campos)) {
                                $stmtField = $conn->prepare("INSERT INTO campos (id_categoria, nombre, orden) VALUES (?, ?, ?)");
                                if (!$stmtField) throw new Exception("Prepare campos falló: " . $conn->error);
                                $orden = 0;
                                foreach ($campos as $c) {
                                    $cTrim = trim((string)$c);
                                    if ($cTrim === '') { $orden++; continue; }
                                    $stmtField->bind_param("isi", $id_categoria, $cTrim, $orden);
                                    if (!$stmtField->execute()) {
                                        $stmtField->close();
                                        throw new Exception("Insert campo falló: " . $stmtField->error);
                                    }
                                    $orden++;
                                }
                                $stmtField->close();
                            }

                            $categoriaObj = [
                                'id' => $id_categoria,
                                'nombre' => $categoria,
                                'campos' => $campos
                            ];
                        }
                    }

                    $conn->commit();
                    echo json_encode(['inventario' => $inventarioObj, 'categoria' => $categoriaObj]);
                    exit;
                } else {
                    // No existe inventario: crear inventario nuevo (comportamiento anterior)
                    $stmt = $conn->prepare("INSERT INTO inventarios (nombre, identificador) VALUES (?, ?)");
                    if (!$stmt) throw new Exception("Prepare inventarios falló: " . $conn->error);
                    $identificadorParam = $identificador !== '' ? $identificador : null;
                    $stmt->bind_param("ss", $nombre, $identificadorParam);
                    if (!$stmt->execute()) {
                        $stmt->close();
                        throw new Exception("Insert inventario falló: " . $stmt->error);
                    }
                    $id_inventario = $conn->insert_id;
                    $stmt->close();

                    $categoriaObj = null;
                    if ($categoria !== '') {
                        $stmt2 = $conn->prepare("INSERT INTO categorias (inventario_id, nombre) VALUES (?, ?)");
                        if (!$stmt2) {
                            $conn->rollback();
                            throw new Exception("Prepare categorias falló: " . $conn->error);
                        }
                        $stmt2->bind_param("is", $id_inventario, $categoria);
                        if (!$stmt2->execute()) {
                            $stmt2->close();
                            $conn->rollback();
                            throw new Exception("Insert categoria falló: " . $stmt2->error);
                        }
                        $id_categoria = $conn->insert_id;
                        $stmt2->close();

                        if (!empty($campos) && is_array($campos)) {
                            $stmtField = $conn->prepare("INSERT INTO campos (id_categoria, nombre, orden) VALUES (?, ?, ?)");
                            if (!$stmtField) {
                                $conn->rollback();
                                throw new Exception("Prepare campos falló: " . $conn->error);
                            }
                            $orden = 0;
                            foreach ($campos as $c) {
                                $cTrim = trim((string)$c);
                                if ($cTrim === '') { $orden++; continue; }
                                $stmtField->bind_param("isi", $id_categoria, $cTrim, $orden);
                                if (!$stmtField->execute()) {
                                    $stmtField->close();
                                    $conn->rollback();
                                    throw new Exception("Insert campo falló: " . $stmtField->error);
                                }
                                $orden++;
                            }
                            $stmtField->close();
                        }

                        $categoriaObj = [
                            'id' => $id_categoria,
                            'nombre' => $categoria,
                            'campos' => $campos
                        ];
                    }

                    $conn->commit();

                    $inventarioObj = [
                        'id' => $id_inventario,
                        'nombre' => $nombre,
                        'identificador' => $identificador
                    ];
                    echo json_encode(['inventario' => $inventarioObj, 'categoria' => $categoriaObj]);
                    exit;
                }
            } catch (Exception $eInner) {
                if ($conn->in_transaction) $conn->rollback();
                throw $eInner;
            }
        }

        // === Crear registro ===
        if ($action === 'crearRegistro') {
            $id_categoria = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : 0;
            $datosRaw = $_POST['datos'] ?? '{}';
            
            if ($id_categoria <= 0) throw new Exception('ID de categoría inválido.');
            
            // Normalizar datos: puede venir como JSON string o array
            $datos = [];
            if (is_string($datosRaw)) {
                $decoded = json_decode($datosRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $datos = $decoded;
                }
            } elseif (is_array($datosRaw)) {
                $datos = $datosRaw;
            }
            
            $datosJson = json_encode($datos, JSON_UNESCAPED_UNICODE);
            $stmt = $conn->prepare("INSERT INTO registros (categoria_id, datos) VALUES (?, ?)");
            if (!$stmt) throw new Exception("Prepare registros falló: " . $conn->error);
            $stmt->bind_param("is", $id_categoria, $datosJson);
            if (!$stmt->execute()) throw new Exception("Insert registro falló: " . $stmt->error);
            $id_registro = $conn->insert_id;
            $stmt->close();
            
            echo json_encode(['id_registro' => $id_registro, 'datos' => $datos]);
            exit;
        }

        // === Editar registro ===
        if ($action === 'editarRegistro') {
            $id_registro = isset($_POST['id_registro']) ? (int)$_POST['id_registro'] : 0;
            $datosRaw = $_POST['datos'] ?? '{}';
            
            if ($id_registro <= 0) throw new Exception('ID de registro inválido.');
            
            $datos = [];
            if (is_string($datosRaw)) {
                $decoded = json_decode($datosRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $datos = $decoded;
                }
            } elseif (is_array($datosRaw)) {
                $datos = $datosRaw;
            }
            
            $datosJson = json_encode($datos, JSON_UNESCAPED_UNICODE);
            $stmt = $conn->prepare("UPDATE registros SET datos = ? WHERE id = ?");
            if (!$stmt) throw new Exception("Prepare UPDATE falló: " . $conn->error);
            $stmt->bind_param("si", $datosJson, $id_registro);
            if (!$stmt->execute()) throw new Exception("Update registro falló: " . $stmt->error);
            $stmt->close();
            
            echo json_encode(['id_registro' => $id_registro, 'datos' => $datos]);
            exit;
        }

        // === Eliminar registro ===
        if ($action === 'eliminarRegistro') {
            $id_registro = isset($_POST['id_registro']) ? (int)$_POST['id_registro'] : 0;
            if ($id_registro <= 0) throw new Exception('ID de registro inválido.');
            
            $stmt = $conn->prepare("DELETE FROM registros WHERE id = ?");
            if (!$stmt) throw new Exception("Prepare DELETE falló: " . $conn->error);
            $stmt->bind_param("i", $id_registro);
            if (!$stmt->execute()) throw new Exception("Delete registro falló: " . $stmt->error);
            $stmt->close();
            
            echo json_encode(['success' => true]);
            exit;
        }

        // === Crear retiro ===
        if ($action === 'create_retiro') {
            $id_categoria = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : 0;
            $articulo_id = isset($_POST['articulo_id']) ? (int)$_POST['articulo_id'] : 0;
            $no_colaborador = isset($_POST['no_colaborador']) ? trim($_POST['no_colaborador']) : null;
            $cantidad = isset($_POST['cantidad']) ? (int)$_POST['cantidad'] : null;
            $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : null;

            if ($id_categoria <= 0 || $articulo_id <= 0) throw new Exception('ID de categoría o artículo inválido.');
            if ($cantidad === null) throw new Exception('Cantidad requerida para el retiro.');

            // obtener nombres de inventario y categoría
            $stmt = $conn->prepare("SELECT c.nombre AS categoria_nombre, i.nombre AS inventario_nombre, i.id AS inventario_id FROM categorias c LEFT JOIN inventarios i ON c.inventario_id = i.id WHERE c.id = ? LIMIT 1");
            if (!$stmt) throw new Exception('Prepare get names falló: ' . $conn->error);
            $stmt->bind_param('i', $id_categoria);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            $inventario_nombre = $row['inventario_nombre'] ?? null;
            $categoria_nombre = $row['categoria_nombre'] ?? null;

            $conn->begin_transaction();
            try {
                // Antes de crear el retiro: comprobar y actualizar el stock en la tabla registros
                $stmtReg = $conn->prepare("SELECT datos FROM registros WHERE id = ? LIMIT 1");
                if (!$stmtReg) throw new Exception('Prepare fetch registro falló: ' . $conn->error);
                $stmtReg->bind_param('i', $articulo_id);
                $stmtReg->execute();
                $resReg = $stmtReg->get_result();
                $regRow = $resReg->fetch_assoc();
                $stmtReg->close();

                $datosRegistro = [];
                if ($regRow && isset($regRow['datos'])) {
                    $decoded = json_decode($regRow['datos'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $datosRegistro = $decoded;
                    }
                }

                // buscar campo 'Cantidad' (case-insensitive) y extraer cantidad disponible
                $cantidadDisponible = 0;
                $campoCantidadKey = null;
                foreach ($datosRegistro as $k => $v) {
                    if (is_string($k) && mb_strtolower(trim($k)) === 'cantidad') {
                        $campoCantidadKey = $k;
                        // extraer primer número entero que aparezca en el valor
                        if (is_numeric($v)) {
                            $cantidadDisponible = (int)$v;
                        } else {
                            // buscar primer número en la cadena (por ejemplo '3 - 2')
                            if (preg_match('/(\d+)/', (string)$v, $m)) {
                                $cantidadDisponible = (int)$m[1];
                            } else {
                                $cantidadDisponible = 0;
                            }
                        }
                        break;
                    }
                }

                if ($cantidad > $cantidadDisponible) {
                    throw new Exception('Cantidad a retirar mayor a la existencia (' . $cantidadDisponible . ').');
                }

                // calcular restante y actualizar el campo en el registro: acumular retiros
                $restante = $cantidadDisponible - $cantidad;
                if ($campoCantidadKey !== null) {
                    $prevVal = $datosRegistro[$campoCantidadKey];
                    $prev_remaining = 0;
                    $prev_withdrawn = 0;
                    if (is_numeric($prevVal)) {
                        $prev_remaining = (int)$prevVal;
                        $prev_withdrawn = 0;
                    } else {
                        // buscar formato "X - Y"
                        if (preg_match('/(\d+)\s*-\s*(\d+)/', (string)$prevVal, $mm)) {
                            $prev_remaining = (int)$mm[1];
                            $prev_withdrawn = (int)$mm[2];
                        } elseif (preg_match('/(\d+)/', (string)$prevVal, $mm)) {
                            $prev_remaining = (int)$mm[1];
                            $prev_withdrawn = 0;
                        }
                    }

                    $new_withdrawn = $prev_withdrawn + $cantidad;
                    $new_remaining = $prev_remaining - $cantidad;
                    if ($new_remaining < 0) $new_remaining = 0;

                    $datosRegistro[$campoCantidadKey] = $new_remaining . ' - ' . $new_withdrawn;

                    // actualizar la fila de registros con los nuevos datos JSON
                    $newJson = json_encode($datosRegistro, JSON_UNESCAPED_UNICODE);
                    $stmtUpdReg = $conn->prepare("UPDATE registros SET datos = ? WHERE id = ?");
                    if (!$stmtUpdReg) throw new Exception('Prepare update registro falló: ' . $conn->error);
                    $stmtUpdReg->bind_param('si', $newJson, $articulo_id);
                    if (!$stmtUpdReg->execute()) {
                        $stmtUpdReg->close();
                        throw new Exception('Update registro falló: ' . $stmtUpdReg->error);
                    }
                    $stmtUpdReg->close();
                }

                $fecha_retiro = date('Y-m-d H:i:s');
                $stmtIns = $conn->prepare("INSERT INTO historial (operacion, codigo_retiro, fecha_retiro, no_colaborador, inventario, categoria, articulo_id, cantidad, observaciones) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmtIns) throw new Exception('Prepare insert historial falló: ' . $conn->error);
                $oper = 'retiro';
                $stmtIns->bind_param('sssssiis', $oper, $fecha_retiro, $no_colaborador, $inventario_nombre, $categoria_nombre, $articulo_id, $cantidad, $observaciones);
                if (!$stmtIns->execute()) {
                    $stmtIns->close();
                    throw new Exception('Insert historial falló: ' . $stmtIns->error);
                }
                $insertId = $conn->insert_id;
                $stmtIns->close();

                // actualizar codigo_retiro con el id insertado (autoincrementable solicitado)
                $stmtUpd = $conn->prepare("UPDATE historial SET codigo_retiro = ? WHERE id = ?");
                if (!$stmtUpd) throw new Exception('Prepare update codigo_retiro falló: ' . $conn->error);
                $stmtUpd->bind_param('ii', $insertId, $insertId);
                if (!$stmtUpd->execute()) {
                    $stmtUpd->close();
                    throw new Exception('Update codigo_retiro falló: ' . $stmtUpd->error);
                }
                $stmtUpd->close();

                $conn->commit();
                echo json_encode(['codigo_retiro' => $insertId, 'id' => $insertId]);
                exit;
            } catch (Exception $eInner) {
                if ($conn->in_transaction) $conn->rollback();
                throw $eInner;
            }
        }

        // === Obtener datos de un retiro por codigo_retiro ===
        if ($action === 'get_retiro') {
            $codigo = isset($_POST['codigo_retiro']) ? (int)$_POST['codigo_retiro'] : 0;
            if ($codigo <= 0) throw new Exception('Código de retiro inválido.');
            $stmt = $conn->prepare("SELECT * FROM historial WHERE codigo_retiro = ? AND operacion = 'retiro' LIMIT 1");
            if (!$stmt) throw new Exception('Prepare get_retiro falló: ' . $conn->error);
            $stmt->bind_param('i', $codigo);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();
            if (!$row) throw new Exception('No se encontró retiro con ese código.');
            echo json_encode($row);
            exit;
        }

        // === Crear devolución vinculada a un retiro ===
        if ($action === 'create_devolucion') {
            $codigo = isset($_POST['codigo_retiro']) ? (int)$_POST['codigo_retiro'] : 0;
            if ($codigo <= 0) throw new Exception('Código de retiro inválido.');

            // buscar retiro original
            $stmt = $conn->prepare("SELECT * FROM historial WHERE codigo_retiro = ? AND operacion = 'retiro' LIMIT 1");
            if (!$stmt) throw new Exception('Prepare fetch retiro falló: ' . $conn->error);
            $stmt->bind_param('i', $codigo);
            $stmt->execute();
            $res = $stmt->get_result();
            $retiro = $res->fetch_assoc();
            $stmt->close();
            if (!$retiro) throw new Exception('Retiro no encontrado para ese código.');

            $fecha_devolucion = isset($_POST['fecha_devolucion']) && trim($_POST['fecha_devolucion']) !== '' ? trim($_POST['fecha_devolucion']) : date('Y-m-d H:i:s');
            $cantidadDev = isset($_POST['cantidad']) && $_POST['cantidad'] !== '' ? (int)$_POST['cantidad'] : (int)($retiro['cantidad'] ?? 0);
            $no_colaborador = $retiro['no_colaborador'] ?? null;
            $inventario_nombre = $retiro['inventario'] ?? null;
            $categoria_nombre = $retiro['categoria'] ?? null;
            $articulo_id = isset($retiro['articulo_id']) ? (int)$retiro['articulo_id'] : null;
            $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : null;

            $conn->begin_transaction();
            try {
                $oper = 'devolucion';
                $stmtIns = $conn->prepare("INSERT INTO historial (operacion, codigo_retiro, fecha_retiro, fecha_devolucion, no_colaborador, inventario, categoria, articulo_id, cantidad, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmtIns) throw new Exception('Prepare insert devolucion falló: ' . $conn->error);
                $fecha_retiro = $retiro['fecha_retiro'] ?? null;
                // types: oper(s), codigo(i), fecha_retiro(s), fecha_devolucion(s), no_colaborador(s), inventario(s), categoria(s), articulo_id(i), cantidad(i), observaciones(s)
                $stmtIns->bind_param('sisssssiis', $oper, $codigo, $fecha_retiro, $fecha_devolucion, $no_colaborador, $inventario_nombre, $categoria_nombre, $articulo_id, $cantidadDev, $observaciones);
                // Note: binding types above may need slight adjustment; use s/i types where appropriate
                if (!$stmtIns->execute()) {
                    $stmtIns->close();
                    throw new Exception('Insert devolucion falló: ' . $stmtIns->error);
                }
                $stmtIns->close();

                // actualizar registro: sumar cantidad devuelta al campo 'Cantidad' si existe
                if ($articulo_id) {
                    $stmtReg = $conn->prepare("SELECT datos FROM registros WHERE id = ? LIMIT 1");
                    if ($stmtReg) {
                        $stmtReg->bind_param('i', $articulo_id);
                        $stmtReg->execute();
                        $resReg = $stmtReg->get_result();
                        $regRow = $resReg->fetch_assoc();
                        $stmtReg->close();

                        $datosRegistro = [];
                        if ($regRow && isset($regRow['datos'])) {
                            $decoded = json_decode($regRow['datos'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $datosRegistro = $decoded;
                            }
                        }

                        // buscar clave 'Cantidad' (case-insensitive) y ajustar retirados/restante
                        foreach ($datosRegistro as $k => $v) {
                            if (is_string($k) && mb_strtolower(trim($k)) === 'cantidad') {
                                $prevVal = $v;
                                $prev_remaining = 0;
                                $prev_withdrawn = 0;
                                if (is_numeric($prevVal)) {
                                    $prev_remaining = (int)$prevVal;
                                    $prev_withdrawn = 0;
                                } else {
                                    if (preg_match('/(\d+)\s*-\s*(\d+)/', (string)$prevVal, $mm)) {
                                        $prev_remaining = (int)$mm[1];
                                        $prev_withdrawn = (int)$mm[2];
                                    } elseif (preg_match('/(\d+)/', (string)$prevVal, $mm)) {
                                        $prev_remaining = (int)$mm[1];
                                        $prev_withdrawn = 0;
                                    }
                                }

                                // calcular nueva cantidad devuelta: disminuye el total retirado y aumenta el restante
                                $new_withdrawn = $prev_withdrawn - (int)$cantidadDev;
                                if ($new_withdrawn < 0) $new_withdrawn = 0;
                                $new_remaining = $prev_remaining + (int)$cantidadDev;

                                if ($new_withdrawn > 0) {
                                    $datosRegistro[$k] = $new_remaining . ' - ' . $new_withdrawn;
                                } else {
                                    $datosRegistro[$k] = (string)$new_remaining;
                                }

                                $newJson = json_encode($datosRegistro, JSON_UNESCAPED_UNICODE);
                                $stmtUpd = $conn->prepare("UPDATE registros SET datos = ? WHERE id = ?");
                                if ($stmtUpd) {
                                    $stmtUpd->bind_param('si', $newJson, $articulo_id);
                                    $stmtUpd->execute();
                                    $stmtUpd->close();
                                }
                                break;
                            }
                        }
                    }
                }

                $conn->commit();
                echo json_encode(['success' => true]);
                exit;
            } catch (Exception $eInner) {
                if ($conn->in_transaction) $conn->rollback();
                throw $eInner;
            }
        }

        // === Obtener historial completo ===
        if ($action === 'get_historial') {
            $stmt = $conn->prepare("SELECT id, fecha_operacion, operacion, codigo_retiro, no_colaborador, inventario, categoria, articulo_id, cantidad, observaciones, fecha_retiro, fecha_devolucion FROM historial ORDER BY fecha_operacion DESC LIMIT 1000");
            if (!$stmt) throw new Exception('Prepare get_historial falló: ' . $conn->error);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            echo json_encode($rows);
            exit;
        }

        // === Acción no reconocida ===
        throw new Exception('Acción no soportada.');
    } catch (Exception $e) {
        if (isset($conn) && $conn->connect_errno === 0) {
            // si estamos en transacción, rollback seguro
            if ($conn->in_transaction) {
                @$conn->rollback();
            }
        }
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
?>
