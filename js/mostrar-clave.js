const ingles = document.documentElement.lang.toLowerCase().startsWith('en');
const textoMostrar = ingles ? 'Show' : 'Mostrar';
const textoOcultar = ingles ? 'Hide' : 'Ocultar';

document.querySelectorAll('input[type="password"]').forEach((campo, indice) => {
  if (campo.dataset.visibilidadLista === 'si') return;
  campo.dataset.visibilidadLista = 'si';

  if (!campo.id) {
    let id = `clave-visible-${indice}`;
    while (document.getElementById(id)) id += '-campo';
    campo.id = id;
  }

  const etiqueta = campo.labels[0]?.textContent.trim()
    || (ingles ? 'password' : 'contraseña');

  const grupo = document.createElement('div');
  grupo.className = 'input-group';
  campo.before(grupo);
  grupo.append(campo);

  const boton = document.createElement('button');
  boton.type = 'button';
  boton.className = 'btn btn-outline-secondary';
  boton.textContent = textoMostrar;
  boton.setAttribute('aria-controls', campo.id);
  boton.setAttribute('aria-label', `${textoMostrar}: ${etiqueta}`);
  boton.setAttribute('aria-pressed', 'false');

  boton.addEventListener('click', () => {
    const mostrar = campo.type === 'password';
    const texto = mostrar ? textoOcultar : textoMostrar;

    campo.type = mostrar ? 'text' : 'password';
    boton.textContent = texto;
    boton.setAttribute('aria-label', `${texto}: ${etiqueta}`);
    boton.setAttribute('aria-pressed', String(mostrar));
  });

  grupo.append(boton);
});