
<?php
session_start();

// Token keamanan untuk pengiriman form pemesanan.
if (empty($_SESSION["csrf_pemesanan"])) {
    $_SESSION["csrf_pemesanan"] = bin2hex(random_bytes(32));
}

// Lindungi halaman agar hanya bisa dibuka setelah login
if (!isset($_SESSION["user_id"])) {
    header("Location: auth/Form%20login.php");
    exit;
}

// Siapkan pesan login yang hanya muncul satu kali
$pesanLogin = "";

if (
    isset($_SESSION["login_berhasil"]) &&
    $_SESSION["login_berhasil"] === true
) {
    $pesanLogin = "Login berhasil! Selamat datang, " .
        htmlspecialchars(
            $_SESSION["username"],
            ENT_QUOTES,
            "UTF-8"
        ) . "!";

    unset($_SESSION["login_berhasil"]);
}

// Siapkan nama pengguna untuk ditampilkan
$username = htmlspecialchars(
    $_SESSION["username"] ?? "Pengguna",
    ENT_QUOTES,
    "UTF-8"
);

// Tentukan role akun yang sedang login.
$role = $_SESSION["role"] ?? "pelanggan";
$isAdmin = ($role === "admin");
$isKurir = ($role === "kurir");
$isPelanggan = ($role === "pelanggan");

// Jika akun kurir membuka halaman utama secara langsung, arahkan ke dashboard.
if ($isKurir) {
    header("Location: kurir/index.php");
    exit;
}

// Tentukan tujuan dashboard sesuai role.
$dashboardUrl = $isAdmin
    ? "admin/index.php"
    : ($isKurir ? "kurir/index.php" : "pesanan/saya.php");

