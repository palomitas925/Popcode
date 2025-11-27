<?php 
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Supervisor') {
  header("Location: index.html");
  exit;
}

include 'conexion.php';

// ========= CONSULTA =========
$sql = "SELECT codigo_barras, descripcion, imagen, fecha FROM productos_escanear ORDER BY fecha DESC";
$result = $conn->query($sql);

$productos = [];
while ($row = $result->fetch_assoc()) {
  $row['imagen_url'] = "imagenes_productos/" . $row['imagen'];
  $productos[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
    
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>POP CODE - Listado de Productos</title>

  <!-- Librerías externas -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <link rel="stylesheet" href="repor_anad.css">
</head>

<body>
<header>
  <img src="../imagenes/SLIMPOP.png" alt="Logo esquina" class="logo-inferior" style="width:65px; height:auto;">
  <h1>Listado de productos</h1>
  <div class="botones">
      <button class="logout-btn3" onclick="location.href='MainSuperCal.php'">🏠︎</button>
      <button class="logout-btn2" onclick="window.location.href='añadir_SuperCal.php'">Regresar</button>
      <button class="logout-btn" onclick="window.location.href='logout.php'">Cerrar sesión</button>      
      </div>
</header>

<main>
  <div class="filter-group">
    <div class="search-container">
      <input type="text" id="filtro" placeholder="Filtro de datos por descripción..." onkeyup="filtrarTabla()">
    </div>
    <div class="filter-buttons">
      <button class="btn-reporte" onclick="abrirModal()">Generar reporte</button>
    </div>
  </div>

  <div class="tabla-scroll">
  <table id="tabla-productos">
    <thead>
      <tr>
        <th>Código de barras</th>
        <th>Descripción</th>
        <th>Imagen</th>
        <th>Fecha</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody id="tb-body"></tbody>
  </table>
 </div>
</main>

<!-- Modal Reporte -->
<div class="modal" id="modal-reporte">
  <div class="modal-content">
    <label for="fecha-inicio">Fecha inicio:</label>
    <input type="date" id="fecha-inicio">
    <label for="fecha-final">Fecha final:</label>
    <input type="date" id="fecha-final">
    <label for="producto-select">Producto:</label>
    <select id="producto-select"><option value="Todos">Todos</option></select>
    <label for="formato">Formato:</label>
    <select id="formato"><option value="PDF">PDF</option><option value="XLSX">XLSX</option></select>
    <button onclick="generarReporte()">Generar</button>
    <button onclick="document.getElementById('modal-reporte').style.display='none'">Cancelar</button>
  </div>
</div>

<!-- Modal Editar -->
<div class="modal" id="modal-editar">
  <div class="modal-content">
    <label for="edit-codigo">Código de barras:</label>
    <input type="text" id="edit-codigo" readonly>
    <label for="edit-descripcion">Descripción:</label>
    <input type="text" id="edit-descripcion">
    <label for="edit-imagen">Nueva imagen:</label>
    <input type="file" id="edit-imagen" accept="image/*">
    <button onclick="guardarEdicion()">Guardar cambios</button>
    <button onclick="cerrarModalEdicion()">Cancelar</button>
  </div>
</div>
<img src="../imagenes/carita_feliz_blanca.png" alt="Logo esquina" class="logo-esquina" style="width:65px; height:auto;">

<script>
  const productos = <?php echo json_encode($productos); ?>;
  const tbody = document.getElementById('tb-body');
  const prodSelect = document.getElementById('producto-select');
  const dateStart = document.getElementById('fecha-inicio');
  const dateEnd = document.getElementById('fecha-final');
  const formatoSel = document.getElementById('formato');
  let filaEditando = null;

  // === RESTRINGIR FECHAS FUTURAS ===
  const hoy = new Date().toISOString().split("T")[0];
  document.getElementById('fecha-inicio').setAttribute('max', hoy);
  document.getElementById('fecha-final').setAttribute('max', hoy);

  // === LLENAR TABLA ===
  function llenarTabla() {
    productos.forEach(prod => {
      tbody.insertAdjacentHTML('beforeend', `
        <tr>
          <td>${prod.codigo_barras}</td>
          <td>${prod.descripcion}</td>
          <td><img src="${prod.imagen_url}" class="product-img" crossorigin="anonymous"></td>
          <td>${prod.fecha}</td>
          <td>
            <button class="action-btn edit-btn" onclick="abrirEditar(this)">Editar</button>
            <button class="action-btn delete-btn" onclick="eliminarFila(this)">Eliminar</button>
          </td>
        </tr>
      `);
      const opt = document.createElement('option');
      opt.value = prod.descripcion;
      opt.textContent = prod.descripcion;
      prodSelect.appendChild(opt);
    });
  }
  llenarTabla();

  // === FILTRAR ===
  function filtrarTabla() {
    const filtro = document.getElementById('filtro').value.toLowerCase();
    document.querySelectorAll('#tb-body tr').forEach(tr => {
      tr.style.display = tr.cells[1].textContent.toLowerCase().includes(filtro) ? '' : 'none';
    });
  }

  function abrirModal() {
    document.getElementById('modal-reporte').style.display = 'block';
  }

  function abrirEditar(btn) {
    filaEditando = btn.closest('tr');
    document.getElementById('edit-codigo').value = filaEditando.cells[0].textContent;
    document.getElementById('edit-descripcion').value = filaEditando.cells[1].textContent;
    document.getElementById('edit-imagen').value = '';
    document.getElementById('modal-editar').style.display = 'block';
  }

  async function guardarEdicion() {
    if (!filaEditando) return;

    const codigo = document.getElementById('edit-codigo').value;
    const descripcion = document.getElementById('edit-descripcion').value;
    const fileInput = document.getElementById('edit-imagen');

    const formData = new FormData();
    formData.append('codigo_barras', codigo);
    formData.append('descripcion', descripcion);
    if (fileInput.files.length > 0) {
      formData.append('imagen', fileInput.files[0]);
    }

    try {
      const res = await fetch('actualizar_producto.php', {
        method: 'POST',
        body: formData
      });

      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      let data = await res.json();
      if (data.success) {
        filaEditando.cells[1].textContent = descripcion;
        if (data.nueva_imagen) {
          filaEditando.cells[2].innerHTML = `<img src="${data.nueva_imagen}?t=${Date.now()}" class="product-img">`;
        }

        Swal.fire({
          title: '¡Actualizado!',
          text: 'El producto se actualizó correctamente.',
          icon: 'success',
          confirmButtonColor: '#3085d6',
          confirmButtonText: 'Aceptar'
        });
        cerrarModalEdicion();
      } else {
        Swal.fire({
          title: 'Error',
          text: data.message || 'No se pudo actualizar el producto.',
          icon: 'error',
          confirmButtonColor: '#d33',
          confirmButtonText: 'Aceptar'
        });
      }
    } catch (error) {
      console.error('Error:', error);
      Swal.fire({
        title: 'Error de conexión',
        text: 'No se pudo conectar con el servidor.',
        icon: 'error',
        confirmButtonColor: '#d33',
        confirmButtonText: 'Aceptar'
      });
    }
  }

  async function eliminarFila(btn) {
    const fila = btn.closest('tr');
    const codigo = fila.cells[0].textContent;

    Swal.fire({
      title: '¿Estás seguro?',
      text: `¿Deseas eliminar el producto con código: ${codigo}?`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then(async (result) => {
      if (result.isConfirmed) {
        try {
          const res = await fetch('eliminar_producto.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'codigo_barras=' + encodeURIComponent(codigo)
          });
          const data = await res.json();

          if (data.success) {
            fila.remove();
            Swal.fire({
              title: 'Eliminado',
              text: 'El producto ha sido eliminado correctamente.',
              icon: 'success',
              confirmButtonColor: '#3085d6',
              confirmButtonText: 'Aceptar'
            });
          } else {
            Swal.fire({
              title: 'Error',
              text: 'No se pudo eliminar el producto.',
              icon: 'error',
              confirmButtonColor: '#d33',
              confirmButtonText: 'Aceptar'
            });
          }
        } catch (error) {
          console.error('Error:', error);
          Swal.fire({
            title: 'Error de conexión',
            text: 'No se pudo conectar con el servidor.',
            icon: 'error',
            confirmButtonColor: '#d33',
            confirmButtonText: 'Aceptar'
          });
        }
      }
    });
  }

  function cerrarModalEdicion() {
    document.getElementById('modal-editar').style.display = 'none';
    filaEditando = null;
  }

  // === GENERAR REPORTE ===
  async function generarReporte() {
    const selProd = prodSelect.value;
    const start = dateStart.value;
    const end = dateEnd.value;

    const hoy = new Date().toISOString().split("T")[0];
    if ((start && start > hoy) || (end && end > hoy)) {
      alert("No puedes seleccionar una fecha futura.");
      return;
    }

    const filas = Array.from(document.querySelectorAll('#tabla-productos tbody tr'));
    const filtradas = filas.filter(row => {
      const fecha = row.cells[3].textContent;
      const desc = row.cells[1].textContent;
      let ok = true;
      if (selProd !== 'Todos' && desc !== selProd) ok = false;
      if (start && fecha < start) ok = false;
      if (end && fecha > end) ok = false;
      return ok;
    });

    if (filtradas.length === 0) {
      Swal.fire({
        icon: 'info',
        title: 'Sin datos',
        text: 'No se encontraron registros con los filtros aplicados'
      });
      return;
    }

    // Mostrar mensaje de carga
    Swal.fire({
      title: 'Generando reporte...',
      text: 'Por favor espere',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    try {
      if (formatoSel.value === 'PDF') {
        await generarPDF(filtradas, selProd, start, end);
      } else {
        await generarExcel(filtradas, selProd, start, end);
      }
      
      Swal.close();
    } catch (error) {
      console.error('Error al generar reporte:', error);
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'No se pudo generar el reporte: ' + error.message
      });
    }

    document.getElementById('modal-reporte').style.display = 'none';
  }

  // Función para cargar imagen
  function cargarImagen(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = "Anonymous";
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Error al cargar imagen'));
      img.src = src;
    });
  }

  // Función para dividir texto
  function dividirTexto(doc, texto, maxWidth) {
    if (!texto) return [''];
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
    
    return lineas.length > 0 ? lineas : [''];
  }

  // Función separada para generar PDF
  async function generarPDF(filtradas, selProd, start, end) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    // Configuración de página
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 10;
    const tableWidth = pageWidth - (margin * 2);

    // Logo a la derecha (con manejo de errores)
    try {
      const logo = new Image();
      logo.src = "../imagenes/SLIMPOP.png";
      await new Promise((resolve, reject) => {
        logo.onload = resolve;
        logo.onerror = reject;
        setTimeout(() => reject(new Error('Timeout cargando logo')), 3000);
      });
      doc.addImage(logo, "PNG", pageWidth - 30, 5, 15, 15);
    } catch (error) {
      console.log('Logo no cargado, continuando sin él...');
    }

    let y = 30;
    doc.setFontSize(16);
    doc.text('Reporte de Productos Registrados', margin, y);
    y += 10;

    // Información de filtros
    doc.setFontSize(10);
    doc.text(`Producto: ${selProd || 'Todos'}`, margin, y); y += 5;
    doc.text(`Fecha Inicio: ${start || '-'}`, margin, y); y += 5;
    doc.text(`Fecha Fin: ${end || '-'}`, margin, y); y += 10;

    // Definir columnas (incluyendo imagen y fecha)
    const columnas = [
      { key: 'codigo', header: 'Código de barras', visible: true, width: 0.25 },
      { key: 'descripcion', header: 'Descripción', visible: true, width: 0.30 },
      { key: 'imagen', header: 'Imagen', visible: true, width: 0.25 },
      { key: 'fecha', header: 'Fecha', visible: true, width: 0.20 }
    ];

    // Calcular anchos reales
    columnas.forEach(col => {
      col.actualWidth = col.width * tableWidth;
      col.maxTextWidth = col.actualWidth - 4;
    });

    // ENCABEZADO AZUL de la tabla
    const startX = margin;
    let x = startX;
    
    doc.setFillColor(0, 173, 238); // #00ADEE
    doc.rect(startX, y, tableWidth, 8, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(8);
    doc.setFont(undefined, 'bold');
    
    x = startX;
    columnas.forEach((col) => {
      const textWidth = doc.getTextWidth(col.header);
      const textX = x + (col.actualWidth - textWidth) / 2;
      doc.text(col.header, textX, y + 5);
      x += col.actualWidth;
    });
    
    y += 8;

    // Datos de la tabla
    doc.setTextColor(0, 0, 0);
    doc.setFontSize(7);
    doc.setFont(undefined, 'normal');
    
    for (let i = 0; i < filtradas.length; i++) {
      const celdas = filtradas[i].cells;
      
      // Calcular altura máxima necesaria para esta fila
      let maxLineas = 1;
      const lineasPorColumna = [];
      let alturaImagen = 20; // Altura base para imágenes
      
      // Procesar texto para calcular altura
      for (let colIndex = 0; colIndex < columnas.length; colIndex++) {
        const col = columnas[colIndex];
        
        if (col.key === 'imagen') {
          // Para imágenes, procesamos después
          lineasPorColumna.push([]);
        } else {
          const texto = celdas[colIndex].textContent || celdas[colIndex].innerText || '';
          const lineas = dividirTexto(doc, texto, col.maxTextWidth);
          lineasPorColumna.push(lineas);
          maxLineas = Math.max(maxLineas, lineas.length);
        }
      }

      // Procesar imagen para determinar altura real
      const imgElement = celdas[2].querySelector('img');
      if (imgElement && imgElement.src) {
        try {
          const img = await cargarImagen(imgElement.src);
          // Calcular dimensiones proporcionales
          const maxImgWidth = columnas[2].actualWidth - 6;
          const maxImgHeight = 25;
          
          const ratio = img.width / img.height;
          let imgHeight = maxImgWidth / ratio;
          
          if (imgHeight > maxImgHeight) {
            imgHeight = maxImgHeight;
          }
          
          alturaImagen = Math.max(imgHeight + 4, 15);
        } catch (error) {
          console.log('Error al cargar imagen:', error);
        }
      }

      const alturaFila = Math.max(4 + (maxLineas * 3), alturaImagen);

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
        columnas.forEach(col => {
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
      for (let colIndex = 0; colIndex < columnas.length; colIndex++) {
        const col = columnas[colIndex];
        
        if (col.key === 'imagen') {
          // Procesar imagen
          const imgElement = celdas[2].querySelector('img');
          if (imgElement && imgElement.src) {
            try {
              const img = await cargarImagen(imgElement.src);
              
              // Calcular dimensiones proporcionales
              const maxImgWidth = col.actualWidth - 6;
              const maxImgHeight = alturaFila - 6;
              
              const ratio = img.width / img.height;
              let imgWidth = maxImgWidth;
              let imgHeight = maxImgWidth / ratio;
              
              if (imgHeight > maxImgHeight) {
                imgHeight = maxImgHeight;
                imgWidth = maxImgHeight * ratio;
              }
              
              // Centrar la imagen en la celda
              const imgX = x + (col.actualWidth - imgWidth) / 2;
              const imgY = y + (alturaFila - imgHeight) / 2;
              
              doc.addImage(img, 'JPEG', imgX, imgY, imgWidth, imgHeight);
            } catch (error) {
              // Si hay error con la imagen, mostrar texto
              doc.text('[Imagen]', x + 2, y + alturaFila / 2);
            }
          } else {
            doc.text('[Sin imagen]', x + 2, y + alturaFila / 2);
          }
        } else {
          // Procesar texto normal
          const lineas = lineasPorColumna[colIndex];
          const texto = celdas[colIndex].textContent || celdas[colIndex].innerText;
          
          // Centrar texto verticalmente
          const startY = y + (alturaFila - (lineas.length * 3)) / 2;
          for (let lineaIndex = 0; lineaIndex < lineas.length; lineaIndex++) {
            const lineaY = startY + (lineaIndex * 3);
            doc.text(lineas[lineaIndex], x + 2, lineaY);
          }
        }
        
        x += col.actualWidth;
      }

      // Línea inferior de la fila
      doc.setDrawColor(200, 200, 200);
      doc.line(startX, y + alturaFila, startX + tableWidth, y + alturaFila);
      
      y += alturaFila;
    }

    doc.save('reporte_productos.pdf');
  }

  // Función separada para generar Excel
  async function generarExcel(filtradas, selProd, start, end) {
    const wb = XLSX.utils.book_new();
    const datos = [];
    
    // Información de filtros
    datos.push(['Reporte de Productos Registrados']);
    datos.push(['Producto:', selProd || 'Todos']);
    datos.push(['Fecha Inicio:', start || '-']);
    datos.push(['Fecha Fin:', end || '-']);
    datos.push([]);
    
    // Encabezados
    datos.push(['Código de barras', 'Descripción', 'Imagen', 'Fecha']);
    
    // Datos filtrados
    for (let i = 0; i < filtradas.length; i++) {
      const celdas = filtradas[i].cells;
      const imgElement = celdas[2].querySelector('img');
      const imagenUrl = imgElement ? imgElement.src : 'Sin imagen';
      
      const filaDatos = [
        celdas[0].textContent,
        celdas[1].textContent,
        imagenUrl,
        celdas[3].textContent
      ];
      datos.push(filaDatos);
    }

    const ws = XLSX.utils.aoa_to_sheet(datos);
    XLSX.utils.book_append_sheet(wb, ws, "Productos");
    XLSX.writeFile(wb, "reporte_productos.xlsx");
  }

  window.onclick = function (event) {
    const modalRep = document.getElementById('modal-reporte');
    const modalEdt = document.getElementById('modal-editar');
    if (event.target === modalRep) modalRep.style.display = 'none';
    if (event.target === modalEdt) modalEdt.style.display = 'none';
  };
</script>
</body>
</html>