
<?php
session_start();
require_once "../koneksi.php";

// ========================================
// 1. CEK LOGIN DAN ROLE KURIR
// ========================================
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/Form%20login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$stmtUser = mysqli_prepare(
    $conn,
    "SELECT username, role FROM users WHERE id = ?"
);

mysqli_stmt_bind_param($stmtUser, "i", $user_id);
mysqli_stmt_execute($stmtUser);

$resultUser = mysqli_stmt_get_result($stmtUser);
$user = mysqli_fetch_assoc($resultUser);

mysqli_stmt_close($stmtUser);

if (!$user || $user["role"] !== "kurir") {
    http_response_code(403);
    exit("Akses ditolak. Halaman ini khusus kurir.");
}

// ========================================
// 2. FUNGSI KEAMANAN DAN TOKEN
// ========================================
function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

if (empty($_SESSION["csrf_kurir"])) {
    $_SESSION["csrf_kurir"] = bin2hex(random_bytes(32));
}

// ========================================
// 3. PROSES AKSI KURIR
// ========================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_kurir"], $token)) {
        exit("Permintaan tidak valid. Silakan muat ulang halaman.");
    }

    $pesanan_id = filter_input(
        INPUT_POST,
        "pesanan_id",
        FILTER_VALIDATE_INT
    );

    $aksi = $_POST["aksi"] ?? "";

    if (!$pesanan_id) {
        $_SESSION["flash_kurir"] = [
            "jenis" => "error",
            "pesan" => "ID pesanan tidak valid."
        ];

        header("Location: index.php");
        exit;
    }

    // Pastikan pesanan ditugaskan kepada kurir yang login.
    $stmtCek = mysqli_prepare(
        $conn,
        "SELECT id, status
         FROM pesanan
         WHERE id = ? AND kurir_id = ?"
    );

    mysqli_stmt_bind_param(
        $stmtCek,
        "ii",
        $pesanan_id,
        $user_id
    );

    mysqli_stmt_execute($stmtCek);
    $resultCek = mysqli_stmt_get_result($stmtCek);
    $pesanan = mysqli_fetch_assoc($resultCek);
    mysqli_stmt_close($stmtCek);

    if (!$pesanan) {
        $_SESSION["flash_kurir"] = [
            "jenis" => "error",
            "pesan" => "Pesanan tidak ditemukan atau bukan tugas kamu."
        ];

        header("Location: index.php");
        exit;
    }

    // ====================================
    // 3A. MULAI PROSES PESANAN
    // ====================================
    if ($aksi === "mulai") {

        if ($pesanan["status"] !== "menunggu") {
            $_SESSION["flash_kurir"] = [
                "jenis" => "error",
                "pesan" => "Pesanan tidak dapat dimulai dari status sekarang."
            ];
        } else {
            $stmtUpdate = mysqli_prepare(
                $conn,
                "UPDATE pesanan
                 SET status = 'diproses'
                 WHERE id = ?
                   AND kurir_id = ?
                   AND status = 'menunggu'"
            );

            mysqli_stmt_bind_param(
                $stmtUpdate,
                "ii",
                $pesanan_id,
                $user_id
            );

            mysqli_stmt_execute($stmtUpdate);

            $berhasil = mysqli_stmt_affected_rows($stmtUpdate) === 1;
            mysqli_stmt_close($stmtUpdate);

            $_SESSION["flash_kurir"] = [
                "jenis" => $berhasil ? "success" : "error",
                "pesan" => $berhasil
                    ? "Pesanan berhasil dimulai."
                    : "Status pesanan gagal diperbarui."
            ];
        }

        header("Location: index.php");
        exit;
    }

    // ====================================
    // 3B. SELESAIKAN PESANAN DENGAN FOTO
    // ====================================
    if ($aksi === "selesai") {

        if ($pesanan["status"] !== "diproses") {
            $_SESSION["flash_kurir"] = [
                "jenis" => "error",
                "pesan" => "Pesanan harus berstatus diproses sebelum diselesaikan."
            ];

            header("Location: index.php");
            exit;
        }

        if (
            !isset($_FILES["foto_bukti"]) ||
            $_FILES["foto_bukti"]["error"] !== UPLOAD_ERR_OK
        ) {
            $_SESSION["flash_kurir"] = [
                "jenis" => "error",
                "pesan" => "Foto gagal diunggah. Pilih foto lalu coba lagi."
            ];

            header("Location: index.php");
            exit;
        }

        $foto = $_FILES["foto_bukti"];

        // Batas ukuran foto 5 MB.
        if ($foto["size"] > 5 * 1024 * 1024) {
            $_SESSION["flash_kurir"] = [
                "jenis" => "error",
                "pesan" => "Ukuran foto maksimal 5 MB."
            ];

            header("Location: index.php");
            exit;
        }

        // Validasi isi file foto.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($foto["tmp_name"]);

        $tipeDiizinkan = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];

        if (!isset($tipeDiizinkan[$mime])) {
            $_SESSION["flash_kurir"] = [
                "jenis" => "error",
                "pesan" => "Format foto harus JPG, PNG, atau WEBP."
            ];

            header("Location: index.php");
            exit;
        }

        // Folder tempat penyimpanan foto.
        $folderFoto = __DIR__ . "/../uploads/bukti_pesanan/";

        if (
            !is_dir($folderFoto) &&
            !mkdir($folderFoto, 0755, true)
        ) {
            $_SESSION["flash_kurir"] = [
                "jenis" => "error",
                "pesan" => "Folder foto gagal dibuat."
            ];

            header("Location: index.php");
            exit;
        }

        if (!is_writable($folderFoto)) {
            $_SESSION["flash_kurir"] = [
                "jenis" => "error",
                "pesan" => "Folder foto tidak bisa ditulisi PHP."
            ];

            header("Location: index.php");
            exit;
        }

        // Nama file acak agar tidak menimpa foto lain.
        $namaFile = bin2hex(random_bytes(16))
            . "." . $tipeDiizinkan[$mime];

        $tujuanFile = $folderFoto . $namaFile;

        if (!move_uploaded_file($foto["tmp_name"], $tujuanFile)) {
            $_SESSION["flash_kurir"] = [
                "jenis" => "error",
                "pesan" => "Foto gagal disimpan."
            ];

            header("Location: index.php");
            exit;
        }

        // Simpan lokasi foto dan status ke database.
        $lokasiFoto = "uploads/bukti_pesanan/" . $namaFile;

        $stmtSelesai = mysqli_prepare(
            $conn,
            "UPDATE pesanan
             SET status = 'selesai',
                 foto_bukti = ?,
                 selesai_pada = NOW()
             WHERE id = ?
               AND kurir_id = ?
               AND status = 'diproses'"
        );

        mysqli_stmt_bind_param(
            $stmtSelesai,
            "sii",
            $lokasiFoto,
            $pesanan_id,
            $user_id
        );

        mysqli_stmt_execute($stmtSelesai);

        $berhasil = mysqli_stmt_affected_rows($stmtSelesai) === 1;
        mysqli_stmt_close($stmtSelesai);

        if ($berhasil) {
            $_SESSION["flash_kurir"] = [
                "jenis" => "success",
                "pesan" => "Pesanan selesai dan foto bukti berhasil disimpan."
            ];
        } else {
            if (is_file($tujuanFile)) {
                unlink($tujuanFile);
            }

            $_SESSION["flash_kurir"] = [
                "jenis" => "error",
                "pesan" => "Data gagal diperbarui. Silakan coba lagi."
            ];
        }

        header("Location: index.php");
        exit;
    }

    $_SESSION["flash_kurir"] = [
        "jenis" => "error",
        "pesan" => "Aksi tidak dikenali."
    ];

    header("Location: index.php");
    exit;
}

