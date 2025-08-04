<?php
session_start();
include "../dbKonek.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];
$admin = mysqli_fetch_assoc(mysqli_query($konek, "SELECT * FROM tb_user WHERE username='$username' AND role='admin'"));

// Simulasi log aktivitas
$aktivitas = [
    ["login", "Berhasil login ke sistem", "2025-07-08 08:00"],
    ["tambah user", "Menambahkan dosen baru", "2025-07-08 08:12"],
    ["hapus mata kuliah", "Menghapus mata kuliah Pemrograman Web", "2025-07-08 08:30"],
    ["edit user", "Mengubah data mahasiswa", "2025-07-08 08:42"],
];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Profil Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to right, #e0eafc, #cfdef3);
            font-family: 'Segoe UI', sans-serif;
        }

        .profile-container {
            max-width: 850px;
            margin: 80px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #0d6efd;
        }

        .info-label {
            font-weight: 500;
            color: #555;
        }

        .navbar-brand {
            font-weight: bold;
        }

        .activity-badge {
            text-transform: uppercase;
            font-size: 12px;
        }
    </style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">LMS Admin</a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3 fw-bold">👤 <?= htmlspecialchars($username); ?></span>
                <a href="../logout.php" class="btn btn-sm btn-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container profile-container mt-5">
        <div class="text-center">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin['username']); ?>&background=0d6efd&color=fff&size=256"
                alt="Avatar" class="profile-avatar mb-3">
            <h4><?= htmlspecialchars($admin['username']); ?></h4>
            <p class="text-muted">Administrator Sistem</p>
        </div>

        <hr>

        <div class="row profile-info mt-3">
            <div class="col-md-6 mb-3">
                <div class="info-label">Username</div>
                <div class="text-dark"><?= htmlspecialchars($admin['username']); ?></div>
            </div>
            <div class="col-md-6">
                <div class="info-label">Role</div>
                <div class="text-dark"><?= ucfirst($admin['role']); ?></div>
            </div>
            <div class="col-md-12">
                <div class="info-label">Tentang Anda</div>
                <div class="text-muted">Sebagai Admin, Anda dapat mengelola user, mata kuliah, dan pengaturan sistem LMS.</div>
            </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3">📋 Log Aktivitas Terakhir</h5>
        <ul class="list-group">
            <?php foreach ($aktivitas as $log): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start">
                    <div class="ms-2 me-auto">
                        <div class="fw-bold"><?= $log[1]; ?></div>
                        <small class="text-muted"><?= $log[2]; ?></small>
                    </div>
                    <span class="badge bg-info rounded-pill activity-badge"><?= $log[0]; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="mt-4 text-star">
            <a href="dashboardAdmin.php" class="btn btn-outline-secondary">Kembali</a>
        </div>
    </div>

</body>

</html>