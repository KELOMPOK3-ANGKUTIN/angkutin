<?php
// Bagian ini dipanggil dari Halaman_utama.php. Session sudah aktif.
require_once __DIR__ . "/koneksi.php";

if (empty($_SESSION["csrf_ulasan"])) {
    $_SESSION["csrf_ulasan"] = bin2hex(random_bytes(32));
}

$ulasanStatus = $_GET["ulasan"] ?? "";
$ulasanPesanStatus = [
    "berhasil" => "Terima kasih! Ulasan kamu berhasil dikirim.",
    "rating" => "Pilih rating antara 1 sampai 5 bintang.",
    "pesan" => "Pesan ulasan harus terdiri dari 5 sampai 1000 karakter.",
    "gagal" => "Ulasan belum berhasil disimpan. Silakan coba lagi.",
    "akses" => "Silakan login sebagai pelanggan untuk memberikan ulasan."
];

$daftarUlasan = [];
$sqlUlasan = "SELECT ulasan.id, users.username, ulasan.rating, ulasan.pesan, ulasan.created_at
              FROM ulasan
              INNER JOIN users ON users.id = ulasan.user_id
              ORDER BY ulasan.created_at DESC, ulasan.id DESC";
$hasilUlasan = mysqli_query($conn, $sqlUlasan);

if ($hasilUlasan) {
    while ($barisUlasan = mysqli_fetch_assoc($hasilUlasan)) {
        $daftarUlasan[] = $barisUlasan;
    }
}
?>

<div class="reviews-inner">
    <div class="reviews-heading">
        <span class="reviews-eyebrow">ULASAN PELANGGAN</span>
        <h2>Apa Kata Mereka?</h2>
        <p>Pengalaman pelanggan yang telah menggunakan layanan Angkutin.</p>
    </div>

    <?php if (isset($ulasanPesanStatus[$ulasanStatus])): ?>
        <div class="review-alert <?= $ulasanStatus === 'berhasil' ? 'success' : 'error' ?>" role="status">
            <?= htmlspecialchars($ulasanPesanStatus[$ulasanStatus], ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <div class="reviews-carousel" id="reviewsCarousel" aria-label="Daftar ulasan pelanggan">
        <div class="reviews-track" id="reviewsTrack">
            <?php if (count($daftarUlasan) > 0): ?>
                <?php foreach ($daftarUlasan as $item): ?>
                    <article class="review-card">
                        <div class="review-stars" aria-label="<?= (int)$item["rating"] ?> dari 5 bintang">
                            <?php for ($bintang = 1; $bintang <= 5; $bintang++): ?>
                                <span class="<?= $bintang <= (int)$item["rating"] ? "star-active" : "star-inactive" ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <p class="review-message">“<?= htmlspecialchars($item["pesan"], ENT_QUOTES, "UTF-8") ?>”</p>
                        <div class="review-user">
                            <div class="review-avatar" aria-hidden="true">
                                <?= htmlspecialchars(mb_strtoupper(mb_substr($item["username"], 0, 1)), ENT_QUOTES, "UTF-8") ?>
                            </div>
                            <div class="review-user-info">
                                <h3><?= htmlspecialchars($item["username"], ENT_QUOTES, "UTF-8") ?></h3>
                                <span><?= htmlspecialchars(date("d M Y", strtotime($item["created_at"])), ENT_QUOTES, "UTF-8") ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <article class="review-empty">
                    <div class="review-empty-icon">⭐</div>
                    <h3>Belum ada ulasan pelanggan</h3>
                    <p>Jadilah pelanggan pertama yang berbagi pengalaman menggunakan Angkutin.</p>
                </article>
            <?php endif; ?>
        </div>
    </div>

    <div class="review-controls">
        <button type="button" id="reviewPrev" aria-label="Geser ke ulasan sebelumnya">←</button>
        <span>Geser dengan mouse atau sentuh layar untuk melihat ulasan</span>
        <button type="button" id="reviewNext" aria-label="Geser ke ulasan berikutnya">→</button>
    </div>

    <div class="review-form-container">
        <div class="review-form-heading">
            <span class="reviews-eyebrow">CERITAKAN PENGALAMANMU</span>
            <h2>Berikan Ulasan</h2>
            <p>Masukan kamu membantu kami meningkatkan layanan Angkutin.</p>
        </div>

        <?php if (empty($_SESSION["user_id"])): ?>
            <div class="review-notice">
                Kamu harus <a href="auth/Form%20login.php">login</a> terlebih dahulu untuk memberikan ulasan.
            </div>
        <?php elseif (($_SESSION["role"] ?? "pelanggan") !== "pelanggan"): ?>
            <div class="review-notice">
                Formulir ulasan hanya tersedia untuk akun pelanggan.
            </div>
        <?php else: ?>
            <form action="ulasan/simpan.php" method="POST" class="review-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_ulasan"], ENT_QUOTES, "UTF-8") ?>">

                <label>Rating kamu</label>
                <div class="rating-picker" aria-label="Pilih rating bintang">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="rating<?= $i ?>" name="rating" value="<?= $i ?>" required>
                        <label for="rating<?= $i ?>" title="<?= $i ?> bintang">★</label>
                    <?php endfor; ?>
                </div>
                <p class="rating-hint" id="ratingHint">Pilih bintang 1 sampai 5</p>

                <label for="reviewMessage">Pesan ulasan</label>
                <textarea id="reviewMessage" name="pesan" rows="4" minlength="5" maxlength="1000"
                    placeholder="Ceritakan pengalamanmu menggunakan layanan Angkutin..." required></textarea>
                <small class="review-char-help">Minimal 5 dan maksimal 1000 karakter.</small>

                <button type="submit" class="review-submit">Kirim Ulasan →</button>
            </form>
        <?php endif; ?>
    </div>
</div>
