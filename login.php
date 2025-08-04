<?php
session_start();
include "dbKonek.php";

$error_message = '';

if (isset($_POST['login'])) {
    $_user = $_POST['username'];
    $_password = $_POST['password'];

    $_query = mysqli_query($konek, "SELECT * FROM tb_user WHERE username='$_user' AND password='$_password'");
    $cekData = mysqli_num_rows($_query);

    if ($cekData > 0) {
        $_data = mysqli_fetch_array($_query);
        $_SESSION['username'] = $_data['username'];
        $_SESSION['role'] = $_data['role'];

        switch ($_SESSION['role']) {
            case 'admin':
                header("Location: admin/dashboardAdmin.php");
                break;
            case 'dosen':
                header("Location: dosen/dasboardDosen.php");
                break;
            case 'mahasiswa':
                header("Location: mahasiswa/dasboardMahasiswa.php");
                break;
            default:
                $error_message = "Role pengguna tidak dikenali.";
                session_destroy();
        }
        exit;
    } else {
        $error_message = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login LMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            /* Latar belakang biru tua solid */
            background-color: #163cacff;
            /* Contoh warna biru tua */
            margin: 0;
            height: 100vh;
            /* Memastikan body mengisi seluruh tinggi viewport */
            display: flex;
            /* Untuk memposisikan form di tengah */
            justify-content: center;
            /* Horizontally center */
            align-items: center;
            /* Vertically center */
        }

        .login-form {
            background: rgba(255, 255, 255, 0.85);
            /* Sedikit transparan agar kontras */
            padding: 30px;
            border-radius: 10px;
            width: 320px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }

        .alert-container {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 9999;
        }
    </style>
</head>

<body>

    <?php if (!empty($error_message)): ?>
        <div class="alert-container">
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="login-form text-center">
        <div class="login-box">
            <h4 class="mb-4 fw-bold">Log in to LMS</h4>
            <p>Silakan masukkan username dan password Anda!</p>

            <form action="login.php" method="POST">
                <div class="mb-3">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <div class="d-grid">
                    <button name="login" type="submit" class="btn btn-primary">Log in</button>
                </div>
            </form>
        </div>
    </div>

</body>

</html>