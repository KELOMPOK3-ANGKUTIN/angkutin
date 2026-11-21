
/*
  PENGATURAN WEBSITE
  1. Ganti whatsappNumber dengan nomor admin.
  2. Ubah nama, harga, dan foto armada sesuai bisnis.
  3. Harga dan tarif/km di bawah adalah contoh.
*/

const whatsappNumber = "6281234567890"; // GANTI DENGAN NOMOR ADMIN

const vehicles = [
  {
    id: "pickup",
    name: "Pickup",
    description: "Cocok untuk barang kecil dan menengah.",
    capacity: "Muatan ringan",
    basePrice: 100000,
    perKm: 5000,
    image: "/angkutin_baru/Gambar/pickup.jpeg",
    alt: "Mobil pickup untuk angkut barang"
  },
  {
    id: "engkel",
    name: "Truk Engkel",
    description: "Untuk perabot rumah dan barang usaha.",
    capacity: "Muatan menengah",
    basePrice: 175000,
    perKm: 7000,
    image: "/angkutin_baru/Gambar/engkel.jpeg",
    alt: "Truk engkel untuk jasa angkut"
  },
  {
    id: "double",
    name: "Engkel Double",
    description: "Kapasitas lebih besar untuk barang banyak.",
    capacity: "Muatan besar",
    basePrice: 250000,
    perKm: 9000,
    image: "/angkutin_baru/Gambar/engkeldobbel.jpeg",
    alt: "Truk engkel double untuk barang besar"
  },
  {
    id: "fuso",
    name: "Truk Fuso",
    description: "Untuk muatan besar dan kebutuhan usaha.",
    capacity: "Muatan ekstra besar",
    basePrice: 500000,
    perKm: 12000,
    image: "/angkutin_baru/Gambar/fuso.jpeg",
    alt: "Truk fuso untuk angkutan barang"
  }
];

const fleetGrid = document.getElementById("fleetGrid");
const vehicleSelect = document.getElementById("vehicle");
const distanceInput = document.getElementById("distance");
const estimatedPrice = document.getElementById("estimatedPrice");
const priceDetail = document.getElementById("priceDetail");
const bookingForm = document.getElementById("bookingForm");
const bookingDate = document.getElementById("bookingDate");

const rupiah = value =>
  new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0
  }).format(value);

function renderVehicles() {
  if (!fleetGrid || !vehicleSelect) return;
  fleetGrid.innerHTML = vehicles.map(vehicle => `
    <article class="vehicle-card" data-id="${vehicle.id}">
      <img
        src="${vehicle.image}"
        alt="${vehicle.alt}"
        loading="lazy"
        onerror="this.onerror=null;this.src='https://placehold.co/600x400/e9edf3/142b49?text=Foto+Armada'">

      <div class="vehicle-content">
        <h3>${vehicle.name}</h3>
        <p class="description">${vehicle.description}</p>

        <div class="vehicle-price">
          ${rupiah(vehicle.basePrice)}
          <small>/ mulai</small>
        </div>

        <span class="capacity">🚚 ${vehicle.capacity}</span>

        <button
          type="button"
          class="choose-btn"
          data-vehicle="${vehicle.id}">
          Pilih Kendaraan
        </button>
      </div>
    </article>
  `).join("");

  vehicleSelect.innerHTML = `
    <option value="">-- Pilih kendaraan --</option>
    ${vehicles.map(vehicle => `
      <option value="${vehicle.id}">
        ${vehicle.name} — mulai ${rupiah(vehicle.basePrice)}
      </option>
    `).join("")}
  `;
}

function getSelectedVehicle() {
  if (!vehicleSelect) return null;
  return vehicles.find(vehicle => vehicle.id === vehicleSelect.value);
}

function getDistance() {
  if (!distanceInput) return 0;
  const distance = Number(distanceInput.value);

  return Number.isFinite(distance) && distance > 0
    ? Math.min(distance, 10000)
    : 0;
}

