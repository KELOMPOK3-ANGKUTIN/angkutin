<?php
session_start();

// Hapus semua data sesi login
$_SESSION = [];

// Hapus sesi dari server
session_destroy();

// Arahkan pengguna kembali ke halaman login
header("Location: Form%20login.php");
exit;
?>
```