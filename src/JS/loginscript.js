const formRegistro = document.getElementById("formRegistro");

formRegistro.addEventListener("submit", (evento) => {
    evento.preventDefault();

    const datos = {
        nickname: document.getElementById("nickname").value.trim(),
        correo: document.getElementById("correo").value.trim(),
        campus: document.getElementById("campus").value,
        intereses: Array.from(
            document.querySelectorAll('input[name="intereses[]"]:checked'),
            (checkbox) => checkbox.value
        )
    };

    console.log(datos);

    localStorage.setItem("user_nickname", datos.nickname);
    localStorage.setItem("user_correo", datos.correo);
    localStorage.setItem("user_campus", datos.campus);
    localStorage.setItem("user_intereses", JSON.stringify(datos.intereses));
});
