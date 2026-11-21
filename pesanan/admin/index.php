
<?php
session_start();
require_once "../koneksi.php";

// ==============================
// 1. CEK LOGIN DAN ROLE ADMIN
// ==============================
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/Form%20login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];

$stmt = mysqli_prepare(
    $conn,
    "SELECT role FROM users WHERE id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$resultRole = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($resultRole);
mysqli_stmt_close($stmt);

if (!$user || $user["role"] !== "admin") {
    http_response_code(403);
    exit("Akses ditolak. Halaman ini khusus admin.");
}

// ==============================
// 2. TOKEN KEAMANAN FORM
// ==============================
if (empty($_SESSION["csrf_admin"])) {
    $_SESSION["csrf_admin"] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// ==============================
// 3. PROSES PENUGASAN KURIR
// ==============================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["csrf_admin"], $token)) {
        exit("Permintaan tidak valid. Muat ulang halaman.");
    }

    $pesanan_id = filter_input(
        INPUT_POST,
        "pesanan_id",
        FILTER_VALIDATE_INT
    );

    $kurir_id = filter_input(
        INPUT_POST,
        "kurir_id",
        FILTER_VALIDATE_INT
    );

    if (!$pesanan_id || $kurir_id === false) {
        $_SESSION["flash_admin"] = [
            "jenis" => "error",
            "pesan" => "Data pesanan atau kurir tidak valid."
        ];
    } else {
        // Pastikan akun yang dipilih benar-benar kurir.
        $stmtKurir = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE id = ? AND role = 'kurir'"
        );

        mysqli_stmt_bind_param(
            $stmtKurir,
            "i",
            $kurir_id
        );

        mysqli_stmt_execute($stmtKurir);
        $resultKurir = mysqli_stmt_get_result($stmtKurir);
        $kurirValid = mysqli_fetch_assoc($resultKurir);
        mysqli_stmt_close($stmtKurir);

        if (!$kurirValid) {
            $_SESSION["flash_admin"] = [
                "jenis" => "error",
                "pesan" => "Akun yang dipilih bukan akun kurir."
            ];
        } else {
            // Pesanan selesai atau dibatalkan tidak bisa ditugaskan ulang.
            $stmtCek = mysqli_prepare(
                $conn,
                "SELECT status FROM pesanan WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmtCek,
                "i",
                $pesanan_id
            );

            mysqli_stmt_execute($stmtCek);
            $resultCek = mysqli_stmt_get_result($stmtCek);
            $pesanan = mysqli_fetch_assoc($resultCek);
            mysqli_stmt_close($stmtCek);

            if (!$pesanan) {
                $_SESSION["flash_admin"] = [
                    "jenis" => "error",
                    "pesan" => "Pesanan tidak ditemukan."
                ];
            } elseif (
                in_array(
                    $pesanan["status"],
                    ["selesai", "dibatalkan"],
                    true
                )
            ) {
                $_SESSION["flash_admin"] = [
                    "jenis" => "error",
                    "pesan" => "Pesanan selesai atau dibatalkan tidak bisa ditugaskan ulang."
                ];
            } else {
                $stmtUpdate = mysqli_prepare(
                    $conn,
                    "UPDATE pesanan SET kurir_id = ? WHERE id = ?"
                );

                mysqli_stmt_bind_param(
                    $stmtUpdate,
                    "ii",
                    $kurir_id,
                    $pesanan_id
                );

                if (mysqli_stmt_execute($stmtUpdate)) {
                    $_SESSION["flash_admin"] = [
                        "jenis" => "success",
                        "pesan" => "Pesanan berhasil ditugaskan kepada kurir."
                    ];
                } else {
                    $_SESSION["flash_admin"] = [
                        "jenis" => "error",
                        "pesan" => "Penugasan gagal disimpan."
                    ];
                }

                mysqli_stmt_close($stmtUpdate);
            }
        }
    }

    header("Location: index.php");
    exit;
}

// ==============================
// 4. NOTIFIKASI
// ==============================
$flash = $_SESSION["flash_admin"] ?? null;
unset($_SESSION["flash_admin"]);

// ==============================
// 5. AMBIL SEMUA AKUN KURIR
// ==============================
$resultKurir = mysqli_query(
    $conn,
    "SELECT id, username, email
     FROM users
     WHERE role = 'kurir'
     ORDER BY username ASC"
);

$daftarKurir = [];

if ($resultKurir) {
    while ($k = mysqli_fetch_assoc($resultKurir)) {
        $daftarKurir[] = $k;
    }
}

// ==============================
// 6. STATISTIK PESANAN
// ==============================
$statistik = [
    "total" => 0,
    "menunggu" => 0,
    "diproses" => 0,
    "selesai" => 0,
    "dibatalkan" => 0
];