$dashboardLabel = $isAdmin
    ? "Dashboard Admin"
    : ($isKurir ? "Dashboard Kurir" : "Pesanan Saya");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Jasa angkut barang yang praktis, aman, dan terpercaya.">

    <title>Angkutin - Jasa Angkut Barang</title>

    <link rel="stylesheet" href="assets/css/Halaman_Utama.css">
    <link rel="stylesheet" href="assets/css/ulasan.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <!-- CSS tambahan khusus tombol akun dan logout -->
    <style>
        .nav-account {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-user {
            font-size: 14px;
            color: inherit;
        }

        .nav-logout {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 8px;
            background: #dc2626;
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s ease;
        }

        .nav-logout:hover {
            background: #b91c1c;
        }

        /* Tautan Pesanan dan Dashboard menyatu dengan navigasi utama. */
        .nav-feature-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 11px;
            border: 1px solid #e4e9f1;
            border-radius: 8px;
            background: #f5f7fb;
            color: #142b49;
            white-space: nowrap;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .navbar nav a.nav-feature-link:hover {
            background: #eaf0f8;
            border-color: #cbd7e7;
            color: #142b49;
        }

        .navbar nav a.nav-admin-link {
            background: #142b49;
            border-color: #142b49;
            color: #ffffff;
        }

        .navbar nav a.nav-admin-link:hover {
            background: #254b78;
            border-color: #254b78;
            color: #ffffff;
        }

        @media (max-width: 760px) {
            .navbar nav a.nav-feature-link {
                justify-content: flex-start;
                margin: 2px 0;
                white-space: normal;
            }
        }

        @media (max-width: 768px) {
            .nav-account {
                justify-content: center;
                margin-top: 10px;
            }
        }
    </style>
</head>

<body>

<?php if ($pesanLogin !== ""): ?>
    <div id="loginSuccessMessage"
         style="background: #d1fae5; color: #065f46; padding: 12px; text-align: center; transition: opacity 0.5s ease;">
        <?= $pesanLogin ?>
    </div>

    <script>
        setTimeout(function () {
            const pesan = document.getElementById("loginSuccessMessage");

            if (pesan) {
                pesan.style.opacity = "0";

                setTimeout(function () {
                    pesan.remove();
                }, 500);
            }
        }, 2500);
    </script>
<?php endif; ?>

    <!-- Pesan ketersediaan armada / hasil pemesanan, tampil di lokasi pesan login -->
    <div id="bookingMessage" role="status" aria-live="polite" hidden
         style="padding:12px;text-align:center;font-weight:600;transition:opacity .5s ease;"></div>

    <!-- NAVBAR -->
    <header class="navbar">
        <a href="#beranda" class="brand">
            <img src="Gambar/Logo_Usaha.png" alt="Logo Angkutin" class="logo">
            <span>Angkut<span class="accent">in.</span></span>
        </a>

        <button
            class="menu-toggle"
            id="menuToggle"
            aria-label="Buka menu"
            aria-expanded="false"
        >☰</button>

        <nav id="navMenu">
            <a href="#beranda">Beranda</a>
            <a href="#layanan">Layanan</a>
            <a href="#ulasan">Ulasan Pelanggan</a>

            <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, "UTF-8") ?>"
               class="nav-feature-link <?= ($isAdmin || $isKurir) ? "nav-admin-link" : "" ?>">
                <?= htmlspecialchars($dashboardLabel, ENT_QUOTES, "UTF-8") ?>
            </a>

            <?php if ($isPelanggan): ?>
                <a href="#pemesanan" class="nav-button">Pesan Sekarang</a>
            <?php endif; ?>

            <!-- Informasi pengguna dan tombol logout -->
            <div class="nav-account">
                <span class="nav-user">
                    Halo, <?= $username ?>
                </span>

                <a
                    href="auth/logout.php"
                    class="nav-logout"
                    onclick="return confirm('Yakin ingin logout dari Angkutin?');"
                >
                    Logout
                </a>
            </div>
        </nav>
    </header>

    <main>

        <!-- BAGIAN BERANDA -->
        <section class="hero" id="beranda">
            <div class="hero-content">
                <span class="eyebrow">ANGKUTAN BARANG TERPERCAYA</span>

                <h1>
                    Pindahan dan Angkut Barang Jadi
                    <span class="accent">Lebih Mudah.</span>
                </h1>

                <p>
                    Butuh angkut barang, pindahan rumah, atau kirim barang usaha?
                    Kami siap membantu dengan armada yang sesuai kebutuhanmu.
                </p>

                <div class="hero-actions">
                    <?php if ($isPelanggan): ?>
                        <a href="#pemesanan" class="btn btn-primary">
                            Pesan Sekarang →
                        </a>
                    <?php elseif ($isAdmin): ?>
                        <a href="admin/index.php" class="btn btn-primary">
                            Dashboard Admin →
                        </a>
                    <?php elseif ($isKurir): ?>
                        <a href="kurir/index.php" class="btn btn-primary">
                            Dashboard Kurir →
                        </a>
                    <?php endif; ?>

                    <a href="#ulasan" class="btn btn-outline">
                        Lihat Ulasan
                    </a>
                </div>

                <div class="hero-points">
                    <span>✓ Pilihan armada lengkap</span>
                    <span>✓ Harga transparan</span>
                    <span>✓ Pemesanan mudah</span>
                </div>
            </div>

            <div class="hero-image">
                <img
                    src="Gambar/foto_truck.jpeg"
                    alt="Truk jasa angkut barang"
                >

                <div class="image-label">
                    <span class="label-icon">🚚</span>

                    <div>
                        <strong>Siap Antar Barangmu</strong>
                        <small>Praktis, aman, dan nyaman</small>
                    </div>
                </div>
            </div>
        </section>

        <!-- BAGIAN LAYANAN -->
        <section class="services section" id="layanan">
            <div class="section-heading">
                <span class="eyebrow">LAYANAN KAMI</span>
                <h2>Satu layanan untuk berbagai kebutuhan</h2>
                <p>
                    Dari barang kecil hingga pindahan rumah, pilih layanan yang tepat.
                </p>
            </div>

            <div class="service-grid">
                <article class="service-card">
                    <div class="service-icon">📦</div>
                    <h3>Angkut Barang</h3>
                    <p>
                        Untuk perabot, perlengkapan usaha, dan barang lainnya.
                    </p>
                </article>

                <article class="service-card">
                    <div class="service-icon">🏠</div>
                    <h3>Pindahan Rumah</h3>
                    <p>
                        Bantu pindahkan barang rumah dengan armada yang sesuai.
                    </p>
                </article>

                <article class="service-card">
                    <div class="service-icon">🏢</div>
                    <h3>Angkut Usaha</h3>
                    <p>
                        Pengiriman stok barang dan perlengkapan usaha.
                    </p>
                </article>
            </div>
        </section>

        <!-- BAGIAN PILIHAN ARMADA -->
        <section class="fleet section" id="armada">
            <div class="section-heading">
                <span class="eyebrow">PILIHAN ARMADA</span>
                <h2>Temukan mobil yang kamu butuhkan</h2>
                <p>
                    Empat pilihan kendaraan dengan kapasitas dan harga dasar berbeda.
                </p>
            </div>

            <div class="fleet-grid" id="fleetGrid"></div>
        </section>

        <!-- BAGIAN ULASAN PELANGGAN: diletakkan di bawah Pilihan Armada -->
        <section class="reviews-section section" id="ulasan">
            <?php include __DIR__ . "/ulasan.php"; ?>
        </section>

        <!-- Form pemesanan hanya ditampilkan untuk pelanggan. -->
        <?php if ($isPelanggan): ?>
        <section class="booking section" id="pemesanan">
            <div class="booking-intro">
                <span class="eyebrow">PEMESANAN ONLINE</span>

                <h2>Siap mengangkut barangmu?</h2>

                <p>
                    Isi detail pemesanan. Pilih armada dan periksa perkiraan
                    biayanya sebelum melanjutkan.
                </p>

                <div class="booking-note">
                    <span>✓</span> Pilih kendaraan sesuai kebutuhan
                </div>

                <div class="booking-note">
                    <span>✓</span> Perkiraan biaya ditampilkan otomatis
                </div>

                <div class="booking-note">
                    <span>✓</span> Konfirmasi pesanan melalui WhatsApp
                </div>
            </div>

            <form id="bookingForm" class="booking-form">
                <h3>Formulir Pemesanan</h3>

                <div class="form-group">
                    <label for="customerName">Nama lengkap</label>

                    <input
                        type="text"
                        id="customerName"
                        placeholder="Masukkan nama kamu"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="customerPhone">Nomor WhatsApp</label>

                    <input
                        type="tel"
                        id="customerPhone"
                        placeholder="Contoh: 081234567890"
                        pattern="[0-9+() -]{9,20}"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="pickup">Alamat penjemputan</label>

                    <textarea
                        id="pickup"
                        rows="2"
                        placeholder="Alamat lengkap lokasi barang"
                        required
                    ></textarea>
                </div>

                <div class="form-group">
                    <label for="destination">Alamat tujuan</label>

                    <textarea
                        id="destination"
                        rows="2"
                        placeholder="Alamat pengantaran barang"
                        required
                    ></textarea>
                </div>

                <div class="form-group route-map-group">
                    <label>Peta rute penjemputan dan pengantaran</label>
                    <p class="map-help">Isi kedua alamat, lalu tekan Tab atau klik di luar kolom. Titik dapat digeser langsung pada peta untuk menyesuaikan lokasi.</p>
                    <div class="map-actions">
                        <button type="button" id="setPickupPoint" class="map-point-button active">Atur titik penjemputan</button>
                        <button type="button" id="setDestinationPoint" class="map-point-button">Atur titik tujuan</button>
                    </div>
                    <div id="routeMap" aria-label="Peta rute penjemputan dan tujuan"></div>
                    <p id="routeStatus" class="route-status" aria-live="polite">Masukkan alamat penjemputan dan tujuan untuk menghitung rute jalan.</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="bookingDate">Tanggal angkut</label>

                        <input
                            type="date"
                            id="bookingDate"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="distance">Jarak (km)</label>

                        <input
                            type="number"
                            id="distance"
                            min="0.1"
                            max="10000"
                            step="0.1"
                            placeholder="Otomatis dari rute peta"
                            readonly
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="vehicle">Pilih kendaraan</label>

                    <select id="vehicle" required></select>
                    <p id="vehicleAvailability" aria-live="polite" style="margin-top:8px;font-size:14px;color:#475569;">
                        Pilih tanggal dan armada untuk melihat stok tersedia.
                    </p>
                </div>

                <div class="form-group">
                    <label for="notes">Catatan barang (opsional)</label>

                    <textarea
                        id="notes"
                        rows="2"
                        placeholder="Contoh: lemari, meja, 5 kardus"
                    ></textarea>
                </div>

                <div class="price-summary">
                    <span>Estimasi biaya</span>
                    <strong id="estimatedPrice">Rp150.000</strong>
                    <small id="priceDetail">
                        Harga dasar dan jarak perjalanan
                    </small>
                </div>

                <!-- Pembayaran QRIS dan bukti pembayaran dikirim bersama form pesanan. -->
                <div class="booking-payment-box">
                    <h3>Pembayaran QRIS</h3>
                    <p>Scan QRIS resmi Angkutin menggunakan aplikasi pembayaran kamu. Bayar sesuai nominal estimasi biaya di atas.</p>
                    <img src="Gambar/qris_angkutin.png" alt="QRIS resmi pembayaran Angkutin" class="booking-qris-image">
                    <p class="booking-payment-note">Pastikan nominal pembayaran sesuai dengan estimasi biaya pesanan.</p>

                    <div class="form-group">
                        <label for="paymentProof">Upload foto bukti pembayaran</label>
                        <input
                            type="file"
                            id="paymentProof"
                            name="bukti_pembayaran"
                            accept="image/jpeg,image/png,image/webp"
                            required
                        >
                        <small>Format JPG, PNG, atau WEBP. Maksimal 5 MB.</small>
                    </div>

                    <label class="payment-confirmation" for="paymentConfirmation">
                        <input type="checkbox" id="paymentConfirmation" required>
                        <span>Saya sudah melakukan pembayaran melalui QRIS dan memastikan bukti pembayaran yang saya unggah sesuai dengan transaksi.</span>
                    </label>
                    <input type="hidden" id="bookingCsrfToken" value="<?= htmlspecialchars($_SESSION["csrf_pemesanan"], ENT_QUOTES, "UTF-8") ?>">
                </div>

                <button
                    type="submit"
                    class="btn btn-primary submit-btn"
                >
                    Pesan sekarang→
                </button>

                <p class="form-disclaimer">
                    Estimasi belum termasuk biaya tambahan seperti tol,
                    parkir, atau tenaga angkut jika diperlukan.
                </p>
            </form>
        </section>
        <?php endif; ?>

        <!-- Ajakan disesuaikan dengan role pengguna. -->
        <section class="cta">
            <?php if ($isPelanggan): ?>
                <h2>Barang siap diangkut?</h2>
                <p>Pilih armada dan rencanakan pengirimanmu sekarang.</p>
                <a href="#pemesanan" class="btn btn-light">
                    Mulai Pemesanan →
                </a>
            <?php elseif ($isAdmin): ?>
                <h2>Kelola Pesanan Angkutin</h2>
                <p>Periksa pesanan pelanggan dan tugaskan kurir dari dashboard admin.</p>
                <a href="admin/index.php" class="btn btn-light">
                    Buka Dashboard Admin →
                </a>
            <?php elseif ($isKurir): ?>
                <h2>Siap Menjalankan Tugas?</h2>
                <p>Lihat pesanan yang telah ditugaskan admin melalui dashboard kurir.</p>
                <a href="kurir/index.php" class="btn btn-light">
                    Lihat Tugas Saya →
                </a>
            <?php endif; ?>
        </section>

    </main>

    <!-- BAGIAN FOOTER -->
    <footer class="footer">
        <a href="#beranda" class="brand footer-brand">
            <img
                src="Gambar/Logo_Usaha.png"
                alt="Logo Angkutin"
                class="logo"
            >

            <span>Angkut<span class="accent">in.</span></span>
        </a>

        <p>
            Solusi angkut barang yang praktis untuk kebutuhanmu.
        </p>

        <small>
            © <span id="year"></span> Angkutin.
            Sesuaikan nama dan informasi bisnis sebelum digunakan.
        </small>
    </footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/Halaman_Utama.js"></script>
    <script src="assets/js/ulasan.js" defer></script>

</body>
</html>
