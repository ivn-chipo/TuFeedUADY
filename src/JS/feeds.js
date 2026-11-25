const endpointFeeds = "includes/crud_preferencias.php";
const formFeed = document.getElementById("form-feed");
const formBusqueda = document.getElementById("form-busqueda");
const listaFeeds = document.getElementById("lista-feeds");
const mensaje = document.getElementById("mensaje");
const estadoFeeds = document.getElementById("estado-feeds");
const campoId = document.getElementById("feed-id");
const campoTema = document.getElementById("feed-tema");
const campoUrl = document.getElementById("feed-url");
const botonGuardar = document.getElementById("guardar-feed");
const botonCancelar = document.getElementById("cancelar-edicion");

function mostrarMensaje(texto, tipo = "") {
    mensaje.textContent = texto;
    mensaje.className = tipo ? `mensaje-${tipo}` : "";
}

async function solicitarApi(url, opciones = {}) {
    let respuesta;
    try {
        respuesta = await fetch(url, {
            credentials: "same-origin",
            ...opciones,
            headers: {
                "Accept": "application/json",
                ...(opciones.headers || {})
            }
        });
    } catch {
        throw new Error("No se pudo conectar con el servidor PHP.");
    }

    let resultado;
    try {
        resultado = await respuesta.json();
    } catch {
        throw new Error("El servidor devolvió una respuesta que no se pudo leer.");
    }

    if (respuesta.status === 401) {
        window.location.replace("login.html");
        throw new Error("Tu sesión terminó. Inicia sesión de nuevo.");
    }
    if (!respuesta.ok || !resultado.ok) {
        throw new Error(resultado.error || "La solicitud no se pudo completar.");
    }

    return resultado;
}

function crearElementoFeed(feed) {
    const tarjeta = document.createElement("article");
    tarjeta.className = "feed-item";

    const titulo = document.createElement("h3");
    titulo.textContent = feed.tema;

    const url = document.createElement("p");
    url.className = "feed-item-url";
    url.textContent = feed.url_rss;

    const botonEditar = document.createElement("button");
    botonEditar.type = "button";
    botonEditar.textContent = "Editar";
    botonEditar.addEventListener("click", () => {
        campoId.value = feed.id;
        campoTema.value = feed.tema;
        campoUrl.value = feed.url_rss;
        botonGuardar.textContent = "Guardar cambios";
        botonCancelar.hidden = false;
        campoTema.focus();
    });

    const botonEliminar = document.createElement("button");
    botonEliminar.type = "button";
    botonEliminar.textContent = "Eliminar";
    botonEliminar.addEventListener("click", async () => {
        if (!window.confirm(`¿Eliminar el feed "${feed.tema}"?`)) {
            return;
        }

        try {
            const resultado = await solicitarApi(`${endpointFeeds}?id=${encodeURIComponent(feed.id)}`, {
                method: "DELETE"
            });
            mostrarMensaje(resultado.mensaje, "exito");
            await cargarFeeds();
        } catch (error) {
            mostrarMensaje(error.message, "error");
        }
    });

    tarjeta.append(titulo, url, botonEditar, botonEliminar);
    return tarjeta;
}

async function cargarFeeds() {
    const palabra = document.getElementById("buscar-feeds").value.trim();
    const url = palabra
        ? `${endpointFeeds}?q=${encodeURIComponent(palabra)}`
        : endpointFeeds;

    const resultado = await solicitarApi(url);
    const feeds = resultado.datos;
    if (!Array.isArray(feeds)) {
        throw new Error("El servidor devolvió una lista de feeds inválida.");
    }

    listaFeeds.replaceChildren(...feeds.map(crearElementoFeed));
    estadoFeeds.textContent = feeds.length
        ? `${feeds.length} feed(s) encontrado(s).`
        : "Aún no tienes feeds guardados.";
}

function limpiarFormulario() {
    formFeed.reset();
    campoId.value = "";
    botonGuardar.textContent = "Agregar feed";
    botonCancelar.hidden = true;
}

formFeed.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    botonGuardar.disabled = true;
    mostrarMensaje("");

    const id = campoId.value;
    const datos = {
        tema: campoTema.value.trim(),
        url_rss: campoUrl.value.trim()
    };
    const opciones = {
        method: id ? "PUT" : "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(id ? { ...datos, id: Number(id) } : datos)
    };

    try {
        const resultado = await solicitarApi(endpointFeeds, opciones);
        limpiarFormulario();
        mostrarMensaje(resultado.mensaje, "exito");
        await cargarFeeds();
    } catch (error) {
        mostrarMensaje(error.message, "error");
    } finally {
        botonGuardar.disabled = false;
    }
});

formBusqueda.addEventListener("submit", async (evento) => {
    evento.preventDefault();
    mostrarMensaje("");
    try {
        await cargarFeeds();
    } catch (error) {
        mostrarMensaje(error.message, "error");
    }
});

document.getElementById("mostrar-todos").addEventListener("click", async () => {
    document.getElementById("buscar-feeds").value = "";
    mostrarMensaje("");
    try {
        await cargarFeeds();
    } catch (error) {
        mostrarMensaje(error.message, "error");
    }
});

botonCancelar.addEventListener("click", limpiarFormulario);

document.getElementById("cerrar-sesion").addEventListener("click", async () => {
    try {
        await solicitarApi("includes/auth.php?accion=logout", { method: "POST" });
        window.location.replace("login.html");
    } catch (error) {
        mostrarMensaje(error.message, "error");
    }
});

async function iniciarPagina() {
    try {
        const resultado = await solicitarApi("includes/auth.php?accion=sesion");
        document.getElementById("usuario-sesion").textContent =
            `Sesión iniciada como ${resultado.datos.nickname}`;
        await cargarFeeds();
    } catch (error) {
        mostrarMensaje(error.message, "error");
    }
}

iniciarPagina();