$resultStatistik = mysqli_query(
    $conn,
    "SELECT status, COUNT(*) AS jumlah
     FROM pesanan
     GROUP BY status"
);

if ($resultStatistik) {
    while ($baris = mysqli_fetch_assoc($resultStatistik)) {
        $status = $baris["status"];
        $jumlah = (int) $baris["jumlah"];

        $statistik["total"] += $jumlah;

        if (array_key_exists($status, $statistik)) {
            $statistik[$status] = $jumlah;
        }
    }
}

// ==============================
// 7. AMBIL SEMUA PESANAN
// ==============================
$sqlPesanan = "
    SELECT
        p.*,
        pelanggan.username AS username_pelanggan,
        kurir.username AS username_kurir
    FROM pesanan p
    LEFT JOIN users pelanggan
        ON p.user_id = pelanggan.id
    LEFT JOIN users kurir
        ON p.kurir_id = kurir.id
    ORDER BY p.created_at DESC
";

$resultPesanan = mysqli_query($conn, $sqlPesanan);

if (!$resultPesanan) {
    exit("Gagal mengambil data pesanan. Periksa struktur tabel pesanan.");
}

$labelStatus = [
    "menunggu" => "Menunggu",
    "diproses" => "Diproses",
    "selesai" => "Selesai",
    "dibatalkan" => "Dibatalkan"
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Angkutin</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            color: #243247;
        }

        header {
            background: #142b49;
            color: white;
            padding: 22px 5%;
        }

        header h1 { margin: 0; font-size: 25px; }

        header p {
            margin-bottom: 0;
            color: #d8e2ef;
        }

        .container {
            width: min(1250px, 94%);
            margin: 28px auto;
        }

        a { color: #245b96; }

        .top-link {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin: 20px 0 28px;
        }

        .stat-card, .panel {
            background: white;
            border: 1px solid #e0e6ef;
            border-radius: 12px;
        }

        .stat-card { padding: 18px; }

        .stat-card p {
            color: #68778c;
            font-size: 14px;
            margin: 0 0 12px;
        }

        .stat-card strong { font-size: 28px; }

        .panel {
            padding: 20px;
            margin-bottom: 24px;
        }

        .panel h2 { margin-top: 0; }

        .table-wrapper { overflow-x: auto; }

        table {
            width: 100%;
            min-width: 1150px;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px 12px;
            border-bottom: 1px solid #e7ebf1;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #f7f9fc;
            white-space: nowrap;
        }

        td p {
            margin: 0 0 8px;
            line-height: 1.5;
        }

        select, button {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccd5e2;
            border-radius: 7px;
            font-size: 13px;
        }

        button {
            background: #142b49;
            color: white;
            border: 0;
            cursor: pointer;
            margin-top: 8px;
        }

        button:hover { background: #254b78; }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #e8eef7;
            color: #23466f;
            font-size: 12px;
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

        .notice {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .notice.success {
            background: #e0f5e8;
            color: #17683a;
        }

        .notice.error {
            background: #fde7e7;
            color: #a12626;
        }

        .empty {
            padding: 25px;
            text-align: center;
            color: #68778c;
        }

        @media (max-width: 850px) {
            .stats { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 450px) {
            .stats { gap: 9px; }
            .stat-card { padding: 13px; }
            .stat-card strong { font-size: 23px; }
            .panel { padding: 14px; }
        }
    </style>
</head>

<body>

<header>
    <h1>Angkutin — Dashboard Admin</h1>
    <p>Kelola pesanan dan tugaskan pekerjaan kepada kurir.</p>
</header>

<main class="container">

    <a class="top-link" href="../Halaman_utama.php">
        &larr; Kembali ke halaman utama
    </a>

    <h2>Ringkasan Pesanan</h2>

    <?php if ($flash): ?>
        <div class="notice <?= e($flash["jenis"]) ?>">
            <?= e($flash["pesan"]) ?>
        </div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat-card">
            <p>Total pesanan</p>
            <strong><?= $statistik["total"] ?></strong>
        </div>

        <div class="stat-card">
            <p>Menunggu</p>
            <strong><?= $statistik["menunggu"] ?></strong>
        </div>

        <div class="stat-card">
            <p>Diproses</p>
            <strong><?= $statistik["diproses"] ?></strong>
        </div>

        <div class="stat-card">
            <p>Selesai</p>
            <strong><?= $statistik["selesai"] ?></strong>
        </div>

        <div class="stat-card">
            <p>Dibatalkan</p>
            <strong><?= $statistik["dibatalkan"] ?></strong>
        </div>
    </section>

    <section class="panel">
        <h2>Daftar Pesanan Masuk</h2>
        <p>Pilih akun kurir untuk menugaskan setiap pesanan. Status pekerjaan diperbarui oleh kurir.</p>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pelanggan</th>
                        <th>Detail perjalanan</th>
                        <th>Tanggal angkut</th>
                        <th>Biaya</th>
                        <th>Status</th>
                        <th>Kurir yang ditugaskan</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($resultPesanan) > 0): ?>

                    <?php while ($p = mysqli_fetch_assoc($resultPesanan)): ?>
                        <?php
                            $status = $p["status"];
                            $label = $labelStatus[$status] ?? $status;
                            $tidakBisaDitugaskan = in_array(
                                $status,
                                ["selesai", "dibatalkan"],
                                true
                            );
                        ?>

                        <tr>
                            <td>
                                #<?= e($p["id"]) ?><br>
                                <small>Akun ID <?= e($p["user_id"]) ?></small>
                            </td>

                            <td>
                                <p><strong><?= e($p["nama_pelanggan"]) ?></strong></p>
                                <p>Akun: <?= e($p["username_pelanggan"] ?? "Tidak ditemukan") ?></p>
                                <p>WA: <?= e($p["nomor_whatsapp"]) ?></p>
                            </td>

                            <td>
                                <p><strong><?= e($p["kendaraan"]) ?></strong></p>
                                <p>Dari: <?= e($p["alamat_penjemputan"]) ?></p>
                                <p>Ke: <?= e($p["alamat_tujuan"]) ?></p>
                                <p>Jarak: <?= e($p["jarak_km"]) ?> km</p>
                                <p>Catatan: <?= e($p["catatan"] ?: "-") ?></p>
                            </td>

                            <td>
                                <?= e($p["tanggal_angkut"]) ?><br><br>
                                <small>Dibuat: <?= e($p["created_at"]) ?></small>
                            </td>

                            <td>
                                Rp<?= number_format(
                                    (float) $p["estimasi_biaya"],
                                    0,
                                    ",",
                                    "."
                                ) ?>
                            </td>

                            <td>
                                <span class="status <?= e($status) ?>">
                                    <?= e($label) ?>
                                </span>

                                <p style="margin-top:10px"><strong>Pembayaran:</strong>
                                    <span class="status <?= !empty($p["bukti_pembayaran"]) ? "selesai" : "diproses" ?>">
                                        <?= !empty($p["bukti_pembayaran"]) ? "Bukti diunggah" : "Menunggu bukti" ?>
                                    </span>
                                </p>
                                <?php if (!empty($p["bukti_pembayaran"])): ?>
                                    <p><a href="../<?= e($p["bukti_pembayaran"]) ?>" target="_blank" rel="noopener">
                                        <img src="../<?= e($p["bukti_pembayaran"]) ?>" alt="Bukti pembayaran pesanan #<?= e($p["id"]) ?>"
                                             style="display:block;width:150px;max-height:190px;object-fit:contain;margin-top:8px;border:1px solid #dce4ef;border-radius:7px;background:#f8fafc;">
                                        Lihat bukti pembayaran ukuran penuh
                                    </a></p>
                                <?php endif; ?>
                                <?php if (!empty($p["foto_bukti"])): ?>
                                    <p style="margin-top:10px">
                                        <a href="../<?= e($p["foto_bukti"]) ?>" target="_blank" rel="noopener">
                                            Lihat foto penyelesaian kurir
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (!empty($p["kurir_id"])): ?>
                                    <p>
                                        Ditugaskan kepada:
                                        <strong><?= e($p["username_kurir"] ?? "Akun kurir") ?></strong>
                                    </p>
                                <?php else: ?>
                                    <p><strong>Belum ditugaskan</strong></p>
                                <?php endif; ?>

                                <?php if (!$tidakBisaDitugaskan): ?>
                                    <?php if (count($daftarKurir) > 0): ?>
                                        <form method="POST" action="index.php">
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION["csrf_admin"]) ?>">

                                            <input
                                                type="hidden"
                                                name="pesanan_id"
                                                value="<?= e($p["id"]) ?>">

                                            <select name="kurir_id" required>
                                                <option value="">-- Pilih kurir --</option>

                                                <?php foreach ($daftarKurir as $k): ?>
                                                    <option
                                                        value="<?= e($k["id"]) ?>"
                                                        <?= (int) $p["kurir_id"] === (int) $k["id"] ? "selected" : "" ?>>
                                                        <?= e($k["username"]) ?>
                                                        (ID <?= e($k["id"]) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button type="submit">
                                                Tugaskan Kurir
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <p>Belum ada akun ber-role kurir.</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small>Penugasan dikunci untuk status ini.</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty">
                            Belum ada pesanan masuk.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>

<?php
mysqli_close($conn);
?>
