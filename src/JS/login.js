const formLogin = document.getElementById("formLogin");
const mensajeLogin = document.getElementById("mensaje-login");

formLogin.addEventListener("submit", async (evento) => {
    evento.preventDefault();

    const boton = formLogin.querySelector('button[type="submit"]');
    boton.disabled = true;
    mensajeLogin.textContent = "";

    let respuesta;
    try {
        respuesta = await fetch("includes/auth.php?accion=login", {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json"
            },
            credentials: "same-origin",
            body: JSON.stringify({
                usuario: document.getElementById("usuario").value.trim(),
                password: document.getElementById("password").value
            })
        });
    } catch {
        mensajeLogin.textContent = "No se pudo conectar con el servidor. Verifica que PHP y MySQL estén activos.";
        boton.disabled = false;
        return;
    }

    let resultado;
    try {
        resultado = await respuesta.json();
    } catch {
        mensajeLogin.textContent = "El servidor devolvió una respuesta que no se pudo leer.";
        boton.disabled = false;
        return;
    }

    if (!respuesta.ok || !resultado.ok) {
        mensajeLogin.textContent = resultado.error || "No se pudo iniciar sesión.";
        boton.disabled = false;
        return;
    }

    window.location.href = "index.html";
});
