<?php
session_start();
include 'conexion.php';

$usuario = $_SESSION['usuario'] ?? 'desconocido';

// Traer escaneos
$consulta = $conn->prepare("SELECT id_escaneado, lote_escaneado, nombre_colaborador_usuarios, fecha_escaneo, hora_escaneo, codigo_barras_producto, descripcion_producto, estado_escaneo FROM registros_escaneos ORDER BY fecha_escaneo ASC, hora_escaneo ASC");
$consulta->execute();
$resultado = $consulta->get_result();

// Traer operadores activos
$operadores = $conn->query("SELECT nombre_colaborador FROM usuarios WHERE estatus='activo' ORDER BY nombre_colaborador");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro de Escaneos</title>
  <link rel="stylesheet" href="repor_anad.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<header>
    <a href="../MainAdmin.php">
      <img src="../imagenes/SLIMPOP.png" alt="Logo esquina" class="logo-inferior" style="width:65px; height:auto;">
    </a>

  <h1>Registro de Escaneos</h1>
 <div class="header-buttons">
     <button class="logout-btn3" onclick="location.href='MainAdmin.php'">🏠︎</button>
    <button class="logout-btn2" onclick="location.href='escaneo_producto.php'">Regresar</button>
    <button class="logout-btn" onclick="location.href='logout.php'">Cerrar sesión</button>
  </div>
</header>

<main>
  <div class="filter-group">
    <div class="search-container">
      <input type="text" id="filtro" placeholder="Filtrar por lote, operador o descripción..." onkeyup="filtrarTabla()">
    </div>
    <div class="filter-buttons">
      <button class="btn-reporte" onclick="abrirModal()">Generar Reporte</button>
    </div>
  </div>
  <div class="tabla-scroll">
  <table id="tabla-escaneos">
    <thead>
      <tr>
        <th>ID</th>
        <th>Lote</th>
        <th>Operador</th>
        <th>Fecha</th>
        <th>Hora</th>
        <th>Código barras</th>
        <th>Descripción</th>
        <th>Estado</th>
      </tr>
    </thead>
    <tbody id="tb-body">
      <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
          <td><?= $row['id_escaneado'] ?></td>
          <td><?= $row['lote_escaneado'] ?></td>
          <td><?= $row['nombre_colaborador_usuarios'] ?></td>
          <td><?= $row['fecha_escaneo'] ?></td>
          <td><?= $row['hora_escaneo'] ?></td>
          <td><?= $row['codigo_barras_producto'] ?></td>
          <td><?= $row['descripcion_producto'] ?></td>
          <td><?= $row['estado_escaneo'] === 'Fallido' ? '<strong style="color:black;">' . htmlspecialchars($row['estado_escaneo']) . '</strong>' : htmlspecialchars($row['estado_escaneo']) ?>
          </td>

        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  </div>
</main>
  <img src="../imagenes/carita_feliz_blanca.png" alt="Logo esquina" class="logo-esquina" style="width:65px; height:auto;">

<!-- Modal de Reporte -->