function calculatePrice() {
  const vehicle = getSelectedVehicle();
  const distance = getDistance();

  if (!estimatedPrice || !priceDetail) return null;

  if (!vehicle) {
    estimatedPrice.textContent = "Pilih kendaraan";
    priceDetail.textContent = "Pilih salah satu dari empat armada.";
    return null;
  }

  if (distance <= 0) {
    estimatedPrice.textContent = "Masukkan jarak";
    priceDetail.textContent = "Jarak harus lebih dari 0 km.";
    return null;
  }

  const travelCost = distance * vehicle.perKm;
  const total = vehicle.basePrice + travelCost;

  estimatedPrice.textContent = rupiah(total);
  priceDetail.textContent =
    `${rupiah(vehicle.basePrice)} biaya dasar + ` +
    `${distance} km × ${rupiah(vehicle.perKm)}/km`;

  return total;
}

function updateSelectedVehicle() {
  if (!vehicleSelect) return;
  document.querySelectorAll(".vehicle-card").forEach(card => {
    const isSelected = card.dataset.id === vehicleSelect.value;

    card.classList.toggle("selected", isSelected);

    const button = card.querySelector(".choose-btn");

    button.textContent = isSelected
      ? "✓ Kendaraan Dipilih"
      : "Pilih Kendaraan";
  });

  calculatePrice();
}

if (fleetGrid) fleetGrid.addEventListener("click", event => {
  const button = event.target.closest("[data-vehicle]");

  if (!button) return;

  vehicleSelect.value = button.dataset.vehicle;
  updateSelectedVehicle();

  document.getElementById("pemesanan").scrollIntoView({
    behavior: "smooth"
  });
});

if (vehicleSelect) vehicleSelect.addEventListener("change", updateSelectedVehicle);
if (distanceInput) distanceInput.addEventListener("input", calculatePrice);

function setMinimumDate() {
  const now = new Date();

  const localDate = new Date(
    now.getTime() - now.getTimezoneOffset() * 60000
  ).toISOString().slice(0, 10);

  if (bookingDate) {
    bookingDate.min = localDate;
    bookingDate.value = localDate;
  }
}


/* ==========================================
   PETA RUTE: GEOCODING DAN JARAK JALAN
========================================== */
const routeMapElement = document.getElementById("routeMap");
const routeStatus = document.getElementById("routeStatus");
const pickupAddressInput = document.getElementById("pickup");
const destinationAddressInput = document.getElementById("destination");
let routeMap = null;
let pickupMarker = null;
let destinationMarker = null;
let routeLine = null;
let pickupCoords = null;
let destinationCoords = null;
let activeMapPoint = "pickup";
let routeRequest = 0;

function setRouteStatus(message, isError = false) {
  if (!routeStatus) return;
  routeStatus.textContent = message;
  routeStatus.classList.toggle("error", isError);
}

function updateDistanceFromRoute(km) {
  if (!distanceInput) return;
  const rounded = Math.max(0.1, Math.round(km * 10) / 10);
  distanceInput.value = rounded.toFixed(1);
  calculatePrice();
}

function drawRoute() {
  if (!routeMap || !pickupCoords || !destinationCoords) return;
  const request = ++routeRequest;
  setRouteStatus("Menghitung jarak berdasarkan rute jalan...");
  const start = `${pickupCoords[1]},${pickupCoords[0]}`;
  const end = `${destinationCoords[1]},${destinationCoords[0]}`;

  fetch(`https://router.project-osrm.org/route/v1/driving/${start};${end}?overview=full&geometries=geojson`, {
    headers: { "Accept": "application/json" }
  })
    .then(response => {
      if (!response.ok) throw new Error("Layanan rute tidak merespons.");
      return response.json();
    })
    .then(data => {
      if (request !== routeRequest) return;
      if (data.code !== "Ok" || !data.routes || !data.routes.length) {
        throw new Error("Rute jalan tidak ditemukan. Periksa kembali kedua titik.");
      }
      const route = data.routes[0];
      const km = route.distance / 1000;
      if (routeLine) routeMap.removeLayer(routeLine);
      routeLine = L.geoJSON(route.geometry, {
        style: { color: "#2463eb", weight: 5, opacity: 0.85 }
      }).addTo(routeMap);
      routeMap.fitBounds(routeLine.getBounds(), { padding: [35, 35] });
      updateDistanceFromRoute(km);
      setRouteStatus(`Jarak rute jalan: ${km.toFixed(1)} km. Jarak ini mengikuti jalan, bukan garis lurus.`);
    })
    .catch(error => {
      if (request !== routeRequest) return;
      setRouteStatus(error.message + " Kamu dapat menggeser titik pada peta atau mencoba lagi.", true);
    });
}