// ========================================
// 4. AMBIL PESANAN MILIK KURIR YANG LOGIN
// ========================================
$stmtPesanan = mysqli_prepare(
    $conn,
    "SELECT
        p.id,
        p.nama_pelanggan,
        p.nomor_whatsapp,
        p.alamat_penjemputan,
        p.alamat_tujuan,
        p.tanggal_angkut,
        p.jarak_km,
        p.kendaraan,
        p.catatan,
        p.estimasi_biaya,
        p.status,
        p.foto_bukti,
        p.selesai_pada,
        p.created_at
     FROM pesanan p
     WHERE p.kurir_id = ?
     ORDER BY p.created_at DESC"
);

mysqli_stmt_bind_param($stmtPesanan, "i", $user_id);
mysqli_stmt_execute($stmtPesanan);

$resultPesanan = mysqli_stmt_get_result($stmtPesanan);

$flash = $_SESSION["flash_kurir"] ?? null;
unset($_SESSION["flash_kurir"]);

$labelStatus = [
    "menunggu" => "Menunggu",
    "diproses" => "Sedang diproses",
    "selesai" => "Selesai",
    "dibatalkan" => "Dibatalkan"
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard Kurir - Angkutin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f3f6fa;
            color: #243247;
        }

        header {
            background: linear-gradient(120deg, #102844, #1e4774);
            color: white;
            padding: 17px max(3%, calc((100% - 1120px) / 2));
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 5px 22px rgba(15, 35, 60, .18);
        }

        .header-title h1 { margin: 0; font-size: 22px; letter-spacing: .2px; }
        .header-title p { color: #d8e2ef; margin: 3px 0 0; font-size: 13px; }

        .logout-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 9px;
            background: #e53935;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            white-space: nowrap;
            transition: background .2s, transform .2s;
        }
        .logout-button:hover { background: #c62828; transform: translateY(-1px); }

        main {
            width: min(1000px, 94%);
            margin: 30px auto;
        }

        main > h2 { color: #142b49; margin: 0 0 18px; font-size: 25px; }

        .panel {
            background: white;
            border: 1px solid #e0e6ef;
            border-radius: 15px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(20, 43, 73, .045);
        }
        .panel h3 { color: #142b49; margin-top: 0; }

        .detail {
            line-height: 1.65;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 20px;
            background: #e8eef7;
            color: #23466f;
            font-size: 13px;
            font-weight: bold;
        }

        .status.diproses {
            background: #fff0d5;
            color: #875600;
        }

        .status.selesai {
            background: #e0f5e8;
            color: #17683a;
        }

        .status.dibatalkan {
            background: #fde7e7;
            color: #a12626;
        }

        button, input[type="file"] {
            width: 100%;
            margin-top: 10px;
        }

        button {
            padding: 12px;
            background: #142b49;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
        }

        button:hover {
            background: #254b78;
        }

        input[type="file"] {
            padding: 10px;
            border: 1px solid #ccd5e2;
            border-radius: 7px;
        }

        .notice {
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .success {
            background: #e0f5e8;
            color: #17683a;
        }

        .error {
            background: #fde7e7;
            color: #a12626;
        }

        .proof {
            width: 320px;
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            border: 1px solid #e0e6ef;
        }

        .empty {
            text-align: center;
            color: #68778c;
            padding: 25px;
        }

        @media (max-width: 600px) {
            header { padding: 13px 4%; gap: 10px; }
            .header-title h1 { font-size: 18px; }
            .header-title p { font-size: 11px; }
            .logout-button { padding: 9px 11px; font-size: 12px; }
            .panel { padding: 16px; }
        }
    </style>
</head>

<body>

<header>
    <div class="header-title">
        <h1>Angkutin <span style="color:#ffb24a;">| Dashboard Kurir</span></h1>
        <p>Halo, <?= e($user["username"]) ?>. Berikut tugas pengangkutanmu.</p>
    </div>
    <a class="logout-button" href="../auth/logout.php"
       onclick="return confirm('Yakin ingin logout dari Angkutin?');">
        Logout
    </a>
</header>

<main>

    <?php if ($flash): ?>
        <div class="notice <?= e($flash["jenis"]) ?>">
            <?= e($flash["pesan"]) ?>
        </div>
    <?php endif; ?>

    <h2>Daftar Tugas Saya</h2>

    <?php if (mysqli_num_rows($resultPesanan) > 0): ?>

        <?php while ($p = mysqli_fetch_assoc($resultPesanan)): ?>
            <?php
                $status = $p["status"];
                $label = $labelStatus[$status] ?? $status;
            ?>

            <section class="panel">
                <h3>Pesanan #<?= e($p["id"]) ?></h3>

                <p>
                    Status:
                    <span class="status <?= e($status) ?>">
                        <?= e($label) ?>
                    </span>
                </p>

                <div class="detail">
                    <p>
                        <strong>Nama pelanggan:</strong>
                        <?= e($p["nama_pelanggan"]) ?>
                    </p>

                    <p>
                        <strong>WhatsApp:</strong>
                        <?= e($p["nomor_whatsapp"]) ?>
                    </p>

                    <p>
                        <strong>Alamat penjemputan:</strong><br>
                        <?= e($p["alamat_penjemputan"]) ?>
                    </p>

                    <p>
                        <strong>Alamat tujuan:</strong><br>
                        <?= e($p["alamat_tujuan"]) ?>
                    </p>

                    <p>
                        <strong>Tanggal angkut:</strong>
                        <?= e($p["tanggal_angkut"]) ?>
                    </p>

                    <p>
                        <strong>Kendaraan:</strong>
                        <?= e($p["kendaraan"]) ?>
                    </p>

                    <p>
                        <strong>Jarak:</strong>
                        <?= e($p["jarak_km"]) ?> km
                    </p>

                    <p>
                        <strong>Catatan:</strong>
                        <?= e($p["catatan"] ?: "-") ?>
                    </p>
                </div>

                <?php if ($status === "menunggu"): ?>

                    <form method="POST" action="index.php">
                        <input type="hidden" name="csrf_token"
                            value="<?= e($_SESSION["csrf_kurir"]) ?>">

                        <input type="hidden" name="pesanan_id"
                            value="<?= e($p["id"]) ?>">

                        <input type="hidden" name="aksi" value="mulai">

                        <button type="submit">
                            Mulai Proses Pesanan
                        </button>
                    </form>

                <?php elseif ($status === "diproses"): ?>

                    <hr>

                    <h4>Unggah Foto Bukti Penyelesaian</h4>

                    <p>
                        Pilih foto yang menunjukkan pesanan sudah selesai diantar.
                    </p>

                    <form method="POST" action="index.php"
                          enctype="multipart/form-data">

                        <input type="hidden" name="csrf_token"
                            value="<?= e($_SESSION["csrf_kurir"]) ?>">

                        <input type="hidden" name="pesanan_id"
                            value="<?= e($p["id"]) ?>">

                        <input type="hidden" name="aksi" value="selesai">

                        <label for="foto_<?= e($p["id"]) ?>">
                            Foto bukti (JPG, PNG, WEBP; maksimal 5 MB)
                        </label>

                        <input
                            type="file"
                            id="foto_<?= e($p["id"]) ?>"
                            name="foto_bukti"
                            accept=".jpg,.jpeg,.png,.webp"
                            required>

                        <button type="submit">
                            Kirim Bukti & Selesaikan Pesanan
                        </button>
                    </form>

                <?php elseif ($status === "selesai"): ?>

                    <p><strong>Pesanan selesai.</strong></p>

                    <?php if (!empty($p["selesai_pada"])): ?>
                        <p>
                            Waktu selesai:
                            <?= e($p["selesai_pada"]) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($p["foto_bukti"])): ?>
                        <p>Foto bukti:</p>

                        <a href="../<?= e($p["foto_bukti"]) ?>"
                           target="_blank" rel="noopener">
                            <img
                                class="proof"
                                src="../<?= e($p["foto_bukti"]) ?>"
                                alt="Foto bukti pesanan selesai">
                        </a>
                    <?php else: ?>
                        <p>Foto bukti belum tercatat.</p>
                    <?php endif; ?>

                <?php elseif ($status === "dibatalkan"): ?>

                    <p>Pesanan ini telah dibatalkan.</p>

                <?php endif; ?>
            </section>

        <?php endwhile; ?>

    <?php else: ?>

        <section class="panel empty">
            <h3>Belum ada tugas</h3>
            <p>
                Pesanan yang ditugaskan admin kepadamu
                akan muncul di sini.
            </p>
        </section>

    <?php endif; ?>
</main>

</body>
</html>

<?php
mysqli_stmt_close($stmtPesanan);
mysqli_close($conn);
?>
