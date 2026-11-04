// Fecha actual del computador
const hoy = new Date();
const anio = hoy.getFullYear();
const mes = String(hoy.getMonth() + 1).padStart(2, "0");
const dia = String(hoy.getDate()).padStart(2, "0");

// Elementos del panel
const selectorMes = document.getElementById("mes");
const campoFecha = document.getElementById("fecha");
const campoProyecto = document.getElementById("proyecto");
const campoHoras = document.getElementById("horas");
const campoDescripcion = document.getElementById("descripcion");
const formulario = document.getElementById("horas-form");
const tabla = document.getElementById("tabla-registros");
const totalHoras = document.getElementById("total-horas");
const mensaje = document.getElementById("mensaje-horas");

// Datos temporales: se borran al actualizar la página
const registros = [];

selectorMes.value = `${anio}-${mes}`;
campoFecha.value = `${anio}-${mes}-${dia}`;

// Mostrar las horas con formato español
function mostrarHoras(centesimas) {
  return (centesimas / 100).toLocaleString("es-CL", {
    maximumFractionDigits: 2
  });
}

// Actualizar la tabla y el total del mes seleccionado
function actualizarTabla() {
  const registrosDelMes = registros.filter(function (registro) {
    return registro.fecha.slice(0, 7) === selectorMes.value;
  });

  tabla.replaceChildren();

  let total = 0;

  registrosDelMes.forEach(function (registro) {
    const fila = document.createElement("tr");

    const valores = [
      registro.fecha.split("-").reverse().join("/"),
      registro.proyecto,
      registro.descripcion,
      mostrarHoras(registro.centesimas)
    ];

    valores.forEach(function (valor, posicion) {
      const celda = document.createElement("td");
      celda.textContent = valor;

      if (posicion === 3) {
        celda.className = "text-end";
      }

      fila.appendChild(celda);
    });

    tabla.appendChild(fila);
    total += registro.centesimas;
  });

  totalHoras.textContent = `${mostrarHoras(total)} h`;

  if (registrosDelMes.length === 0) {
    const fila = document.createElement("tr");
    const celda = document.createElement("td");

    celda.colSpan = 4;
    celda.className = "text-center text-secondary py-4";
    celda.textContent = "No hay horas registradas para este mes.";

    fila.appendChild(celda);
    tabla.appendChild(fila);
  }
}

// Registrar las horas de prueba
formulario.addEventListener("submit", function (evento) {
  evento.preventDefault();

  if (!formulario.reportValidity()) {
    return;
  }

  const descripcion = campoDescripcion.value.trim();
  const centesimas = Math.round(campoHoras.valueAsNumber * 100);

  if (descripcion === "") {
    mensaje.textContent = "Escribe una descripción del trabajo.";
    return;
  }

  if (!Number.isFinite(centesimas) || centesimas <= 0) {
    mensaje.textContent = "Ingresa una cantidad válida de horas.";
    return;
  }

  // Evitar superar 24 horas acumuladas en un mismo día
  const totalDelDia = registros
    .filter(registro => registro.fecha === campoFecha.value)
    .reduce((total, registro) => total + registro.centesimas, 0);

  if (totalDelDia + centesimas > 2400) {
    mensaje.textContent = "El total del día no puede superar 24 horas.";
    return;
  }

  registros.push({
    fecha: campoFecha.value,
    proyecto: campoProyecto.selectedOptions[0].textContent,
    descripcion: descripcion,
    centesimas: centesimas
  });

  // Mostrar el mes del registro que acabamos de agregar
  selectorMes.value = campoFecha.value.slice(0, 7);

  actualizarTabla();

  campoHoras.value = "";
  campoDescripcion.value = "";

  mensaje.textContent =
    "Registro agregado a la prueba. Se borrará al actualizar la página.";
});

// Consultar otro mes
selectorMes.addEventListener("change", function () {
  actualizarTabla();
  mensaje.textContent = "";
});

actualizarTabla();