<?php
session_start();
require_once "../koneksi.php";

function kembali($pesan) {
    $_SESSION["flash_pembayaran"] = $pesan;
    header("Location: saya.php");
    exit;
}

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "pelanggan") {
    header("Location: ../auth/Form%20login.php");
    exit;
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Metode tidak diizinkan.");
}
$token = $_POST["csrf_token"] ?? "";
if (empty($_SESSION["csrf_pembayaran"]) || !hash_equals($_SESSION["csrf_pembayaran"], $token)) {
    kembali("Permintaan tidak valid. Muat ulang halaman dan coba lagi.");
}
$pesananId = filter_input(INPUT_POST, "pesanan_id", FILTER_VALIDATE_INT);
if (!$pesananId || !isset($_FILES["bukti_pembayaran"])) {
    kembali("Data upload tidak lengkap.");
}

$stmt = mysqli_prepare($conn, "SELECT id, status, bukti_pembayaran FROM pesanan WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $pesananId, $_SESSION["user_id"]);
mysqli_stmt_execute($stmt);
$pesanan = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$pesanan) kembali("Pesanan tidak ditemukan.");
if ($pesanan["status"] === "dibatalkan") kembali("Pesanan yang dibatalkan tidak dapat menerima bukti pembayaran.");

$file = $_FILES["bukti_pembayaran"];
if ($file["error"] !== UPLOAD_ERR_OK) kembali("Upload gagal. Pilih ulang foto bukti pembayaran.");
if ($file["size"] < 1 || $file["size"] > 5 * 1024 * 1024) kembali("Ukuran foto maksimal 5 MB.");

$info = @getimagesize($file["tmp_name"]);
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file["tmp_name"]);
$allowed = [
    "image/jpeg" => "jpg",
    "image/png" => "png",
    "image/webp" => "webp"
];
if (!$info || !isset($allowed[$mime])) kembali("File harus berupa gambar JPG, PNG, atau WEBP yang valid.");

$folder = dirname(__DIR__) . "/uploads/bukti_pembayaran";
if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
    kembali("Folder penyimpanan bukti belum dapat dibuat.");
}
$namaFile = bin2hex(random_bytes(16)) . "." . $allowed[$mime];
$tujuan = $folder . "/" . $namaFile;
if (!move_uploaded_file($file["tmp_name"], $tujuan)) {
    kembali("Foto gagal disimpan. Silakan coba lagi.");
}
$pathRelatif = "uploads/bukti_pembayaran/" . $namaFile;

$update = mysqli_prepare($conn, "UPDATE pesanan SET bukti_pembayaran = ?, status_pembayaran = 'diunggah' WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($update, "sii", $pathRelatif, $pesananId, $_SESSION["user_id"]);
if (!mysqli_stmt_execute($update)) {
    @unlink($tujuan);
    mysqli_stmt_close($update);
    kembali("Bukti pembayaran gagal dicatat ke database.");
}
mysqli_stmt_close($update);

// Hapus file bukti lama milik pesanan ini setelah file baru berhasil dicatat.
if (!empty($pesanan["bukti_pembayaran"])) {
    $lama = dirname(__DIR__) . "/" . $pesanan["bukti_pembayaran"];
    if (is_file($lama) && strpos(realpath($lama), realpath($folder) . DIRECTORY_SEPARATOR) === 0) {
        @unlink($lama);
    }
}
mysqli_close($conn);
kembali("Bukti pembayaran berhasil diunggah. Admin dapat melihatnya di dashboard.");