function setMapPoint(type, latlng, updateAddress = false) {
  const coords = [latlng.lat, latlng.lng];
  if (type === "pickup") {
    pickupCoords = coords;
    if (pickupMarker) pickupMarker.setLatLng(latlng);
    else pickupMarker = L.marker(latlng, { draggable: true }).addTo(routeMap).bindPopup("Titik penjemputan");
    pickupMarker.off("dragend").on("dragend", event => setMapPoint("pickup", event.target.getLatLng()));
  } else {
    destinationCoords = coords;
    if (destinationMarker) destinationMarker.setLatLng(latlng);
    else destinationMarker = L.marker(latlng, { draggable: true }).addTo(routeMap).bindPopup("Titik tujuan");
    destinationMarker.off("dragend").on("dragend", event => setMapPoint("destination", event.target.getLatLng()));
  }
  if (updateAddress) reverseGeocode(type, latlng);
  if (pickupCoords && destinationCoords) drawRoute();
}

async function geocodeAddress(type) {
  const field = type === "pickup" ? pickupAddressInput : destinationAddressInput;
  const address = field ? field.value.trim() : "";
  if (!address) return;
  setRouteStatus(`Mencari lokasi ${type === "pickup" ? "penjemputan" : "tujuan"}...`);
  try {
    const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(address + ", Indonesia")}`;
    const response = await fetch(url, { headers: { "Accept": "application/json" } });
    if (!response.ok) throw new Error("Pencarian alamat gagal.");
    const results = await response.json();
    if (!results.length) throw new Error(`Alamat ${type === "pickup" ? "penjemputan" : "tujuan"} tidak ditemukan. Coba tulis alamat lebih lengkap.`);
    const point = L.latLng(Number(results[0].lat), Number(results[0].lon));
    setMapPoint(type, point);
    if (pickupCoords && destinationCoords) drawRoute();
    else {
      routeMap.setView(point, 14);
      setRouteStatus("Lokasi ditemukan. Masukkan alamat satunya untuk menghitung rute jalan.");
    }
  } catch (error) {
    setRouteStatus(error.message, true);
  }
}

async function reverseGeocode(type, latlng) {
  try {
    const url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${latlng.lat}&lon=${latlng.lng}`;
    const response = await fetch(url, { headers: { "Accept": "application/json" } });
    const data = await response.json();
    const field = type === "pickup" ? pickupAddressInput : destinationAddressInput;
    if (field && data.display_name) field.value = data.display_name;
  } catch (error) {
    console.warn("Alamat titik peta tidak dapat diperbarui:", error);
  }
}

if (routeMapElement && window.L) {
  routeMap = L.map(routeMapElement).setView([-6.2, 106.8], 10);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(routeMap);

  routeMap.on("click", event => setMapPoint(activeMapPoint, event.latlng, true));

  const pickupButton = document.getElementById("setPickupPoint");
  const destinationButton = document.getElementById("setDestinationPoint");
  if (pickupButton) pickupButton.addEventListener("click", () => {
    activeMapPoint = "pickup";
    pickupButton.classList.add("active");
    destinationButton?.classList.remove("active");
    setRouteStatus("Klik peta untuk menentukan titik penjemputan.");
  });
  if (destinationButton) destinationButton.addEventListener("click", () => {
    activeMapPoint = "destination";
    destinationButton.classList.add("active");
    pickupButton?.classList.remove("active");
    setRouteStatus("Klik peta untuk menentukan titik tujuan.");
  });

  pickupAddressInput?.addEventListener("blur", () => geocodeAddress("pickup"));
  destinationAddressInput?.addEventListener("blur", () => geocodeAddress("destination"));
}


/* ==========================================
   KETERSEDIAAN ARMADA PER TANGGAL
========================================== */
const vehicleAvailability = document.getElementById("vehicleAvailability");
const bookingMessage = document.getElementById("bookingMessage");
let availabilityRequest = 0;
let currentAvailability = null;
let bookingMessageTimer = null;

