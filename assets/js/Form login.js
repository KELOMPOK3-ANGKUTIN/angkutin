
// Menampilkan pesan pada form login atau pendaftaran
function showResult(message, success = false) {
    const result = document.getElementById("result");

    if (result) {
        result.textContent = message;
        result.style.color = success ? "green" : "red";
    }
}

// ================================
// FORM LOGIN
// ================================

const loginForm = document.getElementById("loginForm");

if (loginForm) {
    loginForm.addEventListener("submit", function (event) {
        const username = document.getElementById("Username");
        const password = document.getElementById("Password");

        // Pastikan elemen input tersedia
        if (!username || !password) {
            return;
        }

        // Validasi tambahan sebelum form dikirim ke PHP
        if (
            username.value.trim() === "" ||
            password.value === ""
        ) {
            event.preventDefault();
            showResult("Username dan password wajib diisi.");
            return;
        }

        // Biarkan form dikirim secara normal ke Form login.php.
        // PHP akan memeriksa akun dan mengatur sesi login.
    });
}

// ================================
// FORM PENDAFTARAN
// ================================

const registerForm = document.getElementById("registerForm");

if (registerForm) {
    registerForm.addEventListener("submit", function (event) {
        const username = document.getElementById("Username");
        const email = document.getElementById("Email");
        const password = document.getElementById("Password");
        const confirmPassword =
            document.getElementById("PasswordConfirm");

        // Jika elemen tidak sesuai, biarkan PHP menangani form.
        if (
            !username ||
            !email ||
            !password ||
            !confirmPassword
        ) {
            return;
        }

        // Periksa kelengkapan data
        if (
            username.value.trim() === "" ||
            email.value.trim() === "" ||
            password.value === "" ||
            confirmPassword.value === ""
        ) {
            event.preventDefault();
            showResult("Semua data wajib diisi.");
            return;
        }

        // Periksa kesamaan password
        if (password.value !== confirmPassword.value) {
            event.preventDefault();
            showResult("Konfirmasi password tidak sama.");
        }

        // Jika data valid, biarkan PHP memproses pendaftaran.
    });
}
