// Botones Mostrar/Ocultar con data-ver-clave (usuarios-editar.php).
document.querySelectorAll('[data-ver-clave]').forEach((boton) => {
  const campo = document.getElementById(boton.dataset.verClave);
  if (!campo) return;

  boton.addEventListener('click', () => {
    const mostrar = campo.type === 'password';
    campo.type = mostrar ? 'text' : 'password';
    boton.textContent = mostrar ? boton.dataset.ocultar : boton.dataset.mostrar;
    boton.setAttribute('aria-pressed', String(mostrar));
  });
});
