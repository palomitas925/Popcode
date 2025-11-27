<?php
include 'conexion.php';

$tipo = $_POST['tipo'] ?? '';
$producto = $_POST['producto'] ?? '';
$operador = $_POST['operador'] ?? '';
$fecha_inicio = $_POST['fecha_inicio'] ?? '';
$fecha_final = $_POST['fecha_final'] ?? '';

if (!$fecha_inicio || !$fecha_final) {
    die("Fechas requeridas.");
}

// === CONSTRUIR CONSULTA ===
$query = "SELECT id_escaneado, lote_escaneado, nombre_colaborador_usuarios, fecha_escaneo, hora_escaneo, codigo_barras_producto, descripcion_producto, estado_escaneo
          FROM registros_escaneos 
          WHERE fecha_escaneo BETWEEN ? AND ?";

$params = [$fecha_inicio, $fecha_final];
$types = "ss";

if (!empty($producto)) {
    $query .= " AND codigo_barras_producto = ?";
    $params[] = $producto;
    $types .= "s";
}
if (!empty($operador)) {
    $query .= " AND nombre_colaborador_usuarios = ?";
    $params[] = $operador;
    $types .= "s";
}
$query .= " ORDER BY fecha_escaneo ASC, hora_escaneo ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$registros = $result->fetch_all(MYSQLI_ASSOC);

if (empty($registros)) {
    die("No se encontraron datos para el rango seleccionado.");
}

// === EXPORTAR SEGÚN TIPO ===
if ($tipo === 'excel') {
    // ===== EXCEL =====
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=reporte_escaneos.xls");
    echo "<table border='1'>";
    echo "<tr>
            <th>ID</th><th>Lote</th><th>Operador</th><th>Fecha</th><th>Hora</th>
            <th>Código</th><th>Descripción</th><th>Estado</th>
          </tr>";
    foreach ($registros as $row) {
        echo "<tr>
                <td>{$row['id_escaneado']}</td>
                <td>{$row['lote_escaneado']}</td>
                <td>{$row['nombre_colaborador_usuarios']}</td>
                <td>{$row['fecha_escaneo']}</td>
                <td>{$row['hora_escaneo']}</td>
                <td>{$row['codigo_barras_producto']}</td>
                <td>{$row['descripcion_producto']}</td>
                <td>{$row['estado_escaneo']}</td>
              </tr>";
    }
    echo "</table>";
    exit;
} elseif ($tipo === 'pdf') {
    // ===== PDF =====
    require_once('fpdf/fpdf.php'); // asegúrate de tener FPDF en tu proyecto

    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'Reporte de Escaneos', 0, 1, 'C');
            $this->Ln(5);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(10, 8, 'ID', 1);
            $this->Cell(25, 8, 'Lote', 1);
            $this->Cell(35, 8, 'Operador', 1);
            $this->Cell(20, 8, 'Fecha', 1);
            $this->Cell(20, 8, 'Hora', 1);
            $this->Cell(30, 8, 'Codigo', 1);
            $this->Cell(40, 8, 'Descripcion', 1);
            $this->Cell(20, 8, 'Estado', 1);
            $this->Ln();
        }
    }

    $pdf = new PDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 9);

    foreach ($registros as $row) {
        $pdf->Cell(10, 8, $row['id_escaneado'], 1);
        $pdf->Cell(25, 8, $row['lote_escaneado'], 1);
        $pdf->Cell(35, 8, utf8_decode($row['nombre_colaborador_usuarios']), 1);
        $pdf->Cell(20, 8, $row['fecha_escaneo'], 1);
        $pdf->Cell(20, 8, $row['hora_escaneo'], 1);
        $pdf->Cell(30, 8, $row['codigo_barras_producto'], 1);
        $pdf->Cell(40, 8, utf8_decode($row['descripcion_producto']), 1);
        $pdf->Cell(20, 8, $row['estado_escaneo'], 1);
        $pdf->Ln();
    }

    $pdf->Output('D', 'reporte_escaneos.pdf');
    exit;
} else {
    echo "Tipo de reporte no válido.";
}
