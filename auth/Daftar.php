<?php
require_once "../koneksi.php";

$pesan = "";
$warna = "red";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["Username"] ?? "");
    $email = trim($_POST["Email"] ?? "");
    $password = $_POST["Password"] ?? "";
    $konfirmasi = $_POST["PasswordConfirm"] ?? "";

    try {
        if ($username === "" || $email === "" ||
            $password === "" || $konfirmasi === "") {
            throw new Exception("Semua data wajib diisi.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email tidak valid.");
        }

        if (strlen($password) < 6) {
            throw new Exception("Password minimal 6 karakter.");
        }

        if ($password !== $konfirmasi) {
            throw new Exception("Konfirmasi password tidak sama.");
        }

        // Periksa apakah username atau email sudah digunakan
        $cek = mysqli_prepare(
            $conn,
            "SELECT id FROM users WHERE username = ? OR email = ?"
        );

        mysqli_stmt_bind_param($cek, "ss", $username, $email);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            mysqli_stmt_close($cek);
            throw new Exception("Username atau email sudah digunakan.");
        }

        mysqli_stmt_close($cek);

        // Amankan password sebelum disimpan
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $role = "pelanggan";

        $simpan = mysqli_prepare(
            $conn,
            "INSERT INTO users (username, email, password, role)
             VALUES (?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $simpan,
            "ssss",
            $username,
            $email,
            $passwordHash,
            $role
        );

        mysqli_stmt_execute($simpan);
        mysqli_stmt_close($simpan);

        // Pendaftaran berhasil
        header("Location: Form%20login.php?daftar=berhasil");
        exit;

    } catch (Exception $e) {
        $pesan = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $pesan = "Gagal menyimpan akun ke database. Periksa koneksi dan struktur tabel users.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - Jasa Pindah Rumah</title>

    <link rel="stylesheet" href="../assets/css/Form%20login.css">
</head>
<body>

<form id="registerForm" method="POST" action="daftar.php">
    <h2>Daftar Akun</h2>
    <p class="subtitle">Buat akun untuk menggunakan layanan kami</p>

    <label for="Username">Username</label>
    <input type="text" id="Username" name="Username"
           placeholder="Masukkan username" maxlength="50" required>

    <label for="Email">Email</label>
    <input type="email" id="Email" name="Email"
           placeholder="Masukkan email" maxlength="100" required>

    <label for="Password">Password</label>
    <input type="password" id="Password" name="Password"
           placeholder="Minimal 6 karakter" minlength="6" required>

    <label for="PasswordConfirm">Konfirmasi Password</label>
    <input type="password" id="PasswordConfirm"
           name="PasswordConfirm"
           placeholder="Ulangi password" minlength="6" required>

    <button type="submit">Daftar</button>

    <p id="result" style="color: red;">
        <?= htmlspecialchars($pesan, ENT_QUOTES, "UTF-8") ?>
    </p>

    <div class="link">
        Sudah punya akun?
        <a href="Form%20login.php">Login</a>
    </div>
</form>

</body>
</html>
