function mostrarFormulario(tipo) {
  document.querySelectorAll('.formulario').forEach(f => f.classList.remove('active'));
  document.getElementById(`formulario-${tipo}`).classList.add('active');
}

function generarTicket() {
  alert("Ticket generado. Imprimiendo etiqueta 2.5x5...");
  // Aquí puedes usar window.print() o redirigir a generar_ticket.php
}
$(document).ready(main);

var contador = 1;

function main () {
	$('.menu_bar').click(function(){
		if (contador == 1) {
			$('nav').animate({
				left: '0'
			});
			contador = 0;
		} else {
			contador = 1;
			$('nav').animate({
				left: '-100%'
			});
		}
	});

	// Mostramos y ocultamos submenus
	$('.submenu').click(function(){
		$(this).children('.children').slideToggle();
	});
}
