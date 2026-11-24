

//SECCION PARA ANIMACIONES APARTE DE LA LÓGICA

//ANIMACION DEL CARRUSEL
const viewport = document.getElementById("carrusel-vp");
const track = document.getElementById("carrusel-track");

let originalCards = Array.from(track.children).map((card)=> card.cloneNode(true));
let paused = false;
let dragging= false;
let dragStartX = 0;
let scrollPosition = 0;
let setWidth = 0;
let resumeTimer;
let dragStartScroll = 0;

function iniciar() {
    track.replaceChildren(...originalCards.map((card)=> card.cloneNode(true)));
    setWidth = 0;

    if (originalCards.length === 0 || viewport.clientWidth === 0) {
        scrollPosition = 0;
        viewport.scrollLeft = 0;
        return;
    }

    while (track.scrollWidth <= viewport.clientWidth) {
        originalCards.forEach((card) => track.append(card.cloneNode(true)));
    }

    const cardsInSet = Array.from(track.children);
    setWidth = track.scrollWidth;
    for (let copy= 0; copy <2; copy +=1) {
        cardsInSet.forEach((card) => track.append(card.cloneNode(true)));
    }

    viewport.scrollLeft = setWidth;
    scrollPosition = setWidth;
}

function setCarruselCards(cards) {
    if (!Array.isArray(cards) || cards.some((card) => !(card instanceof Element))) {
        throw new TypeError("setCarruselCards espera un arreglo de elementos HTML.");
    }
    originalCards = cards.map((card) => card.cloneNode(true));
    iniciar();
}

function moveToMiddleSet(){
    if(setWidth === 0) return;
    if (viewport.scrollLeft < setWidth) viewport.scrollLeft += setWidth;
    if (viewport.scrollLeft >= setWidth * 2) viewport.scrollLeft -= setWidth;
    scrollPosition = viewport.scrollLeft;
}

function animacion() {
    if (setWidth > 0 && !paused && !dragging) {
        scrollPosition +=0.50;
        if(scrollPosition >= setWidth * 2) {
            scrollPosition -= setWidth;
        }
        viewport.scrollLeft = scrollPosition;
    } else {
        moveToMiddleSet();
    }
    requestAnimationFrame(animacion);
}

iniciar();
window.addEventListener("load", iniciar);
window.addEventListener("resize", iniciar);
requestAnimationFrame(animacion);

viewport.addEventListener("mouseenter", () => {
    paused = true;
});
viewport.addEventListener("mouseleave", () => {
    dragging = false;
    paused = false;
});
viewport.addEventListener("mousedown", (event) => {
    dragging = true;
    paused = true;
    dragStartX = event.pageX;
    dragStartScroll = viewport.scrollLeft;
});

window.addEventListener("mousemove", (event) => {
    if (!dragging) return;
    viewport.scrollLeft = dragStartScroll - (event.pageX - dragStartX);
});

window.addEventListener("mouseup", () => {
    dragging = false;
    paused = false;
});

viewport.addEventListener("touchstart", () => {
    paused = true;
    clearTimeout(resumeTimer);
}, { passive: true });

viewport.addEventListener("touchend", () => {
    resumeTimer = setTimeout(() => {
        paused = false;
    }, 1800);
}, { passive: true });


////////////////////////