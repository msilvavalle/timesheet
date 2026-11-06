// Confirmación antes de enviar los formularios con data-confirmar (usuarios.php).
document.querySelectorAll('form[data-confirmar]').forEach((formulario) => {
  formulario.addEventListener('submit', (evento) => {
    if (!window.confirm(formulario.dataset.confirmar)) {
      evento.preventDefault();
    }
  });
});
