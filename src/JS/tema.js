const claveTema = "tufeeduady-tema";
const botonesTema = document.querySelectorAll("[data-theme-toggle]");

function aplicarTema(tema) {
    const esOscuro = tema === "oscuro";
    document.documentElement.dataset.theme = esOscuro ? "dark" : "light";

    botonesTema.forEach((boton) => {
        boton.textContent = esOscuro ? "Modo claro" : "Modo oscuro";
        boton.setAttribute("aria-label", `Cambiar a modo ${esOscuro ? "claro" : "oscuro"}`);
        boton.setAttribute("aria-pressed", String(esOscuro));
    });
}

let temaGuardado;
try {
    temaGuardado = localStorage.getItem(claveTema);
} catch (error) {
    console.error("No se pudo leer el tema guardado.", error);
}
aplicarTema(temaGuardado);

botonesTema.forEach((boton) => {
    boton.addEventListener("click", () => {
        const tema = document.documentElement.dataset.theme === "dark" ? "claro" : "oscuro";
        aplicarTema(tema);

        try {
            localStorage.setItem(claveTema, tema);
        } catch (error) {
            console.error("No se pudo guardar la preferencia de tema.", error);
        }
    });
});
