<?php
session_start();
include "../dbKonek.php"; // Pastikan path ke file koneksi database Anda benar

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'dosen') {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$message = '';
$message_type = '';

// Ambil nama mata kuliah dan cek akses dosen
$check_course_stmt = mysqli_prepare($konek, "SELECT nama_mk FROM tb_course WHERE id = ? AND dosen = ?");
mysqli_stmt_bind_param($check_course_stmt, "is", $course_id, $username);
mysqli_stmt_execute($check_course_stmt);
$check_course_result = mysqli_stmt_get_result($check_course_stmt);
$course_data = mysqli_fetch_assoc($check_course_result);
mysqli_stmt_close($check_course_stmt);

if (!$course_data) {
    $_SESSION['message'] = "Mata kuliah tidak ditemukan atau Anda tidak memiliki akses.";
    $_SESSION['message_type'] = "danger";
    header("Location: kelola_course.php"); // Redirect ke halaman kelola course jika tidak valid
    exit;
}

$nama_mk = htmlspecialchars($course_data['nama_mk']); // Ambil nama mata kuliah

// Proses form input sesi absensi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pertemuan_ke = intval($_POST['pertemuan_ke'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');

    // Validasi input
    if ($pertemuan_ke <= 0) {
        $message = "❌ Pertemuan ke- harus angka lebih dari 0.";
        $message_type = "danger";
    } else {
        // Cek apakah sesi absensi untuk pertemuan dan tanggal yang sama sudah ada
        $check_duplicate_stmt = mysqli_prepare($konek, "SELECT id FROM tb_absensi WHERE course_id = ? AND pertemuan_ke = ? AND tanggal = ?");
        mysqli_stmt_bind_param($check_duplicate_stmt, "iis", $course_id, $pertemuan_ke, $tanggal);
        mysqli_stmt_execute($check_duplicate_stmt);
        mysqli_stmt_store_result($check_duplicate_stmt);

        if (mysqli_stmt_num_rows($check_duplicate_stmt) > 0) {
            $message = "⚠️ Sesi absensi pertemuan ke-$pertemuan_ke pada tanggal ini sudah ada.";
            $message_type = "warning";
        } else {
            $insert_stmt = mysqli_prepare($konek, "INSERT INTO tb_absensi (course_id, pertemuan_ke, tanggal, created_at) VALUES (?, ?, ?, NOW())");
            mysqli_stmt_bind_param($insert_stmt, "iis", $course_id, $pertemuan_ke, $tanggal);
            if (mysqli_stmt_execute($insert_stmt)) {
                $message = "✅ Sesi absensi pertemuan ke-$pertemuan_ke berhasil dibuat.";
                $message_type = "success";
            } else {
                $message = "❌ Gagal menyimpan: " . mysqli_error($konek);
                $message_type = "danger";
            }
            mysqli_stmt_close($insert_stmt);
        }
        mysqli_stmt_close($check_duplicate_stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Absensi & Rekap - <?= $nama_mk ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4338ca;
            /* Bootstrap primary blue */
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --white: #ffffff;
            --border-radius-lg: 0.75rem;
            --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --box-shadow-lg: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-gray);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding-top: 75px;
            /* Adjust for fixed top navbar height */
        }

        .top-navbar {
            background-color: var(--primary-color);
            color: #ffffff;
            /* Pastikan teks di navbar berwarna putih */
            box-shadow: var(--box-shadow-lg);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1030;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 65px;
        }

        .top-navbar .nav-link {
            color: #ffffff;
            /* Pastikan link juga putih */
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .top-navbar .nav-link:hover {
            color: #fff;
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .top-navbar .nav-link i {
            margin-right: 8px;
            font-size: 1.1em;
        }

        .top-navbar .navbar-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark-gray);
            /* Dark gray for title */
        }

        .content-wrapper {
            flex-grow: 1;
            padding: 20px;
        }

        .card {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
        }

        .card-header {
            background-color: var(--primary-color);
            /* Primary color for card header */
            color: var(--white);
            border-top-left-radius: var(--border-radius-lg);
            border-top-right-radius: var(--border-radius-lg);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            font-size: 1.15rem;
        }

        .card-header.bg-secondary-custom {
            background-color: var(--primary-color);
            /* Secondary color for rekap card header */
        }

        .card-header i {
            margin-right: 10px;
            font-size: 1.3em;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 0.5rem;
        }

        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            transition: all 0.2s ease-in-out;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            /* Adjusted shadow for primary blue */
        }

        .btn {
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
        }

        .btn-success {
            background-color: #19ad52ff;
            border-color: #19ad52ff;
        }

        .btn-success:hover {
            background-color: #19ad52ff;
            border-color: #19ad52ff;
        }

        .btn-outline-info {
            color: var(--info-color);
            border-color: var(--info-color);
            background-color: transparent;
        }

        .btn-outline-info:hover {
            background-color: var(--info-color);
            color: var(--white);
        }

        .table {
            border-radius: var(--border-radius-lg);
            overflow: hidden;
        }

        .table thead {
            background-color: #e9ecef;
            color: var(--dark-gray);
        }

        .table th,
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid #dee2e6;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .table-bordered th,
        .table-bordered td {
            border: 1px solid #dee2e6;
        }

        .badge {
            font-size: 0.85em;
            padding: 0.6em 0.9em;
            border-radius: 0.35rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge.bg-success {
            background-color: var(--success-color) !important;
        }

        .badge.bg-warning {
            background-color: var(--warning-color) !important;
            color: var(--dark-gray);
        }

        .badge.bg-info {
            background-color: var(--info-color) !important;
        }

        .badge.bg-danger {
            background-color: var(--danger-color) !important;
        }

        .badge.bg-secondary {
            background-color: var(--secondary-color) !important;
        }

        .alert {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 1rem 1.25rem;
        }

        .alert-dismissible .btn-close {
            font-size: 0.9rem;
            padding: 0.75rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body {
                padding-top: 65px;
            }

            .top-navbar {
                height: 60px;
                padding: 10px;
            }

            .top-navbar .navbar-title {
                font-size: 1rem;
            }

            .top-navbar .nav-link span {
                display: none;
            }

            .top-navbar .nav-link i {
                margin-right: 0;
            }

            .content-wrapper {
                padding: 15px;
            }

            .card-header {
                font-size: 1rem;
                padding: 1rem;
            }

            .card-header i {
                font-size: 1.1em;
            }

            .table-responsive {
                border: 1px solid #dee2e6;
                border-radius: var(--border-radius-lg);
            }

            .table thead {
                display: none;
            }

            .table tbody tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #dee2e6;
                border-radius: 0.75rem;
                box-shadow: var(--box-shadow);
            }

            .table tbody td {
                display: block;
                text-align: right !important;
                padding-left: 50%;
                position: relative;
                border: none;
            }

            .table tbody td::before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                width: calc(50% - 30px);
                text-align: left;
                font-weight: 600;
                color: var(--dark-gray);
            }

            .table td:first-child {
                border-top: none;
            }

            .table tbody tr:nth-of-type(odd) {
                background-color: var(--white);
            }
        }
    </style>
