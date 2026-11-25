const formRegistro = document.getElementById("formRegistro");
const mensajeRegistro = document.getElementById("mensaje-registro");

formRegistro.addEventListener("submit", async (evento) => {
    evento.preventDefault();

    const boton = formRegistro.querySelector('button[type="submit"]');
    boton.disabled = true;
    mensajeRegistro.textContent = "";

    let respuesta;
    try {
        respuesta = await fetch("includes/auth.php?accion=registro", {
            method: "POST",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json"
            },
            credentials: "same-origin",
            body: JSON.stringify({
                nickname: document.getElementById("nickname").value.trim(),
                correo: document.getElementById("correo").value.trim(),
                password: document.getElementById("password").value,
                campus: document.querySelector('input[name="campus"]:checked').value
            })
        });
    } catch {
        mensajeRegistro.textContent = "No se pudo conectar con el servidor. Verifica que PHP y MySQL estén activos.";
        boton.disabled = false;
        return;
    }

    let resultado;
    try {
        resultado = await respuesta.json();
    } catch {
        mensajeRegistro.textContent = "El servidor devolvió una respuesta que no se pudo leer.";
        boton.disabled = false;
        return;
    }

    if (!respuesta.ok || !resultado.ok) {
        const erroresCampos = Object.values(resultado.campos || {}).join(". ");
        mensajeRegistro.textContent = [resultado.error, erroresCampos].filter(Boolean).join(": ");
        boton.disabled = false;
        return;
    }

    window.location.href = "index.html";
});
