<?php
session_start();
require_once __DIR__ . "/../koneksi.php";

function kembaliDenganStatus($status) {
    header("Location: ../Halaman_utama.php?ulasan=" . urlencode($status) . "#armada");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../Halaman_utama.php#armada");
    exit;
}

if (empty($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "pelanggan") {
    kembaliDenganStatus("akses");
}

$tokenSesi = $_SESSION["csrf_ulasan"] ?? "";
$tokenForm = $_POST["csrf_token"] ?? "";
if ($tokenSesi === "" || !is_string($tokenForm) || !hash_equals($tokenSesi, $tokenForm)) {
    kembaliDenganStatus("gagal");
}

$rating = filter_input(INPUT_POST, "rating", FILTER_VALIDATE_INT);
$pesan = trim($_POST["pesan"] ?? "");

if ($rating === false || $rating === null || $rating < 1 || $rating > 5) {
    kembaliDenganStatus("rating");
}

if (mb_strlen($pesan) < 5 || mb_strlen($pesan) > 1000) {
    kembaliDenganStatus("pesan");
}

$userId = (int) $_SESSION["user_id"];
$stmt = mysqli_prepare($conn, "INSERT INTO ulasan (user_id, rating, pesan) VALUES (?, ?, ?)");
if (!$stmt) {
    kembaliDenganStatus("gagal");
}

mysqli_stmt_bind_param($stmt, "iis", $userId, $rating, $pesan);
$berhasil = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

kembaliDenganStatus($berhasil ? "berhasil" : "gagal");