</head>

<body>

    <nav class="top-navbar">
        <div class="nav-links-group">
            <a class="nav-link" href="#" onclick="history.back(); return false;">
                <i class="bi bi-arrow-left-circle"></i>
                <span>Kembali</span>
            </a>
        </div>
        <div class="nav-links-group">
            <a class="nav-link" href="profil_dosen.php">
                <i class="bi bi-person-circle"></i>
                <span>Profil</span>
            </a>
        </div>
    </nav>

    <div class="content-wrapper container">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <i class="bi bi-calendar-plus"></i>Buat Sesi Absensi Baru
            </div>
            <div class="card-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                        <?= $message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form method="POST">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="pertemuan_ke" class="form-label">Pertemuan Ke-</label>
                            <input type="number" name="pertemuan_ke" id="pertemuan_ke" class="form-control" required placeholder="Contoh: 1">
                        </div>
                        <div class="col-md-6">
                            <label for="tanggal" class="form-label">Tanggal Sesi</label>
                            <input type="date" name="tanggal" id="tanggal" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mt-4 text-start">
                        <button type="submit" class="btn btn-success"><i class="bi bi-plus-circle me-2"></i>Buat Sesi</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-secondary-custom">
                <i class="bi bi-journal-check"></i>Rekap Detail Kehadiran Mahasiswa
            </div>
            <div class="card-body">
                <?php
                $rekap_detail_stmt = mysqli_prepare($konek, "
                    SELECT
                        a.id AS absensi_id,
                        a.pertemuan_ke,
                        a.tanggal,
                        d.mahasiswa_username,
                        d.status,
                        d.keterangan
                    FROM tb_absensi a
                    LEFT JOIN tb_detail_absensi d ON a.id = d.absensi_id
                    WHERE a.course_id = ?
                    ORDER BY a.pertemuan_ke ASC, a.tanggal DESC, d.mahasiswa_username ASC
                ");
                mysqli_stmt_bind_param($rekap_detail_stmt, "i", $course_id);
                mysqli_stmt_execute($rekap_detail_stmt);
                $rekap_detail_result = mysqli_stmt_get_result($rekap_detail_stmt);

                if (mysqli_num_rows($rekap_detail_result) === 0): ?>
                    <p class="text-muted text-center py-4">Belum ada sesi absensi yang dibuat atau mahasiswa yang mengisi absensi untuk mata kuliah ini.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle">
                            <thead class="text-center">
                                <tr>
                                    <th>Pertemuan</th>
                                    <th>Tanggal Sesi</th>
                                    <th>Mahasiswa</th>
                                    <th>Status</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($rekap_detail_result)): ?>
                                    <tr>
                                        <td data-label="Pertemuan:" class="text-center"><?= $row['pertemuan_ke'] ?></td>
                                        <td data-label="Tanggal Sesi:"><?= date("d M Y", strtotime($row['tanggal'])) ?></td>
                                        <td data-label="Mahasiswa:">
                                            <?php
                                            if (empty($row['mahasiswa_username'])) {
                                                echo '<em class="text-muted">Belum ada data absensi</em>';
                                            } else {
                                                echo htmlspecialchars($row['mahasiswa_username']);
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Status:" class="text-center">
                                            <?php
                                            $status = ucfirst($row['status']);
                                            $badge = match ($status) {
                                                'Hadir' => 'success',
                                                'Izin' => 'warning',
                                                'Sakit' => 'info',
                                                'Alpa' => 'danger',
                                                default => 'secondary'
                                            };
                                            if (!empty($row['status'])) {
                                                echo '<span class="badge bg-' . $badge . '">' . $status . '</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">Belum Absen</span>';
                                            }
                                            ?>
                                        </td>
                                        <td data-label="Keterangan:"><?= !empty($row['keterangan']) ? htmlspecialchars($row['keterangan']) : '-' ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php
                endif;
                mysqli_stmt_close($rekap_detail_stmt);
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>