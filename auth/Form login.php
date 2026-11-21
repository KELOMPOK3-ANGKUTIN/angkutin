<?php
session_start();
require_once "../koneksi.php";

$pesan = "";
$warna = "red";

if (isset($_GET["daftar"]) && $_GET["daftar"] === "berhasil") {
    $pesan = "Pendaftaran berhasil! Silakan login.";
    $warna = "green";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["Username"] ?? "");
    $password = $_POST["Password"] ?? "";

    if ($username === "" || $password === "") {
        $pesan = "Username dan password wajib diisi.";
    } else {
        $query = mysqli_prepare(
            $conn,
            "SELECT id, username, email, password, role
             FROM users
             WHERE username = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($query, "s", $username);
        mysqli_stmt_execute($query);
        mysqli_stmt_store_result($query);

        if (mysqli_stmt_num_rows($query) === 1) {
            mysqli_stmt_bind_result(
                $query,
                $id,
                $usernameDB,
                $emailDB,
                $passwordDB,
                $roleDB
            );

            mysqli_stmt_fetch($query);

            if (password_verify($password, $passwordDB)) {
                session_regenerate_id(true);

                $_SESSION["user_id"] = $id;
                $_SESSION["username"] = $usernameDB;
                $_SESSION["email"] = $emailDB;
                $_SESSION["role"] = $roleDB;
                $_SESSION["login_berhasil"] = true;

                mysqli_stmt_close($query);

                // Kurir langsung masuk dashboard; pelanggan/admin tetap ke halaman utama.
                $tujuanLogin = ($roleDB === "kurir")
                    ? "../kurir/index.php"
                    : "../Halaman_utama.php";

                header("Location: " . $tujuanLogin);
                exit;
            }
        }

        mysqli_stmt_close($query);

        if ($pesan === "") {
            $pesan = "Username atau password salah!";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Jasa Pindah Rumah</title>

    <link rel="stylesheet" href="../assets/css/Form%20login.css">
</head>
<body>

<form id="loginForm" method="POST" action="Form%20login.php">
    <h2>Login</h2>
    <p class="subtitle">Masuk ke akun Jasa Pindah Rumah</p>

    <label for="Username">Username</label>
    <input
        type="text"
        id="Username"
        name="Username"
        placeholder="Masukkan username"
        autocomplete="username"
        required
    >

    <label for="Password">Password</label>
    <input
        type="password"
        id="Password"
        name="Password"
        placeholder="Masukkan password"
        autocomplete="current-password"
        required
    >

    <button type="submit">Login</button>

    <p id="result" style="color: <?= $warna ?>;">
        <?= htmlspecialchars($pesan, ENT_QUOTES, "UTF-8") ?>
    </p>

    <div class="link">
        Belum punya akun?
        <a href="daftar.php">Daftar sekarang</a>
    </div>
</form>
<script src="../assets/js/Form%20login.js"></script>

</body>
</html>
