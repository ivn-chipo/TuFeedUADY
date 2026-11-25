const mensajeSesion = document.getElementById("usuario-sesion");
const botonCerrarSesion = document.getElementById("cerrar-sesion");

async function verificarSesion() {
    let respuesta;
    try {
        respuesta = await fetch("includes/auth.php?accion=sesion", {
            headers: { "Accept": "application/json" },
            credentials: "same-origin"
        });
    } catch {
        mensajeSesion.textContent = "No se pudo validar la sesión con el servidor.";
        return;
    }

    let resultado;
    try {
        resultado = await respuesta.json();
    } catch {
        mensajeSesion.textContent = "El servidor devolvió una respuesta que no se pudo leer.";
        return;
    }

    if (respuesta.status === 401) {
        window.location.replace("login.html");
        return;
    }
    if (!respuesta.ok || !resultado.ok) {
        mensajeSesion.textContent = resultado.error || "No se pudo validar la sesión.";
        return;
    }

    mensajeSesion.textContent = `Hola, ${resultado.datos.nickname}`;
}

botonCerrarSesion.addEventListener("click", async () => {
    botonCerrarSesion.disabled = true;
    try {
        const respuesta = await fetch("includes/auth.php?accion=logout", {
            method: "POST",
            headers: { "Accept": "application/json" },
            credentials: "same-origin"
        });
        const resultado = await respuesta.json();
        if (!respuesta.ok || !resultado.ok) {
            mensajeSesion.textContent = resultado.error || "No se pudo cerrar la sesión.";
            botonCerrarSesion.disabled = false;
            return;
        }
        window.location.replace("login.html");
    } catch {
        mensajeSesion.textContent = "No se pudo conectar con el servidor para cerrar sesión.";
        botonCerrarSesion.disabled = false;
    }
});

verificarSesion();
