document.addEventListener("DOMContentLoaded", function () {
    const carousel = document.getElementById("reviewsCarousel");
    const track = document.getElementById("reviewsTrack");
    const previous = document.getElementById("reviewPrev");
    const next = document.getElementById("reviewNext");
    const ratingHint = document.getElementById("ratingHint");

    if (!carousel || !track) return;

    const originalCards = Array.from(track.children)
        .filter((item) => item.classList.contains("review-card"));

    // Duplikasi card untuk membuat animasi loop yang mulus.
    if (originalCards.length >= 2) {
        originalCards.forEach((card) => {
            const clone = card.cloneNode(true);
            clone.setAttribute("aria-hidden", "true");
            track.appendChild(clone);
        });
    } else {
        track.style.animation = "none";
    }

    function geserUlasan(arah) {
        const card = track.querySelector(".review-card");
        if (!card) return;

        const gap = parseFloat(getComputedStyle(track).gap) || 0;
        carousel.scrollBy({
            left: arah * (card.getBoundingClientRect().width + gap),
            behavior: "smooth"
        });
    }

    if (previous) previous.addEventListener("click", () => geserUlasan(-1));
    if (next) next.addEventListener("click", () => geserUlasan(1));

    // Geser manual dengan mouse. Sentuhan HP menggunakan scroll bawaan.
    let sedangGeser = false;
    let posisiAwalX = 0;
    let scrollAwal = 0;

    carousel.addEventListener("pointerdown", function (event) {
        if (event.pointerType === "touch") return;
        if (event.target.closest("button, a")) return;

        sedangGeser = true;
        posisiAwalX = event.clientX;
        scrollAwal = carousel.scrollLeft;
        carousel.classList.add("is-dragging");
        carousel.setPointerCapture(event.pointerId);
    });

    carousel.addEventListener("pointermove", function (event) {
        if (!sedangGeser) return;
        carousel.scrollLeft = scrollAwal - (event.clientX - posisiAwalX);
    });

    function berhentiGeser() {
        sedangGeser = false;
        carousel.classList.remove("is-dragging");
    }

    carousel.addEventListener("pointerup", berhentiGeser);
    carousel.addEventListener("pointercancel", berhentiGeser);
    carousel.addEventListener("lostpointercapture", berhentiGeser);

    document.querySelectorAll('.rating-picker input[name="rating"]').forEach((input) => {
        input.addEventListener("change", function () {
            if (ratingHint) ratingHint.textContent = this.value + " dari 5 bintang dipilih";
        });
    });

    // Hindari alert yang berulang saat halaman disegarkan.
    const parameter = new URLSearchParams(window.location.search);
    if (parameter.has("ulasan")) {
        const pesan = {
            berhasil: "Ulasan berhasil dikirim. Terima kasih!",
            rating: "Pilih rating antara 1 sampai 5 bintang.",
            pesan: "Pesan ulasan harus terdiri dari 5 sampai 1000 karakter.",
            gagal: "Ulasan gagal disimpan. Silakan coba lagi.",
            akses: "Login sebagai pelanggan untuk mengirim ulasan."
        }[parameter.get("ulasan")];

        if (pesan) window.alert(pesan);

        parameter.delete("ulasan");
        const query = parameter.toString();
        const urlBaru = window.location.pathname +
            (query ? "?" + query : "") + window.location.hash;
        window.history.replaceState({}, document.title, urlBaru);
    }
});
