<?php
session_start();
// Aktifkan pelaporan error untuk debugging. Nonaktifkan di lingkungan produksi.
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

include "../dbKonek.php"; // Pastikan path ke dbKonek.php sudah benar

// --- Bagian 1: Verifikasi Sesi dan Role Pengguna ---
// Pastikan pengguna sudah login dan memiliki role 'dosen'
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dosen') {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "danger";
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];
$course_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Redirect jika ID mata kuliah tidak valid atau tidak ada
if ($course_id === false || $course_id === null) {
    $_SESSION['message'] = "ID mata kuliah tidak valid atau tidak ditemukan.";
    $_SESSION['message_type'] = "danger";
    header("Location: dashboardDosen.php"); // Arahkan kembali ke dashboard dosen
    exit;
}

$course = null;
$materi = null;
$tugas = null;
$absensi_list = null;

// Inisialisasi prepared statements untuk dipastikan ditutup di finally block
$course_stmt = null;
$materi_stmt = null;
$tugas_stmt = null;
$absensi_stmt = null;

try {
    // --- Bagian 2: Ambil Data Mata Kuliah ---
    // Pastikan dosen yang login adalah pemilik mata kuliah ini
    $course_stmt = mysqli_prepare($konek, "SELECT * FROM tb_course WHERE id = ? AND dosen = ?");
    if (!$course_stmt) {
        throw new Exception("Gagal menyiapkan statement course: " . mysqli_error($konek));
    }
    mysqli_stmt_bind_param($course_stmt, "is", $course_id, $username);
    mysqli_stmt_execute($course_stmt);
    $course_result = mysqli_stmt_get_result($course_stmt);
    $course = mysqli_fetch_assoc($course_result);

    // Jika mata kuliah tidak ditemukan atau dosen tidak memiliki akses
    if (!$course) {
        $_SESSION['message'] = "Mata kuliah tidak ditemukan atau Anda tidak memiliki izin akses.";
        $_SESSION['message_type'] = "danger";
        header("Location: dashboardDosen.php");
        exit;
    }

    // --- Bagian 3: Ambil Data Materi Perkuliahan ---
    $materi_stmt = mysqli_prepare($konek, "SELECT * FROM tb_materi WHERE course_id = ? ORDER BY created_at DESC");
    if (!$materi_stmt) {
        throw new Exception("Gagal menyiapkan statement materi: " . mysqli_error($konek));
    }
    mysqli_stmt_bind_param($materi_stmt, "i", $course_id);
    mysqli_stmt_execute($materi_stmt);
    $materi = mysqli_stmt_get_result($materi_stmt);

    // --- Bagian 4: Ambil Data Tugas Mahasiswa ---
    $tugas_stmt = mysqli_prepare($konek, "SELECT * FROM tb_tugas WHERE course_id = ? ORDER BY deadline ASC");
    if (!$tugas_stmt) {
        throw new Exception("Gagal menyiapkan statement tugas: " . mysqli_error($konek));
    }
    mysqli_stmt_bind_param($tugas_stmt, "i", $course_id);
    mysqli_stmt_execute($tugas_stmt);
    $tugas = mysqli_stmt_get_result($tugas_stmt);

    // --- Bagian 5: Ambil Data Absensi ---
    $absensi_stmt = mysqli_prepare($konek, "SELECT * FROM tb_absensi WHERE course_id = ? ORDER BY tanggal DESC");
    if (!$absensi_stmt) {
        throw new Exception("Gagal menyiapkan statement absensi: " . mysqli_error($konek));
    }
    mysqli_stmt_bind_param($absensi_stmt, "i", $course_id);
    mysqli_stmt_execute($absensi_stmt);
    $absensi_list = mysqli_stmt_get_result($absensi_stmt);
} catch (Exception $e) {
    // Tangani error database jika terjadi
    error_log("Database Error in kelola_course.php: " . $e->getMessage()); // Catat error ke log server
    $_SESSION['message'] = "Terjadi kesalahan database saat memuat data. Silakan coba lagi nanti.";
    $_SESSION['message_type'] = "danger";
    header("Location: dashboardDosen.php");
    exit;
} finally {
    // Pastikan semua prepared statements ditutup untuk membebaskan sumber daya
    if ($course_stmt) {
        mysqli_stmt_close($course_stmt);
    }
    if ($materi_stmt) {
        mysqli_stmt_close($materi_stmt);
    }
    if ($tugas_stmt) {
        mysqli_stmt_close($tugas_stmt);
    }
    if ($absensi_stmt) {
        mysqli_stmt_close($absensi_stmt);
    }
    // Menutup koneksi database
    // mysqli_close($konek); // Koneksi akan ditutup di file footer atau setelah semua operasi selesai
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola <?= htmlspecialchars($course['nama_mk'] ?? 'Mata Kuliah'); ?> - Dosen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Variabel CSS untuk konsistensi */
        :root {
            --primary-color: #4f46e5;
            /* Indigo */
            --primary-dark: #4338ca;
            /* Darker Indigo */
            --secondary-color: #6c757d;
            /* Gray */
            --background-light: #f4f7f6;
            /* Latar belakang lebih terang */
            --card-background: #ffffff;
            /* Putih untuk kartu */
            --border-color: #e0e0e0;
            /* Warna border default */
            --shadow-light: 0 2px 4px rgba(0, 0, 0, 0.05);
            /* Bayangan ringan */
            --shadow-medium: 0 8px 16px rgba(0, 0, 0, 0.1);
            /* Bayangan sedang */
            --text-dark: #212529;
            /* Warna teks utama */
            --text-muted: #6c757d;
            /* Warna teks sekunder/muted */
            --success-color: #28a745;
            /* Warna sukses */
            --warning-color: #ffc107;
            /* Warna peringatan */
            --danger-color: #dc3545;
            /* Warna bahaya */
            --sidebar-width: 250px;
            --navbar-height: 75px;
            /* Menambahkan variabel untuk tinggi navbar */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-light);
            margin: 0;
            color: var(--text-dark);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }

        .wrapper {
            display: flex;
            transition: all 0.3s ease-in-out;
            min-height: 100vh;
            width: 100%;
            /* Pastikan wrapper mengambil lebar penuh */
        }

        /* --- Sidebar Styling --- */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            color: white;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            transition: all 0.3s ease-in-out;
            display: flex;
            flex-direction: column;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar .logo {
            font-size: 1.6rem;
            font-weight: 700;
            padding: 25px 20px;
            text-align: center;
            background-color: var(--primary-dark);
            /* Mengubah ketebalan border-bottom di sini */
            border-bottom: 2px solid rgba(255, 255, 255, 0.25);
            /* Garis sedikit lebih tebal dan lebih terlihat */
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .sidebar .logo i {
            font-size: 1.8rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .sidebar .nav-link-list {
            flex-grow: 1;
            padding-top: 15px;
        }

        .sidebar .nav-link-list a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px 25px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
            border-left: 5px solid transparent;
        }

        .sidebar .nav-link-list a i {
            font-size: 1.4rem;
            width: 28px;
            text-align: center;
        }

        .sidebar .nav-link-list a:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: white;
            border-left-color: white;
        }

        .sidebar .nav-link-list a.active {
            background-color: var(--primary-dark);
            color: white;
            border-left-color: white;
            font-weight: 600;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.2);
        }

        .sidebar .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
        }

        .sidebar .sidebar-footer .btn-logout {
            background-color: transparent;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }

        .sidebar .sidebar-footer .btn-logout:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-color: white;
        }

        .content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            transition: all 0.3s ease-in-out;
            /* Sesuaikan padding-top dengan tinggi navbar */
            padding-top: calc(var(--navbar-height) + 20px);
            /* Tambahan 20px untuk jarak ekstra */
            padding-bottom: 30px;
            padding-right: 30px;
            padding-left: 30px;
        }

        .collapsed .sidebar {
            margin-left: calc(-1 * var(--sidebar-width));
        }

        .collapsed .content {
            margin-left: 0;
            width: 100%;
        }

        /* --- Navbar Styling --- */
        .navbar {
            background-color: var(--card-background);
            padding: 10px 30px;
            box-shadow: var(--shadow-light);
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            /* Navbar dimulai setelah sidebar */
            width: calc(100% - var(--sidebar-width));
            z-index: 1030;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease-in-out;
            height: var(--navbar-height);
            /* Set tinggi navbar agar konsisten */
        }

        .collapsed .navbar {
            left: 0;
            /* Pastikan navbar menempel sempurna di sisi kiri saat sidebar collapse */
            width: 100%;
        }

        .navbar .btn-outline-secondary {
            border: none;
            background-color: #e6eaf0;
            color: var(--primary-color);
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 1.15rem;
            transition: background-color 0.2s ease;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .navbar .btn-outline-secondary:hover {
            background-color: #d8dde3;
        }

        .navbar .d-flex.align-items-center {
            /* Targetkan div yang membungkus tombol dan h5 */
            gap: 20px;
            /* Jarak antara tombol hamburger dan h5 */
        }

        .navbar h5 {
            color: var(--text-dark);
            margin: 0;
            font-weight: 600;
            font-size: 1.4rem;
        }

        .navbar .icon-group i {
            color: var(--secondary-color);
            cursor: pointer;
            transition: color 0.2s ease, transform 0.2s ease;
            padding: 8px;
            border-radius: 50%;
        }

        .navbar .icon-group i:hover {
            background-color: #f0f2f5;
        }

        .card {
            border-radius: 15px;
            border: 1px solid var(--border-color);
            background: var(--card-background);
            box-shadow: var(--shadow-light);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 18px 25px;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .card-body {
            padding: 25px;
        }

        .section-header {
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn-action-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
            justify-content: flex-start;
            /* Changed from center to start */
        }

        .btn-action-group .btn {
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action-group .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-action-group .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-action-group .btn-outline-success {
            color: var(--success-color);
            border-color: var(--success-color);
        }

        .btn-action-group .btn-outline-success:hover {
            background-color: var(--success-color);
            color: white;
        }

        .btn-action-group .btn-outline-warning {
            color: var(--warning-color);
            border-color: var(--warning-color);
        }

        .btn-action-group .btn-outline-warning:hover {
            background-color: var(--warning-color);
            color: white;
        }

        .btn-action-group .btn-outline-danger {
            color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-action-group .btn-outline-danger:hover {
            background-color: var(--danger-color);
            color: white;
        }

        .list-group-item {
            border-radius: 12px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            background-color: var(--card-background);
            padding: 18px 20px;
            transition: all 0.2s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .list-group-item:last-child {
            margin-bottom: 0;
        }

        .list-group-item:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            transform: translateY(-2px);
        }

        .list-group-item strong {
            font-size: 1.05rem;
            color: var(--text-dark);
        }

        .list-group-item small {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 5px;
            display: block;
        }

        .list-group-item .item-details {
            flex-grow: 1;
        }

        .list-group-item .btn-group {
            margin-left: 15px;
            flex-shrink: 0;
            display: flex;
            gap: 8px;
        }

        .list-group-item .btn {
            border-radius: 8px;
            font-size: 0.85rem;
            padding: 8px 14px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
            background-color: #f8f8f8;
            border-radius: 15px;
            border: 2px dashed var(--border-color);
            margin-top: 20px;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ced4da;
            margin-bottom: 15px;
        }

        .empty-state h6 {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 1.2rem;
        }

        .footer {
            background-color: var(--primary-dark);
            color: white;
            text-align: center;
            padding: 20px 0;
            margin-top: auto;
            /* Push footer to the bottom */
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Styles for Bootstrap Toasts */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1080;
        }

        .toast {
            min-width: 250px;
        }

        .toast-header .btn-close {
            margin-left: .5rem;
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {

            /* Adjust breakpoint for sidebar collapse */
            .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }

            .sidebar.collapsed {
                margin-left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .main-content.shifted {
                margin-left: var(--sidebar-width);
            }

            .navbar-toggler {
                display: block;
                /* Show toggler on smaller screens */
            }

            .navbar-nav {
                display: none;
                /* Hide regular nav links when sidebar is primary nav */
            }
        }

        @media (min-width: 992px) {
            .navbar-toggler {
                display: none;
                /* Hide toggler on larger screens */
            }
        }

        @media (max-width: 767.98px) {
            .navbar {
                padding: 15px 20px;
            }

            .content-area {
                padding-left: 15px;
                padding-right: 15px;
            }

            .section-header {
                font-size: 1.5rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .btn-action-group {
                flex-direction: column;
                gap: 10px;
            }

            .btn-action-group .btn {
                width: 100%;
            }

            .list-group-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .list-group-item .item-details {
                width: 100%;
            }

            .list-group-item .btn-group {
                margin-left: 0;
                width: 100%;
                display: flex;
                gap: 8px;
            }

            .list-group-item .btn-group .btn {
                flex-grow: 1;
            }

            .toast-container {
                top: 70px;
                right: 15px;
                left: 15px;
                max-width: none;
                width: auto;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper" id="wrapper">
        <div class="sidebar">
            <div class="logo">
                <i class="bi bi-book"></i> LMS
            </div>
            <div class="nav-link-list">
                <a href="dasboardDosen.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'dasboardDosen.php' && !isset($_GET['course_id'])) ? 'active' : ''; ?>">
                    <i class="bi bi-house-door-fill"></i> Dashboard
                </a>
                <a href="apload_tugas.php">
                    <i class="bi bi-pencil-square"></i> Kelola Tugas
                </a>
                <a href="lihat_absensi.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'absensi.php') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-check"></i> Absensi
                </a>
                <a href="../logout.php" class="btn btn-logout w-75" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </div>
            <div class="sidebar-footer">
            </div>
        </div>

        <div class="content">
            <nav class="navbar">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
                    <h5 class="m-0">Materi</h5>
                </div>
                <div class="d-flex align-items-center gap-3 icon-group">
                    <span class="me-2 fs-3"><i class="bi bi-person-circle"></i></span>
                    <i class="bi bi-bell fs-4" data-bs-toggle="offcanvas" data-bs-target="#rightPanel"></i>
                    <i class="bi bi-gear fs-4"></i>
                </div>
            </nav>

            <div class="toast-container">
                <?php if (isset($_SESSION['message'])) : ?>
                    <div class="toast align-items-center text-white bg-<?= $_SESSION['message_type']; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <?= htmlspecialchars($_SESSION['message']); ?>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                    <?php
                    // Hapus pesan dari sesi setelah ditampilkan
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                <?php endif; ?>
            </div>

            <div class="content-area">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-journal-check"></i> Detail Mata Kuliah
                    </div>
                    <div class="card-body">
                        <h4 class="mb-2 text-dark"><?= htmlspecialchars($course['nama_mk'] ?? 'Nama Mata Kuliah Tidak Ditemukan'); ?> (<?= htmlspecialchars($course['kode_mk'] ?? 'N/A'); ?>)</h4>
                        <p class="text-muted mb-0">
                            Semester: <span class="fw-bold"><?= htmlspecialchars($course['semester'] ?? 'N/A'); ?></span> |
                            SKS: <span class="fw-bold"><?= htmlspecialchars($course['sks'] ?? 'N/A'); ?></span>
                        </p>
                    </div>
                </div>

                <div class="mb-4 btn-action-group">
                    <a href="apload_materi.php?course_id=<?= $course_id ?>" class="btn btn-outline-primary">
                        <i class="bi bi-cloud-arrow-up"></i> Upload Materi
                    </a>
                    <a href="apload_tugas.php?course_id=<?= $course_id ?>" class="btn btn-outline-success">
                        <i class="bi bi-pencil-square"></i> Buat Tugas
                    </a>
                    <a href="input_absensi.php?course_id=<?= $course_id ?>" class="btn btn-outline-warning">
                        <i class="bi bi-calendar-plus"></i> Buat Absensi
                    </a>
                    <a href="lihat_pengumpulan.php?course_id=<?= $course_id ?>" class="btn btn-outline-danger">
                        <i class="bi bi-collection"></i> Pengumpulan Tugas
                    </a>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-book"></i> Materi Perkuliahan
                            </div>
                            <div class="card-body">
                                <?php if ($materi && mysqli_num_rows($materi) > 0) : ?>
                                    <ul class="list-group list-group-flush">
                                        <?php while ($m = mysqli_fetch_assoc($materi)) : ?>
                                            <li class="list-group-item">
                                                <div class="item-details">
                                                    <strong><?= htmlspecialchars($m['judul']); ?></strong>
                                                    <small class="text-muted d-block mt-1">
                                                        <i class="bi bi-tag me-1"></i>Jenis: <?= ucfirst(htmlspecialchars($m['jenis_materi'])); ?>
                                                        <i class="bi bi-clock me-1 ms-3"></i>Diunggah: <?= date("d M Y H:i", strtotime($m['created_at'])); ?>
                                                    </small>
                                                    <?php if (!empty($m['keterangan'])) : ?>
                                                        <small class="d-block mt-1">
                                                            <i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($m['keterangan']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    <?php if ($m['jenis_materi'] == 'file' && !empty($m['file_path'])) : ?>
                                                        <small class="d-block mt-1">
                                                            <i class="bi bi-file-earmark-arrow-down me-1"></i>File:
                                                            <a href="../assets/materi/<?= htmlspecialchars($m['file_path']); ?>" target="_blank" download>
                                                                <?= htmlspecialchars(basename($m['file_path'])); ?>
                                                            </a>
                                                        </small>
                                                    <?php elseif ($m['jenis_materi'] == 'text') : ?>
                                                        <small class="d-block mt-1">
                                                            <i class="bi bi-file-earmark-text me-1"></i>Konten Teks (lihat detail untuk membaca)
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="btn-group" role="group" aria-label="Aksi Materi">
                                                    <a href="edit_materi.php?materi_id=<?= $m['id'] ?>&course_id=<?= $course_id ?>" class="btn btn-warning btn-sm">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                    <a href="hapus_materi.php?materi_id=<?= $m['id']; ?>&course_id=<?= $course_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus materi ini? Ini juga akan menghapus file jika ada.')">
                                                        <i class="bi bi-trash"></i> Hapus
                                                    </a>
                                                </div>
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php else : ?>
                                    <div class="empty-state">
                                        <i class="bi bi-journal-x"></i>
                                        <h6>Belum Ada Materi</h6>
                                        <p>Silakan gunakan tombol "Upload Materi" untuk menambahkan materi baru.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <i class="bi bi-clipboard-check"></i> Tugas Mahasiswa
                            </div>
                            <div class="card-body">
                                <?php if ($tugas && mysqli_num_rows($tugas) > 0) : ?>
                                    <ul class="list-group list-group-flush">
                                        <?php while ($t = mysqli_fetch_assoc($tugas)) : ?>
                                            <li class="list-group-item">
                                                <div class="item-details">
                                                    <strong><?= htmlspecialchars($t['judul']); ?></strong>
                                                    <small class="text-muted d-block mt-1"><i class="bi bi-hourglass-split me-1"></i>Deadline: <?= date("d M Y H:i", strtotime($t['deadline'])); ?></small>
                                                    <small class="d-block mt-1">
                                                        <i class="bi bi-info-circle me-1"></i>
                                                        <a href="lihat_pengumpulan.php?tugas_id=<?= $t['id']; ?>&course_id=<?= $course_id ?>" class="text-decoration-none">Lihat Pengumpulan</a>
                                                    </small>
                                                </div>
                                                <div class="btn-group" role="group" aria-label="Aksi Tugas">
                                                    <a href="edit_tugas.php?id=<?= $t['id'] ?>&course_id=<?= $course_id ?>" class="btn btn-warning btn-sm">
                                                        <i class="bi bi-pencil"></i> Edit
                                                    </a>
                                                    <a href="hapus_tugas.php?tugas_id=<?= $t['id']; ?>&course_id=<?= $course_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin menghapus tugas ini? Ini juga akan menghapus semua pengumpulan yang terkait.')">
                                                        <i class="bi bi-trash"></i> Hapus
                                                    </a>
                                                </div>
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php else : ?>
                                    <div class="empty-state">
                                        <i class="bi bi-clipboard-x"></i>
                                        <h6>Belum Ada Tugas</h6>
                                        <p>Silakan gunakan tombol "Buat Tugas" untuk menambahkan tugas baru.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fungsi untuk menampilkan toast
        document.addEventListener('DOMContentLoaded', function() {
            var toastElList = [].slice.call(document.querySelectorAll('.toast'))
            var toastList = toastElList.map(function(toastEl) {
                return new bootstrap.Toast(toastEl, {
                    autohide: true,
                    delay: 5000 //
                })
            })
            toastList.forEach(toast => toast.show());
        });

        // Fungsi untuk toggle sidebar
        function toggleSidebar() {
            document.getElementById('wrapper').classList.toggle('collapsed');
        }
    </script>
</body>

</html>