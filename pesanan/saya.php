<?php
session_start();

// 1. Pastikan pengguna sudah login.
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/Form%20login.php");
    exit;
}

$role = $_SESSION["role"] ?? "";

// Halaman Pesanan Saya hanya untuk pelanggan.
if ($role !== "pelanggan") {

    if ($role === "admin") {
        header("Location: ../admin/index.php");
    } elseif ($role === "kurir") {
        header("Location: ../kurir/index.php");
    } else {
        header("Location: ../auth/Form%20login.php");
    }

    exit;
}

require_once "../koneksi.php";

$user_id = (int) $_SESSION["user_id"];

$sql = "SELECT id, kendaraan, alamat_penjemputan, alamat_tujuan,
               tanggal_angkut, jarak_km, estimasi_biaya,
               status, created_at
        FROM pesanan
        WHERE user_id = ?
        ORDER BY created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$labelStatus = [
    "menunggu" => "Menunggu konfirmasi",
    "diproses" => "Pesanan diproses",
    "selesai" => "Pesanan selesai",
    "dibatalkan" => "Pesanan dibatalkan"
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - Angkutin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fa;
            color: #243247;
        }

        header {
            background: #142b49;
            color: white;
            padding: 20px;
        }

        .header-inner,
        main {
            width: min(1000px, 92%);
            margin: auto;
        }

        header a {
            color: white;
            text-decoration: none;
        }

        main {
            padding: 28px 0;
        }

        .order-card {
            background: white;
            padding: 20px;
            margin-bottom: 18px;
            border-radius: 12px;
            border: 1px solid #e0e5ed;
        }

        .order-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .status {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 20px;
            background: #e8eef7;
            color: #23466f;
            font-size: 13px;
            font-weight: bold;
        }

        .status.selesai {
            background: #e0f5e8;
            color: #17683a;
        }

        .status.dibatalkan {
            background: #fde7e7;
            color: #a12626;
        }

        .status.diproses {
            background: #fff0d5;
            color: #875600;
        }

        .detail {
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .price {
            font-size: 20px;
            font-weight: bold;
            color: #142b49;
        }


        .empty {
            background: white;
            padding: 30px;
            text-align: center;
            border-radius: 12px;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            color: #245b96;
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .order-card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
<header>
    <div class="header-inner">
        <a href="../Halaman_utama.php">
            <strong>Angkutin</strong> — Pesanan Saya
        </a>
    </div>
</header>

<main>
    <a class="back" href="../Halaman_utama.php">
        &larr; Kembali ke halaman utama
    </a>

    <h1>Pesanan Saya</h1>
    <p>Pantau status dan detail pesanan jasa angkut kamu di sini.</p>
    <?php if (mysqli_num_rows($result) === 0): ?>
        <div class="empty">
            <h3>Belum ada pesanan</h3>
            <p>Pesanan yang kamu buat akan muncul di halaman ini.</p>
            <a href="../Halaman_utama.php#pemesanan">
                Buat pesanan sekarang
            </a>
        </div>
    <?php else: ?>

        <?php while ($pesanan = mysqli_fetch_assoc($result)): ?>
            <?php
                $status = $pesanan["status"];
                $label = $labelStatus[$status] ?? "Status tidak diketahui";
            ?>

            <section class="order-card">
                <div class="order-top">
                    <h3>Pesanan #<?= e($pesanan["id"]) ?></h3>

                    <span class="status <?= e($status) ?>">
                        <?= e($label) ?>
                    </span>
                </div>

                <div class="detail">
                    <p>
                        <strong>Kendaraan:</strong>
                        <?= e($pesanan["kendaraan"]) ?>
                    </p>

                    <p>
                        <strong>Tanggal angkut:</strong>
                        <?= e($pesanan["tanggal_angkut"]) ?>
                    </p>

                    <p>
                        <strong>Alamat jemput:</strong>
                        <?= e($pesanan["alamat_penjemputan"]) ?>
                    </p>

                    <p>
                        <strong>Alamat tujuan:</strong>
                        <?= e($pesanan["alamat_tujuan"]) ?>
                    </p>

                    <p>
                        <strong>Jarak:</strong>
                        <?= e($pesanan["jarak_km"]) ?> km
                    </p>

                    <p class="price">
                        Estimasi biaya:
                        Rp<?= number_format(
                            (float) $pesanan["estimasi_biaya"],
                            0,
                            ",",
                            "."
                        ) ?>
                    </p>
                </div>

                <p>
                    <small>
                        Dibuat pada: <?= e($pesanan["created_at"]) ?>
                    </small>
                </p>
            </section>
        <?php endwhile; ?>

    <?php endif; ?>
</main>
</body>
</html>

<?php
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
