// Elementos de la pantalla
const formulario = document.getElementById("login-form");
const botonIngresar = document.getElementById("ingresar");
const botonRecuperar = document.getElementById("recuperar-password");
const mensaje = document.getElementById("mensaje");

// Comprobar que los campos estén completos
function comprobarIngreso(evento) {
  evento.preventDefault();

  if (!formulario.reportValidity()) {
    return;
  }

  mensaje.textContent =
    "Campos completos. El acceso se conectará con PHP y MySQL.";
}

// Permitir clic en Ingresar y envío con Enter
botonIngresar.addEventListener("click", comprobarIngreso);
formulario.addEventListener("submit", comprobarIngreso);

// Mostrar orientación para recuperar la contraseña
botonRecuperar.addEventListener("click", function () {
  mensaje.textContent =
    "Contacta a administración para solicitar el restablecimiento de tu contraseña.";
});