function tampilkanPesanPemesanan(teks, jenis = "error") {
  if (!bookingMessage) return;
  clearTimeout(bookingMessageTimer);
  bookingMessage.textContent = teks;
  bookingMessage.hidden = false;
  bookingMessage.style.opacity = "1";
  bookingMessage.style.background = jenis === "success" ? "#d1fae5" : "#fee2e2";
  bookingMessage.style.color = jenis === "success" ? "#065f46" : "#b91c1c";
  bookingMessage.style.borderBottom = jenis === "success" ? "1px solid #a7f3d0" : "1px solid #fecaca";
  bookingMessage.scrollIntoView({ behavior: "smooth", block: "start" });
  bookingMessageTimer = setTimeout(() => {
    bookingMessage.style.opacity = "0";
    setTimeout(() => {
      bookingMessage.hidden = true;
      bookingMessage.style.opacity = "1";
    }, 500);
  }, 3500);
}

async function periksaKetersediaanArmada() {
  const vehicle = getSelectedVehicle();
  const date = bookingDate ? bookingDate.value : "";
  currentAvailability = null;
  const requestId = ++availabilityRequest;

  if (!vehicle || !date) {
    if (vehicleAvailability) vehicleAvailability.textContent = "Pilih tanggal dan armada untuk melihat stok tersedia.";
    return;
  }
  if (vehicleAvailability) vehicleAvailability.textContent = "Memeriksa ketersediaan armada...";

  try {
    const params = new URLSearchParams({ tanggal: date, kendaraan: vehicle.id });
    const response = await fetch(`pesanan/cek_armada.php?${params.toString()}`, {
      credentials: "same-origin",
      cache: "no-store"
    });
    const data = await response.json();
    if (requestId !== availabilityRequest) return;
    if (!response.ok || !data.berhasil) throw new Error(data.pesan || "Ketersediaan armada gagal diperiksa.");

    currentAvailability = { date, vehicleId: vehicle.id, tersedia: Number(data.tersedia) };
    if (vehicleAvailability) {
      vehicleAvailability.textContent = data.tersedia > 0
        ? `Tersedia ${data.tersedia} dari 10 armada ${data.nama_kendaraan} pada tanggal ${date}.`
        : `Stok armada ${data.nama_kendaraan} untuk tanggal ${date} sudah habis.`;
      vehicleAvailability.style.color = data.tersedia > 0 ? "#166534" : "#b91c1c";
      vehicleAvailability.style.fontWeight = "600";
    }
    if (data.tersedia <= 0) {
      tampilkanPesanPemesanan("Armada yang kamu butuhkan sedang tidak ada. Silakan pilih armada lain atau ganti tanggal pemesanan.", "error");
    }
  } catch (error) {
    if (requestId !== availabilityRequest) return;
    currentAvailability = null;
    if (vehicleAvailability) {
      vehicleAvailability.textContent = error.message || "Ketersediaan armada tidak dapat diperiksa. Coba lagi.";
      vehicleAvailability.style.color = "#b91c1c";
    }
  }
}

if (vehicleSelect) vehicleSelect.addEventListener("change", periksaKetersediaanArmada);
if (bookingDate) bookingDate.addEventListener("change", periksaKetersediaanArmada);

/* ==========================================
   PROSES PEMESANAN
   Simpan pesanan ke database dan buka WhatsApp
========================================== */