<!-- Modal de Reporte -->
<div id="modal-reporte" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color: rgba(0,0,0,0.5); z-index:1000;">
  <div class="modal-content" style="background:#fff; margin:50px auto; padding:20px; border-radius:10px; width:90%; max-width:600px; position:relative;">
    <span class="close" onclick="cerrarModal()" style="position:absolute; top:10px; right:20px; cursor:pointer; font-size:24px;">&times;</span>
    <h2>Generar Reporte</h2>
    <form id="form-reporte">
      <!-- Producto -->
      <div style="margin-bottom:15px;">
        <label for="producto">Producto:</label>
        <select id="producto" name="producto">
          <option value="">Todos</option>
          <?php
          $productos = $conn->query("SELECT DISTINCT descripcion_producto FROM registros_escaneos ORDER BY descripcion_producto");
          while ($prod = $productos->fetch_assoc()) {
              echo "<option value='".htmlspecialchars($prod['descripcion_producto'])."'>".htmlspecialchars($prod['descripcion_producto'])."</option>";
          }
          ?>
        </select>
      </div>

      <!-- Operador -->
      <div style="margin-bottom:15px;">
        <label for="operador">Operador:</label>
        <select id="operador" name="operador">
          <option value="">Todos</option>
          <?php
          $op_activos = $conn->query("SELECT DISTINCT nombre_colaborador_usuarios FROM registros_escaneos ORDER BY nombre_colaborador_usuarios");
          while ($op = $op_activos->fetch_assoc()) {
              echo "<option value='".htmlspecialchars($op['nombre_colaborador_usuarios'])."'>".htmlspecialchars($op['nombre_colaborador_usuarios'])."</option>";
          }
          ?>
        </select>
      </div>

      <!-- Fecha -->
      <div style="margin-bottom:15px;">
        <label>Fecha inicio:</label>
        <input type="date" id="fecha_inicio" max="<?= date('Y-m-d') ?>">
        <label>Fecha fin:</label>
        <input type="date" id="fecha_fin" max="<?= date('Y-m-d') ?>">
      </div>

      <!-- Columnas a mostrar -->
      <div style="margin-bottom:15px;">
        <label style="font-weight:bold;">Columnas a incluir en el reporte:</label><br>
        <input type="checkbox" id="mostrar_lote" checked onchange="toggleColumna('lote')">
        <label for="mostrar_lote">Mostrar columna Lote</label><br>
        
        <input type="checkbox" id="mostrar_hora" checked onchange="toggleColumna('hora')">
        <label for="mostrar_hora">Mostrar columna Hora</label>
      </div>

      <!-- Formato de descarga -->
      <div style="margin-bottom:15px;">
        <label for="formato">Formato de descarga:</label>
        <select id="formato" name="formato">
          <option value="pdf">PDF</option>
          <option value="excel">Excel</option>
        </select>
      </div>

      <!-- Botón generar reporte -->
      <div style="text-align:right;">
        <button type="button" onclick="generarReporte()">Generar Reporte</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Variables globales para controlar columnas
  let columnasVisibles = {
    lote: true,
    hora: true
  };

  // Abrir y cerrar modal
  function abrirModal() {
    document.getElementById('modal-reporte').style.display = 'block';
  }
  function cerrarModal() {
    document.getElementById('modal-reporte').style.display = 'none';
  }

  // Controlar visibilidad de columnas
  function toggleColumna(columna) {
    columnasVisibles[columna] = !columnasVisibles[columna];
  }

  // Función para filtrar datos según los criterios seleccionados
  function filtrarDatos() {
    const producto = document.getElementById('producto').value;
    const operador = document.getElementById('operador').value;
    const fechaInicio = document.getElementById('fecha_inicio').value;
    const fechaFin = document.getElementById('fecha_fin').value;
    
    const filas = document.getElementById('tabla-escaneos').getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const datosFiltrados = [];

    for (let i = 0; i < filas.length; i++) {
      const celdas = filas[i].getElementsByTagName('td');
      const descripcionProducto = celdas[6].textContent || celdas[6].innerText;
      const nombreOperador = celdas[2].textContent || celdas[2].innerText;
      const fechaEscaneo = celdas[3].textContent || celdas[3].innerText;

      // Aplicar filtros
      let coincide = true;

      // Filtro por producto
      if (producto && descripcionProducto !== producto) {
        coincide = false;
      }

      // Filtro por operador
      if (operador && nombreOperador !== operador) {
        coincide = false;
      }

      // Filtro por fecha
      if (fechaInicio && fechaEscaneo < fechaInicio) {
        coincide = false;
      }
      if (fechaFin && fechaEscaneo > fechaFin) {
        coincide = false;
      }

      if (coincide) {
        datosFiltrados.push(filas[i]);
      }
    }

    return datosFiltrados;
  }

  // Función para dividir texto en líneas que caben en el ancho de la columna
  function dividirTexto(doc, texto, maxWidth) {
    const palabras = texto.split(' ');
    const lineas = [];
    let lineaActual = '';

    for (let i = 0; i < palabras.length; i++) {
      const palabra = palabras[i];
      const width = doc.getTextWidth(lineaActual + palabra + ' ');
      
      if (width < maxWidth) {
        lineaActual += palabra + ' ';
      } else {
        if (lineaActual !== '') {
          lineas.push(lineaActual.trim());
        }
        lineaActual = palabra + ' ';
      }
    }
    
    if (lineaActual !== '') {
      lineas.push(lineaActual.trim());
    }
    
    return lineas;
  }

  // Función principal para generar reporte
  function generarReporte() {
    const formato = document.getElementById('formato').value;
    
    if (formato === 'pdf') {
      generarPDF();
    } else if (formato === 'excel') {
      generarExcel();
    }
  }

  // Generar PDF con tabla que ajusta texto automáticamente
  function generarPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Configuración de página
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 10;
    const tableWidth = pageWidth - (margin * 2);

    // Logo a la derecha
    const img = new Image();
    img.src = '../imagenes/SLIMPOP.png';
    img.onload = function() {
      doc.addImage(img, 'PNG', pageWidth - 30, 5, 15, 15);

      let y = 30;
      doc.setFontSize(16);
      doc.text('Reporte de Escaneos', margin, y);
      y += 10;

      // Recoger filtros
      const producto = document.getElementById('producto').value;
      const operador = document.getElementById('operador').value;
      const fechaInicio = document.getElementById('fecha_inicio').value;
      const fechaFin = document.getElementById('fecha_fin').value;

      doc.setFontSize(10);
      doc.text(`Producto: ${producto || 'Todos'}`, margin, y); y += 5;
      doc.text(`Operador: ${operador || 'Todos'}`, margin, y); y += 5;
      doc.text(`Fecha Inicio: ${fechaInicio || '-'}`, margin, y); y += 5;
      doc.text(`Fecha Fin: ${fechaFin || '-'}`, margin, y); y += 10;

      // Obtener datos filtrados
      const datosFiltrados = filtrarDatos();
      
      if (datosFiltrados.length === 0) {
        doc.text('No se encontraron registros con los filtros aplicados', margin, y);
        doc.save('reporte_escaneos.pdf');
        return;
      }

      // Definir columnas con anchos proporcionales
      const columnas = [
        { key: 'id', header: 'ID', visible: true, width: 0.08 },
        { key: 'lote', header: 'Lote', visible: columnasVisibles.lote, width: 0.12 },
        { key: 'operador', header: 'Operador', visible: true, width: 0.15 },
        { key: 'fecha', header: 'Fecha', visible: true, width: 0.12 },
        { key: 'hora', header: 'Hora', visible: columnasVisibles.hora, width: 0.10 },
        { key: 'codigo', header: 'Código Barras', visible: true, width: 0.18 },
        { key: 'descripcion', header: 'Descripción', visible: true, width: 0.15 },
        { key: 'estado', header: 'Estado', visible: true, width: 0.10 }
      ];

      // Filtrar columnas visibles y calcular anchos reales
      const columnasVisiblesArray = columnas.filter(col => col.visible);
      const totalWidthRatio = columnasVisiblesArray.reduce((sum, col) => sum + col.width, 0);
      
      columnasVisiblesArray.forEach(col => {
        col.actualWidth = (col.width / totalWidthRatio) * tableWidth;
        col.maxTextWidth = col.actualWidth - 4; // Margen interno de 2mm cada lado
      });

      // ENCABEZADO AZUL de la tabla
      const startX = margin;
      let x = startX;
      
      doc.setFillColor(0, 173, 238);
      doc.rect(startX, y, tableWidth, 8, 'F');
      doc.setTextColor(255, 255, 255);
      doc.setFontSize(8);
      doc.setFont(undefined, 'bold');
      
      x = startX;
      columnasVisiblesArray.forEach((col) => {
        const textWidth = doc.getTextWidth(col.header);
        const textX = x + (col.actualWidth - textWidth) / 2;
        doc.text(col.header, textX, y + 5);
        x += col.actualWidth;
      });
      
      y += 8;

      // Datos de la tabla con ajuste automático de texto
      doc.setTextColor(0, 0, 0);
      doc.setFontSize(7);
      doc.setFont(undefined, 'normal');
      
      for (let i = 0; i < datosFiltrados.length; i++) {
        const celdas = datosFiltrados[i].getElementsByTagName('td');
        
        // Calcular altura máxima necesaria para esta fila
        let maxLineas = 1;
        const lineasPorColumna = [];
        
        let colIndex = 0;
        columnas.forEach((col, index) => {
          if (col.visible) {
            const texto = celdas[index].textContent || celdas[index].innerText;
            const lineas = dividirTexto(doc, texto, col.maxTextWidth);
            lineasPorColumna.push(lineas);
            maxLineas = Math.max(maxLineas, lineas.length);
            colIndex++;
          }
        });

        // Calcular altura de la fila
        const alturaFila = 4 + (maxLineas * 3); // 4mm base + 3mm por línea adicional

        // Verificar si hay espacio en la página
        if (y + alturaFila > pageHeight - 20) {
          doc.addPage();
          y = 20;
          
          // Redibujar encabezados
          doc.setFillColor(0, 173, 238);
          doc.rect(startX, y, tableWidth, 8, 'F');
          doc.setTextColor(255, 255, 255);
          doc.setFontSize(8);
          doc.setFont(undefined, 'bold');
          
          x = startX;
          columnasVisiblesArray.forEach(col => {
            const textWidth = doc.getTextWidth(col.header);
            const textX = x + (col.actualWidth - textWidth) / 2;
            doc.text(col.header, textX, y + 5);
            x += col.actualWidth;
          });
          
          y += 8;
          doc.setTextColor(0, 0, 0);
          doc.setFontSize(7);
          doc.setFont(undefined, 'normal');
        }

        // Fondo alterno para la fila
        if (i % 2 === 0) {
          doc.setFillColor(240, 240, 240);
          doc.rect(startX, y, tableWidth, alturaFila, 'F');
        }

        // Dibujar contenido de cada celda
        x = startX;
        colIndex = 0;
        columnas.forEach((col, index) => {
          if (col.visible) {
            const lineas = lineasPorColumna[colIndex];
            const texto = celdas[index].textContent || celdas[index].innerText;
            
            // Verificar si es la columna de Estado y el texto es "Fallido"
            if (col.key === 'estado' && texto.trim().toLowerCase() === 'fallido') {
              doc.setFont(undefined, 'bold'); // Texto en negrita para Fallido
            } else {
              doc.setFont(undefined, 'normal'); // Texto normal para otros estados
            }
            
            // Dibujar cada línea de texto
            for (let lineaIndex = 0; lineaIndex < lineas.length; lineaIndex++) {
              const lineaY = y + 3 + (lineaIndex * 3);
              doc.text(lineas[lineaIndex], x + 2, lineaY);
            }
            
            x += col.actualWidth;
            colIndex++;
          }
        });

        // Restaurar fuente normal para las siguientes filas
        doc.setFont(undefined, 'normal');

        // Línea inferior de la fila
        doc.setDrawColor(200, 200, 200);
        doc.line(startX, y + alturaFila, startX + tableWidth, y + alturaFila);
        
        y += alturaFila;
      }

      doc.save('reporte_escaneos.pdf');
    };
  }

  // Generar Excel
  function generarExcel() {
    // Obtener datos filtrados
    const datosFiltrados = filtrarDatos();
    
    if (datosFiltrados.length === 0) {
      Swal.fire({
        icon: 'info',
        title: 'Sin datos',
        text: 'No se encontraron registros con los filtros aplicados'
      });
      return;
    }

    // Definir columnas basado en selección
    const columnas = [
      { key: 'id', header: 'ID', visible: true },
      { key: 'lote', header: 'Lote', visible: columnasVisibles.lote },
      { key: 'operador', header: 'Operador', visible: true },
      { key: 'fecha', header: 'Fecha', visible: true },
      { key: 'hora', header: 'Hora', visible: columnasVisibles.hora },
      { key: 'codigo', header: 'Código Barras', visible: true },
      { key: 'descripcion', header: 'Descripción', visible: true },
      { key: 'estado', header: 'Estado', visible: true }
    ];

    // Filtrar columnas visibles
    const columnasVisiblesArray = columnas.filter(col => col.visible);
    
    // Crear datos para Excel
    const datos = [];
    
    // Información de filtros
    const producto = document.getElementById('producto').value;
    const operador = document.getElementById('operador').value;
    const fechaInicio = document.getElementById('fecha_inicio').value;
    const fechaFin = document.getElementById('fecha_fin').value;
    
    datos.push(['Reporte de Escaneos']);
    datos.push(['Producto:', producto || 'Todos']);
    datos.push(['Operador:', operador || 'Todos']);
    datos.push(['Fecha Inicio:', fechaInicio || '-']);
    datos.push(['Fecha Fin:', fechaFin || '-']);
    datos.push([]);
    
    // Encabezados
    const encabezados = columnasVisiblesArray.map(col => col.header);
    datos.push(encabezados);
    
    // Datos filtrados
    for (let i = 0; i < datosFiltrados.length; i++) {
      const celdas = datosFiltrados[i].getElementsByTagName('td');
      const filaDatos = [];
      
      columnas.forEach((col, index) => {
        if (col.visible) {
          const texto = celdas[index].textContent || celdas[index].innerText;
          filaDatos.push(texto);
        }
      });
      
      datos.push(filaDatos);
    }

    // Crear libro de Excel
    const ws = XLSX.utils.aoa_to_sheet(datos);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Escaneos");
    
    // Descargar archivo
    XLSX.writeFile(wb, 'reporte_escaneos.xlsx');
  }

