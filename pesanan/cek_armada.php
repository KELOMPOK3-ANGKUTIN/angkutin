<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . "/../koneksi.php";

function respons($berhasil, $pesan, $data = [], $kode = 200) {
    http_response_code($kode);
    echo json_encode(array_merge(["berhasil" => $berhasil, "pesan" => $pesan], $data));
    exit;
}

if (!isset($_SESSION["user_id"])) {
    respons(false, "Silakan login kembali.", [], 401);
}
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    respons(false, "Metode permintaan tidak diizinkan.", [], 405);
}
$tanggal = trim($_GET["tanggal"] ?? "");
$kendaraan = trim($_GET["kendaraan"] ?? "");
$daftar = [
    "pickup" => "Pickup",
    "engkel" => "Truk Engkel",
    "double" => "Engkel Double",
    "fuso" => "Truk Fuso"
];
$tanggalObj = DateTime::createFromFormat("!Y-m-d", $tanggal);
if (!$tanggalObj || $tanggalObj->format("Y-m-d") !== $tanggal || $tanggal < date("Y-m-d")) {
    respons(false, "Pilih tanggal angkut yang valid.", [], 400);
}
if (!isset($daftar[$kendaraan])) {
    respons(false, "Pilih jenis armada terlebih dahulu.", [], 400);
}
$nama = $daftar[$kendaraan];
$sql = "SELECT COUNT(*) AS terpakai FROM pesanan WHERE tanggal_angkut = ? AND kendaraan = ? AND status <> 'dibatalkan'";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) respons(false, "Stok armada belum dapat diperiksa.", [], 500);
mysqli_stmt_bind_param($stmt, "ss", $tanggal, $nama);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
$terpakai = (int)($row["terpakai"] ?? 0);
$tersedia = max(0, 10 - $terpakai);
mysqli_stmt_close($stmt);
mysqli_close($conn);
respons(true, "Stok berhasil diperiksa.", ["tanggal" => $tanggal, "kendaraan" => $kendaraan, "nama_kendaraan" => $nama, "stok_total" => 10, "terpakai" => $terpakai, "tersedia" => $tersedia]);
?>