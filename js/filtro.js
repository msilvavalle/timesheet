// Filtra las listas de proyectos y consultores sin cambiar las casillas marcadas.
document.querySelectorAll('input[data-filtro]').forEach((entrada) => {
  const lista = document.getElementById(entrada.dataset.filtro);
  const caja = entrada.closest('[data-filtro-caja]');
  const vacio = entrada.form?.querySelector('[data-filtro-vacio]');

  if (!lista) return;

  const normalizar = (texto) => texto
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('es')
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .trim();

  const filtrar = () => {
    const palabras = normalizar(entrada.value).split(/\s+/).filter(Boolean);

    lista.querySelectorAll('[data-filtro-item]').forEach((item) => {
      const texto = normalizar(item.dataset.filtroTexto ?? item.textContent);
      item.hidden = !palabras.every((palabra) => texto.includes(palabra));
    });

    lista.querySelectorAll('[data-filtro-grupo]').forEach((grupo) => {
      grupo.hidden = !grupo.querySelector('[data-filtro-item]:not([hidden])');
    });

    if (vacio) {
      vacio.hidden = Boolean(lista.querySelector('[data-filtro-item]:not([hidden])'));
    }
  };

  entrada.addEventListener('input', filtrar);
  entrada.addEventListener('keydown', (evento) => {
    if (evento.key === 'Enter') evento.preventDefault();
  });

  filtrar();
  if (caja) caja.hidden = false;
});