if (bookingForm) bookingForm.addEventListener("submit", async event => {
    event.preventDefault();

    if (!bookingForm.reportValidity()) return;

    const vehicle = getSelectedVehicle();
    const distance = getDistance();
    const total = calculatePrice();

    if (!pickupCoords || !destinationCoords || distance <= 0) {
      tampilkanPesanPemesanan("Tentukan alamat penjemputan dan tujuan sampai rute jalan ditemukan di peta.", "error");
      return;
    }

    // Selalu cek ulang stok untuk tanggal dan kendaraan yang dipilih.
    await periksaKetersediaanArmada();
    if (currentAvailability && currentAvailability.tersedia <= 0) {
      tampilkanPesanPemesanan("Armada yang kamu butuhkan sedang tidak ada. Silakan pilih armada lain atau ganti tanggal pemesanan.", "error");
      return;
    }

    if (!vehicle || total === null || distance <= 0) {
        alert("Silakan pilih kendaraan dan masukkan jarak yang valid.");
        return;
    }

    const name = document.getElementById("customerName").value.trim();
    const phone = document.getElementById("customerPhone").value.trim();
    const pickup = document.getElementById("pickup").value.trim();
    const destination = document.getElementById("destination").value.trim();
    const date = bookingDate.value;
    const notes = document.getElementById("notes").value.trim();
    const paymentProofInput = document.getElementById("paymentProof");
    const paymentProof = paymentProofInput?.files?.[0];
    const paymentConfirmation = document.getElementById("paymentConfirmation");
    const csrfToken = document.getElementById("bookingCsrfToken")?.value || "";

    if (!paymentProof) {
        tampilkanPesanPemesanan("Silakan pilih foto bukti pembayaran terlebih dahulu.", "error");
        paymentProofInput?.focus();
        return;
    }
    if (paymentProof.size > 5 * 1024 * 1024) {
        tampilkanPesanPemesanan("Ukuran foto bukti pembayaran maksimal 5 MB.", "error");
        return;
    }
    if (!paymentConfirmation?.checked) {
        tampilkanPesanPemesanan("Centang konfirmasi pembayaran sebelum mengirim pesanan.", "error");
        paymentConfirmation?.focus();
        return;
    }

    if (!name || !phone || !pickup || !destination || !date) {
        alert("Mohon lengkapi semua data pemesanan.");
        return;
    }

    if (!/^[0-9+() -]{9,20}$/.test(phone)) {
        alert("Nomor telepon tidak valid.");
        return;
    }

    if (date < bookingDate.min) {
        alert("Tanggal pemesanan tidak boleh sebelum hari ini.");
        return;
    }

    const formData = new FormData();
    formData.append("customerName", name);
    formData.append("customerPhone", phone);
    formData.append("pickup", pickup);
    formData.append("destination", destination);
    formData.append("bookingDate", date);
    formData.append("distance", String(distance));
    formData.append("vehicle", vehicle.id);
    formData.append("notes", notes);
    formData.append("estimatedPrice", String(total));
    formData.append("bukti_pembayaran", paymentProof);
    formData.append("csrf_token", csrfToken);
    formData.append("payment_confirmation", paymentConfirmation.checked ? "yes" : "no");

    const tombolSubmit = bookingForm.querySelector('[type="submit"]');
    const teksTombol = tombolSubmit.textContent;

    tombolSubmit.disabled = true;
    tombolSubmit.textContent = "Menyimpan pesanan...";

    try {
        const response = await fetch("pesanan/simpan_pesanan.php", {
            method: "POST",
            body: formData,
            credentials: "same-origin"
        });

        const hasil = await response.json();

        if (!response.ok || !hasil.berhasil) {
            throw new Error(
                hasil.pesan || "Pesanan gagal disimpan ke database."
            );
        }

        tampilkanPesanPemesanan("Pesanan dan bukti pembayaran berhasil dikirim! Membuka halaman Pesanan...", "success");
        setTimeout(() => { window.location.href = "pesanan/saya.php"; }, 900);

    } catch (error) {
        console.error("Kesalahan penyimpanan pesanan:", error);
        tampilkanPesanPemesanan(error.message || "Terjadi kesalahan. Silakan coba lagi.", "error");
        // Jika stok habis di sisi server, refresh jumlah stok yang ditampilkan.
        await periksaKetersediaanArmada();
    } finally {
        tombolSubmit.disabled = false;
        tombolSubmit.textContent = teksTombol;
    }
});

/* ==========================================
   MENU NAVIGASI
========================================== */

const menuToggle = document.getElementById("menuToggle");
const navMenu = document.getElementById("navMenu");

if (menuToggle && navMenu) menuToggle.addEventListener("click", () => {
  const isOpen = navMenu.classList.toggle("open");

  menuToggle.setAttribute("aria-expanded", String(isOpen));
});

if (navMenu && menuToggle) navMenu.querySelectorAll("a").forEach(link => {
  link.addEventListener("click", () => {
    navMenu.classList.remove("open");
    menuToggle.setAttribute("aria-expanded", "false");
  });
});

/* ==========================================
   TAHUN FOOTER DAN INISIALISASI
========================================== */

const yearElement = document.getElementById("year");
if (yearElement) yearElement.textContent = new Date().getFullYear();

renderVehicles();
setMinimumDate();
updateSelectedVehicle();
periksaKetersediaanArmada();
