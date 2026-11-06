// Filtro del selector de consultores en asignaciones.php.
(() => {
  'use strict';

  const buscador = document.getElementById('buscador-consultores');
  const entrada = document.getElementById('buscar-consultor');
  const limpiar = document.getElementById('limpiar-consultor');
  const selector = document.getElementById('consultor_id');
  const resultados = document.getElementById('resultados-consultores');
  const guardar = document.getElementById('guardar-asignacion');

  if (!buscador || !entrada || !limpiar || !selector || !resultados || !guardar) {
    return;
  }

  function normalizar(texto) {
    return texto
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLocaleLowerCase('es')
      .trim();
  }

  const opciones = Array.from(selector.options)
    .filter(opcion => opcion.value !== '')
    .map(opcion => ({
      valor: opcion.value,
      texto: opcion.textContent.trim(),
      busqueda: normalizar(opcion.textContent)
    }));

  if (opciones.length === 0) {
    return;
  }

  const textoInicial = selector.options[0].textContent.trim();
  const guardadoBloqueado = guardar.disabled;

  function filtrarConsultores() {
    const palabras = normalizar(entrada.value).split(/\s+/).filter(Boolean);
    const seleccionAnterior = selector.value;

    const visibles = opciones.filter(opcion =>
      palabras.every(palabra => opcion.busqueda.includes(palabra))
    );

    const fragmento = document.createDocumentFragment();
    fragmento.appendChild(new Option(textoInicial, ''));

    visibles.forEach(opcion => {
      fragmento.appendChild(new Option(opcion.texto, opcion.valor));
    });

    selector.replaceChildren(fragmento);

    selector.value = visibles.some(opcion => opcion.valor === seleccionAnterior)
      ? seleccionAnterior
      : '';

    guardar.disabled = guardadoBloqueado || visibles.length === 0;

    resultados.textContent = visibles.length > 0
      ? `${resultados.dataset.disponibles}: ${visibles.length}`
      : resultados.dataset.sinResultados;
  }

  entrada.addEventListener('input', filtrarConsultores);
  entrada.addEventListener('search', filtrarConsultores);

  entrada.addEventListener('keydown', evento => {
    if (evento.key === 'Enter') {
      evento.preventDefault();
      selector.focus();
    }
  });

  limpiar.addEventListener('click', () => {
    entrada.value = '';
    filtrarConsultores();
    entrada.focus();
  });

  filtrarConsultores();
  buscador.hidden = false;
  resultados.hidden = false;
})();
