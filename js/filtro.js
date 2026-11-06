// Filtro de texto para listas de casillas (fichas de consultor y de proyecto).
// <input data-filtro="lista-x"> filtra los [data-filtro-item] dentro de #lista-x
// y oculta los [data-filtro-grupo] que queden vacíos. Sin JavaScript, la lista
// se ve completa y el buscador queda oculto.
document.querySelectorAll('input[data-filtro]').forEach((entrada) => {
  const lista = document.getElementById(entrada.dataset.filtro);
  const caja = entrada.closest('[data-filtro-caja]');

  if (!lista) {
    return;
  }

  const normalizar = (texto) => texto
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLocaleLowerCase('es')
    .trim();

  const filtrar = () => {
    const palabras = normalizar(entrada.value).split(/\s+/).filter(Boolean);

    lista.querySelectorAll('[data-filtro-item]').forEach((item) => {
      const texto = normalizar(item.textContent);
      item.hidden = !palabras.every((palabra) => texto.includes(palabra));
    });

    lista.querySelectorAll('[data-filtro-grupo]').forEach((grupo) => {
      grupo.hidden = !grupo.querySelector('[data-filtro-item]:not([hidden])');
    });
  };

  entrada.addEventListener('input', filtrar);
  entrada.addEventListener('keydown', (evento) => {
    if (evento.key === 'Enter') {
      evento.preventDefault();
    }
  });

  if (caja) {
    caja.hidden = false;
  }
});