// Función para filtrar tabla por lote, operador o descripción
function filtrarTabla() {
    const input = document.getElementById('filtro');
    const filtro = input.value.toLowerCase();
    const tabla = document.getElementById('tabla-escaneos');
    const filas = tabla.getElementsByTagName('tr');

    // Empezar desde 1 para saltar el encabezado
    for (let i = 1; i < filas.length; i++) {
        const celdas = filas[i].getElementsByTagName('td');
        let mostrarFila = false;

        // Verificar cada celda en las columnas que quieres filtrar
        for (let j = 0; j < celdas.length; j++) {
            const celda = celdas[j];
            // Filtrar solo por lote (columna 1), operador (columna 2) y descripción (columna 6)
            if (j === 1 || j === 2 || j === 6) {
                if (celda) {
                    const texto = celda.textContent || celda.innerText;
                    if (texto.toLowerCase().indexOf(filtro) > -1) {
                        mostrarFila = true;
                        break; // Si encuentra coincidencia en una columna, no necesita verificar las demás
                    }
                }
            }
        }

        // Mostrar u ocultar la fila según si coincide con el filtro
        if (mostrarFila) {
            filas[i].style.display = '';
        } else {
            filas[i].style.display = 'none';
        }
    }
}
</script>

<style>
  .modal-content select, .modal-content input[type=date] {
    width: 100%;
    padding: 5px;
    margin-top:5px;
  }
</style>

</body>
</html>