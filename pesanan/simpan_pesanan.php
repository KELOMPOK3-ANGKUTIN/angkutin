<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once "../koneksi.php";

function kirimRespons($berhasil, $pesan, $kode = 400) {
    http_response_code($kode);
    echo json_encode(["berhasil" => $berhasil, "pesan" => $pesan]);
    exit;
}

if (!isset($_SESSION["user_id"])) {
    kirimRespons(false, "Sesi login berakhir. Silakan login kembali.", 401);
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    kirimRespons(false, "Metode permintaan tidak diizinkan.", 405);
}
if (($_SESSION["role"] ?? "") !== "pelanggan") {
    kirimRespons(false, "Hanya pelanggan yang dapat membuat pesanan.", 403);
}
$sessionToken = $_SESSION["csrf_pemesanan"] ?? "";
$token = $_POST["csrf_token"] ?? "";
error_log("Jumlah field POST: " . count($_POST));
error_log("Content-Length: " . ($_SERVER["CONTENT_LENGTH"] ?? "TIDAK ADA"));
error_log("Bukti file terdeteksi: " . (isset($_FILES["bukti_pembayaran"]) ? "ADA" : "TIDAK"));

error_log("=== DEBUG CSRF ANGKUTIN ===");
error_log("Token sesi: " . ($sessionToken !== "" ? "ADA" : "KOSONG"));
error_log("Token POST: " . ($token !== "" ? "ADA" : "KOSONG"));
error_log("Panjang token sesi: " . strlen($sessionToken));
error_log("Panjang token POST: " . strlen($token));
error_log("Token cocok: " . (($sessionToken !== "" && hash_equals($sessionToken, $token)) ? "YA" : "TIDAK"));

if (empty($_SESSION["csrf_pemesanan"]) || !hash_equals($_SESSION["csrf_pemesanan"], $token)) {
    kirimRespons(false, "Sesi form tidak valid. Muat ulang halaman dan coba lagi.", 403);
}

if (($_POST["payment_confirmation"] ?? "") !== "yes") {
    kirimRespons(false, "Konfirmasi pembayaran wajib disetujui.");
}

$user_id = (int) $_SESSION["user_id"];
$nama = trim($_POST["customerName"] ?? "");
$nomor = trim($_POST["customerPhone"] ?? "");
$penjemputan = trim($_POST["pickup"] ?? "");
$tujuan = trim($_POST["destination"] ?? "");
$tanggal = trim($_POST["bookingDate"] ?? "");
$jarakInput = $_POST["distance"] ?? "";
$kendaraan = trim($_POST["vehicle"] ?? "");
$catatan = trim($_POST["notes"] ?? "");
$biayaInput = $_POST["estimatedPrice"] ?? "";

if ($nama === "" || $nomor === "" || $penjemputan === "" || $tujuan === "" || $tanggal === "" || $jarakInput === "" || $kendaraan === "" || $biayaInput === "") {
    kirimRespons(false, "Mohon lengkapi semua data wajib.");
}
if (!preg_match('/^[0-9+() -]{9,20}$/', $nomor)) {
    kirimRespons(false, "Nomor WhatsApp tidak valid.");
}
$tanggalObj = DateTime::createFromFormat("!Y-m-d", $tanggal);
if (!$tanggalObj || $tanggalObj->format("Y-m-d") !== $tanggal || $tanggal < date("Y-m-d")) {
    kirimRespons(false, "Tanggal angkut tidak valid.");
}
if (!is_numeric($jarakInput) || (float)$jarakInput < 0.1 || (float)$jarakInput > 10000) {
    kirimRespons(false, "Jarak rute harus antara 0,1 dan 10000 km.");
}
if (!is_numeric($biayaInput) || (float)$biayaInput < 0) {
    kirimRespons(false, "Estimasi biaya tidak valid.");
}

// Validasi file dilakukan sebelum stok dikunci atau pesanan dimasukkan.
if (!isset($_FILES["bukti_pembayaran"]) || $_FILES["bukti_pembayaran"]["error"] !== UPLOAD_ERR_OK) {
    kirimRespons(false, "Foto bukti pembayaran wajib diunggah.");
}
$file = $_FILES["bukti_pembayaran"];
if ($file["size"] < 1 || $file["size"] > 5 * 1024 * 1024) {
    kirimRespons(false, "Ukuran foto bukti pembayaran maksimal 5 MB.");
}
$infoGambar = @getimagesize($file["tmp_name"]);
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file["tmp_name"]);
$formatDiizinkan = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
if (!$infoGambar || !isset($formatDiizinkan[$mime])) {
    kirimRespons(false, "Bukti pembayaran harus berupa gambar JPG, PNG, atau WEBP yang valid.");
}

$jarak = (float)$jarakInput;
$biaya = (float)$biayaInput;
$daftarKendaraan = [
    "pickup" => ["nama" => "Pickup", "dasar" => 100000, "per_km" => 5000],
    "engkel" => ["nama" => "Truk Engkel", "dasar" => 175000, "per_km" => 7000],
    "double" => ["nama" => "Engkel Double", "dasar" => 250000, "per_km" => 9000],
    "fuso" => ["nama" => "Truk Fuso", "dasar" => 500000, "per_km" => 12000]
];
if (!isset($daftarKendaraan[$kendaraan])) {
    kirimRespons(false, "Pilihan kendaraan tidak valid.");
}
$dataKendaraan = $daftarKendaraan[$kendaraan];
$namaKendaraan = $dataKendaraan["nama"];
$biayaSeharusnya = $dataKendaraan["dasar"] + ($jarak * $dataKendaraan["per_km"]);
if (abs($biaya - $biayaSeharusnya) > 0.01) {
    kirimRespons(false, "Estimasi biaya berubah. Silakan periksa kembali pesanan.");
}

// Kunci stok per tanggal dan kendaraan agar batas 10 unit tetap terjaga.
$kunciStok = "angkutin_" . $tanggal . "_" . $kendaraan;
$lockStmt = mysqli_prepare($conn, "SELECT GET_LOCK(?, 5) AS terkunci");
if (!$lockStmt) kirimRespons(false, "Stok armada belum dapat diperiksa. Silakan coba lagi.", 500);
mysqli_stmt_bind_param($lockStmt, "s", $kunciStok);
mysqli_stmt_execute($lockStmt);
$lockRow = mysqli_fetch_assoc(mysqli_stmt_get_result($lockStmt));
mysqli_stmt_close($lockStmt);
if ((int)($lockRow["terkunci"] ?? 0) !== 1) {
    kirimRespons(false, "Sistem sedang memeriksa pesanan lain. Silakan coba lagi sebentar.", 409);
}

$cekStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS terpakai FROM pesanan WHERE tanggal_angkut = ? AND kendaraan = ? AND status <> 'dibatalkan'");
if (!$cekStmt) {
    $release = mysqli_prepare($conn, "SELECT RELEASE_LOCK(?)");
    mysqli_stmt_bind_param($release, "s", $kunciStok); mysqli_stmt_execute($release); mysqli_stmt_close($release);
    kirimRespons(false, "Stok armada belum dapat diperiksa.", 500);
}
mysqli_stmt_bind_param($cekStmt, "ss", $tanggal, $namaKendaraan);
mysqli_stmt_execute($cekStmt);
$cekRow = mysqli_fetch_assoc(mysqli_stmt_get_result($cekStmt));
mysqli_stmt_close($cekStmt);
if ((int)($cekRow["terpakai"] ?? 0) >= 10) {
    $release = mysqli_prepare($conn, "SELECT RELEASE_LOCK(?)");
    mysqli_stmt_bind_param($release, "s", $kunciStok); mysqli_stmt_execute($release); mysqli_stmt_close($release);
    mysqli_close($conn);
    kirimRespons(false, "Armada yang kamu butuhkan sedang tidak ada. Silakan pilih armada lain atau ganti tanggal pemesanan.", 409);
}

// Simpan file dengan nama acak; file akan dihapus lagi jika INSERT gagal.
$folder = dirname(__DIR__) . "/uploads/bukti_pembayaran";
if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
    $release = mysqli_prepare($conn, "SELECT RELEASE_LOCK(?)");
    mysqli_stmt_bind_param($release, "s", $kunciStok); mysqli_stmt_execute($release); mysqli_stmt_close($release);
    kirimRespons(false, "Folder penyimpanan bukti pembayaran belum dapat dibuat.", 500);
}
$namaFile = bin2hex(random_bytes(16)) . "." . $formatDiizinkan[$mime];
$tujuanFile = $folder . "/" . $namaFile;
if (!move_uploaded_file($file["tmp_name"], $tujuanFile)) {
    $release = mysqli_prepare($conn, "SELECT RELEASE_LOCK(?)");
    mysqli_stmt_bind_param($release, "s", $kunciStok); mysqli_stmt_execute($release); mysqli_stmt_close($release);
    kirimRespons(false, "Foto bukti pembayaran gagal disimpan.", 500);
}
$pathRelatif = "uploads/bukti_pembayaran/" . $namaFile;

$sql = "INSERT INTO pesanan (
    user_id, nama_pelanggan, nomor_whatsapp, alamat_penjemputan, alamat_tujuan,
    tanggal_angkut, jarak_km, kendaraan, catatan, estimasi_biaya,
    bukti_pembayaran, status_pembayaran
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'diunggah')";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    @unlink($tujuanFile);
    $release = mysqli_prepare($conn, "SELECT RELEASE_LOCK(?)");
    mysqli_stmt_bind_param($release, "s", $kunciStok); mysqli_stmt_execute($release); mysqli_stmt_close($release);
    error_log("Gagal menyiapkan query pesanan: " . mysqli_error($conn));
    kirimRespons(false, "Pesanan belum dapat disimpan. Silakan coba lagi.", 500);
}
mysqli_stmt_bind_param($stmt, "isssssdssds", $user_id, $nama, $nomor, $penjemputan, $tujuan, $tanggal, $jarak, $namaKendaraan, $catatan, $biaya, $pathRelatif);
// Tipe parameter: i + 5 string + double + string + string + double + string.
if (mysqli_stmt_execute($stmt)) {
    $pesananId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    $release = mysqli_prepare($conn, "SELECT RELEASE_LOCK(?)");
    mysqli_stmt_bind_param($release, "s", $kunciStok); mysqli_stmt_execute($release); mysqli_stmt_close($release);
    mysqli_close($conn);
    kirimRespons(true, "Pesanan dan bukti pembayaran berhasil disimpan.", 200);
}

error_log("Gagal menyimpan pesanan: " . mysqli_stmt_error($stmt));
mysqli_stmt_close($stmt);
@unlink($tujuanFile);
$release = mysqli_prepare($conn, "SELECT RELEASE_LOCK(?)");
mysqli_stmt_bind_param($release, "s", $kunciStok); mysqli_stmt_execute($release); mysqli_stmt_close($release);
mysqli_close($conn);
kirimRespons(false, "Pesanan gagal disimpan. Silakan coba lagi.", 500);
?>