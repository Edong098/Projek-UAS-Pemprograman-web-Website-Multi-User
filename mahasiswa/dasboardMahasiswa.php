<?php
session_start();
include "../dbKonek.php";

// Pastikan pengguna sudah login dan memiliki peran 'mahasiswa'
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'mahasiswa') {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];

// Ambil ID mahasiswa dari tb_user
$query_user = "SELECT id FROM tb_user WHERE username = ?";
$stmt_user = mysqli_prepare($konek, $query_user);
mysqli_stmt_bind_param($stmt_user, "s", $username);
mysqli_stmt_execute($stmt_user);
$result_user = mysqli_stmt_get_result($stmt_user);
$data_user = mysqli_fetch_assoc($result_user);

$mahasiswa_id = $data_user['id'] ?? null;
if (!$mahasiswa_id) {
    die("Mahasiswa tidak ditemukan.");
}

// Ambil filter semester (jika ada)
$semester_filter = isset($_GET['semester']) ? filter_var($_GET['semester'], FILTER_SANITIZE_STRING) : 'all';

// Query daftar mata kuliah yang diambil oleh mahasiswa
$sql_course = "SELECT c.* 
               FROM tb_course c
               INNER JOIN tb_krs k ON c.id = k.course_id
               WHERE k.mahasiswa_id = ?";

$params = [$mahasiswa_id];
$types = "i";

if ($semester_filter !== 'all' && $semester_filter !== '') {
    $sql_course .= " AND c.semester = ?";
    $params[] = $semester_filter;
    $types .= "s";
}

$stmt_course = mysqli_prepare($konek, $sql_course);
mysqli_stmt_bind_param($stmt_course, $types, ...$params);
mysqli_stmt_execute($stmt_course);
$courses = mysqli_stmt_get_result($stmt_course);

// Data pengumuman (bisa diambil dari database di masa depan)
$pengumuman = [
    ["judul" => "Liburan Idul Adha 1445 H", "tanggal" => "17 Juni 2024"],
    ["judul" => "Ujian Akhir Semester Ganjil 2024/2025", "tanggal" => "24 Juni - 05 Juli 2025"],
    ["judul" => "Pengisian KRS Semester Genap 2024/2025", "tanggal" => "15 - 30 Agustus 2025"]
];

// Menutup koneksi database di akhir skrip
mysqli_close($konek);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa - Sistem Akademik</title>
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
            --secondary-color: #6c757d;
            /* Gray */
            --background-light: #f4f7f6;
            /* Latar belakang lebih terang */
            --card-background: #ffffff;
            --border-color: #e0e0e0;
            --shadow-light: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-medium: 0 8px 16px rgba(0, 0, 0, 0.1);
            --text-dark: #212529;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-light);
            margin: 0;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .wrapper {
            display: flex;
            transition: all 0.3s ease-in-out;
            min-height: 100vh;
        }

        /* --- Sidebar Styling --- */
        .sidebar {
            width: 250px;
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
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
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

        /* --- Content Area Styling --- */
        .content {
            margin-left: 250px;
            width: calc(100% - 250px);
            transition: all 0.3s ease-in-out;
            padding-top: 80px;
            padding-bottom: 30px;
            padding-right: 30px;
            padding-left: 30px;
        }

        .collapsed .sidebar {
            margin-left: -250px;
        }

        .collapsed .content {
            margin-left: 0;
            width: 100%;
        }

        /* --- Navbar Styling --- */
        /* --- Navbar Styling --- */
        .navbar {
            background-color: var(--card-background);
            padding: 15px 30px;
            box-shadow: var(--shadow-light);
            position: fixed;
            top: 0;
            left: 250px;
            width: calc(100% - 250px);
            z-index: 1030;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease-in-out;
        }

        .collapsed .navbar {
            left: 0;
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

        /* KUNCI PERUBAHAN: Menambahkan 'gap' pada kontainer d-flex */
        .navbar .d-flex.align-items-center {
            /* Targetkan div yang membungkus tombol dan h5 */
            gap: 10px;
            /* Jarak antara tombol hamburger dan h5 */
        }

        .navbar h5 {
            color: var(--text-dark);
            margin: 0;
            /* Pastikan margin default direset */
            font-weight: 600;
            font-size: 1.4rem;
            /* margin-left: 20px; <--- Ini dihapus karena kita pakai 'gap' */
        }

        .navbar .icon-group i {
            color: var(--secondary-color);
            cursor: pointer;
            transition: color 0.2s ease, transform 0.2s ease;
            padding: 8px;
            border-radius: 50%;
        }

        .navbar .icon-group i:hover {
            color: var(--primary-color);
            background-color: #f0f2f5;
            transform: translateY(-2px);
        }

        /* --- Responsiveness (dipertahankan dan disesuaikan) --- */
        @media (min-width: 768px) and (max-width: 991px) {
            .navbar {
                left: 0;
                width: 100%;
                padding: 15px 20px;
            }

            .collapsed .navbar {
                left: 250px;
                width: calc(100% - 250px);
            }

            .navbar .d-flex.align-items-center {
                gap: 15px;
                /* Kurangi gap untuk tablet */
            }

            .navbar h5 {
                font-size: 1.2rem;
            }

            .navbar .btn-outline-secondary {
                padding: 8px 12px;
                font-size: 1rem;
            }
        }

        @media (max-width: 767.98px) {
            .navbar .d-flex.align-items-center {
                gap: 10px;
                /* Kurangi gap lagi untuk mobile */
            }

            .navbar h5 {
                font-size: 1.2rem;
            }

            .navbar .btn-outline-secondary {
                padding: 8px 12px;
                font-size: 1rem;
            }

            .navbar .icon-group i {
                font-size: 1.3rem;
                padding: 6px;
            }
        }

        @media (max-width: 575px) {
            .navbar .icon-group {
                gap: 10px;
            }

            .navbar .icon-group i {
                font-size: 1.2rem;
            }
        }

        /* --- Dashboard Content Styling --- */
        .dashboard-header {
            padding: 25px 0;
            margin-bottom: 25px;
        }

        .dashboard-header h4 {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .dashboard-header h6 {
            font-weight: 500;
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        /* KUNCI PERUBAHAN UNTUK FILTER-SORT-GROUP */
        .filter-sort-group {
            display: flex;
            flex-wrap: wrap;
            /* Allows items to wrap to the next line */
            align-items: center;
            gap: 0.75rem;
            /* Consistent spacing between items */
            margin-bottom: 30px;
            justify-content: flex-start;
            /* Aligns items to the start of the container */
        }

        /* Common styling for select and input within the filter group */
        .filter-sort-group .form-select,
        .filter-sort-group .form-control {
            border-radius: 12px;
            /* Very rounded corners, as per your image */
            padding: 0.75rem 1.25rem;
            /* Larger padding for better aesthetics */
            border: 1px solid var(--border-color);
            /* Light border */
            box-shadow: none;
            /* Remove default Bootstrap shadow */
            font-size: 0.95rem;
            color: var(--text-dark);
            background-color: var(--card-background);
            transition: all 0.2s ease-in-out;
        }

        .filter-sort-group .form-control:focus,
        .filter-sort-group .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(79, 70, 229, 0.25);
        }

        /* Flex item sizing for column-like behavior */
        .filter-sort-group .flex-item {
            flex-basis: auto;
            /* Default basis is content size */
            min-width: 150px;
            /* Minimum width for each item (adjust as needed) */
        }

        /* Specific width adjustments for each item */
        .filter-sort-group .flex-item:first-child {
            /* Semester Select */
            width: 180px;
            /* Adjust as needed */
        }

        .filter-sort-group .flex-item:nth-child(2) {
            /* Cari Mata Kuliah Input */
            flex-grow: 1;
            /* Allow it to grow and fill available space */
            max-width: 300px;
            /* Limit its max width */
            min-width: 200px;
            /* Ensure it's not too small */
        }

        .filter-sort-group .flex-item:nth-child(3) {
            /* Urutkan Berdasarkan Select */
            width: 240px;
            /* Adjust as needed */
        }

        .filter-sort-group .flex-item:nth-child(4) {
            /* Tampilan Select */
            width: 180px;
            /* Adjust as needed */
        }

        /* --- Materi Card Styling --- */
        .course-card {
            border-radius: 15px;
            border: 1px solid var(--border-color);
            background: var(--card-background);
            transition: all 0.3s ease-in-out;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .course-card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-5px);
            border-color: var(--primary-color);
        }

        .course-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-top-left-radius: 14px;
            border-top-right-radius: 14px;
        }

        .course-card .card-body {
            padding: 20px;
            flex-grow: 1;
            margin: 1;
            line-height: 0.5;
        }

        .course-card .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .course-card .card-text small {
            font-size: 0.9rem;
            color: var(--text-muted);
            display: block;
            margin-bottom: 3px;
        }

        .course-card .card-footer {
            background-color: #fcfcfc;
            border-top: 1px solid #f0f0f0;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .course-card .btn-outline-primary {
            border-radius: 8px;
            font-weight: 500;
            padding: 8px 15px;
            border-color: var(--primary-color);
            color: var(--primary-color);
            transition: all 0.2s ease;
        }

        .course-card .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .course-card .btn-light {
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 1.1rem;
            color: var(--secondary-color);
            transition: all 0.2s ease;
        }

        .course-card .btn-light:hover {
            background-color: #e9ecef;
            color: var(--text-dark);
        }

        /* --- Offcanvas (Right Panel) Styling --- */
        .offcanvas {
            background-color: var(--card-background);
            box-shadow: -6px 0 20px rgba(0, 0, 0, 0.1);
        }

        .offcanvas-header {
            border-bottom: 1px solid var(--border-color);
            padding: 20px 25px;
        }

        .offcanvas-title {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .offcanvas-body {
            padding: 20px 25px;
        }

        .offcanvas-body h6 {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 8px;
            font-size: 1.1rem;
        }

        .offcanvas-body .list-group-item {
            border-radius: 10px;
            margin-bottom: 10px;
            border: 1px solid #f0f0f0;
            background-color: #fdfdfd;
            padding: 15px;
            transition: all 0.2s ease;
        }

        .offcanvas-body .list-group-item:hover {
            background-color: #f8f8f8;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .offcanvas-body .list-group-item strong {
            display: block;
            font-size: 1rem;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .offcanvas-body .list-group-item small {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
            background-color: var(--card-background);
            border-radius: 15px;
            border: 2px dashed var(--border-color);
            margin-top: 30px;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ced4da;
            margin-bottom: 20px;
        }

        .empty-state h5 {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 10px;
            font-size: 1.5rem;
        }

        .empty-state p {
            font-size: 1rem;
            color: var(--text-muted);
        }


        /* --- Responsiveness (Updated for filter-sort-group) --- */
        /* For large desktops and above */
        @media (min-width: 1200px) {}

        /* For desktops (>= 992px) */
        @media (min-width: 992px) {
            .col-lg-4 {
                width: 33.333333%;
            }
        }

        /* For tablets (>= 768px and < 992px) */
        @media (min-width: 768px) and (max-width: 991px) {
            .sidebar {
                margin-left: -250px;
                position: fixed;
                height: 100vh;
                top: 0;
            }

            .content {
                margin-left: 0;
                width: 100%;
                padding-left: 20px;
            }

            .collapsed .sidebar {
                margin-left: 0;
            }

            .collapsed .content {
                margin-left: 250px;
                width: calc(100% - 250px);
            }

            .navbar {
                left: 0;
                width: 100%;
                padding: 15px 20px;
            }

            .collapsed .navbar {
                left: 250px;
                width: calc(100% - 250px);
            }

            .dashboard-header h4 {
                font-size: 1.8rem;
            }

            .dashboard-header h6 {
                font-size: 1rem;
            }

            .filter-sort-group {
                justify-content: space-between;
                /* Distribute space */
            }

            .filter-sort-group .flex-item {
                width: calc(50% - 0.5rem);
                /* 2 columns with gap consideration */
                max-width: none;
                /* Remove max-width on smaller screens to allow 50% */
            }


        }

        /* For mobile phones (< 768px) */
        @media (max-width: 767.98px) {
            .navbar h5 {
                font-size: 1.2rem;
                margin-left: 10px;
            }

            .navbar .btn-outline-secondary {
                padding: 8px 12px;
                font-size: 1rem;
            }

            .navbar .icon-group i {
                font-size: 1.3rem;
                padding: 6px;
            }

            .sidebar .logo {
                font-size: 1.4rem;
                padding: 20px 15px;
            }

            .sidebar .nav-link-list a {
                padding: 14px 20px;
                font-size: 0.95rem;
                gap: 12px;
            }

            .sidebar .nav-link-list a i {
                font-size: 1.3rem;
            }

            .filter-sort-group {
                flex-direction: column;
                /* Stack vertically on small screens */
                gap: 0.75rem;
                /* Maintain gap between stacked items */
                align-items: stretch;
                /* Make items stretch to full width */
            }

            .filter-sort-group .flex-item {
                width: 100% !important;
                /* Force full width on mobile */
                max-width: none !important;
                /* Remove any max-width constraints */
                min-width: unset !important;
                /* Remove min-width constraints */
            }

            .course-card .card-body {
                padding: 15px;
            }

            .course-card .card-footer {
                flex-direction: column;
                gap: 10px;
                padding: 15px;
            }

            .course-card .btn-outline-primary,
            .course-card .dropdown {
                width: 100%;
            }

            .col-sm-12,
            .col-md-6,
            .col-lg-4 {
                width: 100%;
                /* Force 1 column on mobile */
            }
        }

        /* For extra small screens (e.g., < 576px) */
        @media (max-width: 575px) {
            .navbar .icon-group {
                gap: 10px;
            }

            .navbar .icon-group i {
                font-size: 1.2rem;
            }

            .dashboard-header h4 {
                font-size: 1.5rem;
            }

            .dashboard-header h6 {
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper" id="wrapper">
        <div class="sidebar">
            <div class="logo">
                <i class="bi bi-mortarboard-fill"></i> LMS
            </div>
            <div class="nav-link-list">
                <a href="dasboardMahasiswa.php"
                    class="<?= (basename($_SERVER['PHP_SELF']) == 'dashboardMahasiswa.php' && !isset($_GET['course_id'])) ? 'active' : ''; ?>">
                    <i class="bi bi-house-door-fill"></i> Dashboard
                </a>

                <a href="materi.php"
                    class="<?= (basename($_SERVER['PHP_SELF']) == 'materi.php') ? 'active' : ''; ?>">
                    <i class="bi bi-journal-text"></i> Materi
                </a>

                <a href="upload_tugas.php"
                    class="<?= (basename($_SERVER['PHP_SELF']) == 'upload_tugas.php') ? 'active' : ''; ?>">
                    <i class="bi bi-pencil-square"></i> Tugas
                </a>

                <a href="isi_absensi.php"
                    class="<?= (basename($_SERVER['PHP_SELF']) == 'isi_absensi.php') ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-check"></i> Absensi
                </a>
                <a href="../logout.php"
                    class="btn btn-logout w-75"
                    onclick="return confirm('Apakah Anda yakin ingin keluar?')">
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
                    <h5 class="m-0">Dashboard Mahasiswa</h5>
                </div>
                <div class="d-flex align-items-center gap-2 icon-group">
                    <span class="me-2 fs-3"><i class="bi bi-person-circle"></i></span>
                    <i class="bi bi-bell fs-4" data-bs-toggle="offcanvas" data-bs-target="#rightPanel"></i>
                    <i class="bi bi-gear fs-4"></i>
                </div>
            </nav>

            <div class="container-fluid px-4">
                <div class="dashboard-header">
                    <h4>Selamat Datang, <?= htmlspecialchars($username); ?>! 👋</h4>
                    <h7>Jelajahi mata kuliah dan informasi terbaru Anda.</h7>
                </div>

                <h4>Mata Kuliah Aktif</h4>
                <div class="filter-sort-group d-flex flex-wrap align-items-center gap-3 mb-4">
                    <select name="semester" onchange="location.href='?semester=' + this.value" class="form-select custom-rounded-select flex-item">
                        <option value="all" <?= $semester_filter == 'all' ? 'selected' : '' ?>>Semua Semester</option>
                        <option value="1" <?= $semester_filter == '1' ? 'selected' : '' ?>>Semester 1</option>
                        <option value="2" <?= $semester_filter == '2' ? 'selected' : '' ?>>Semester 2</option>
                        <option value="3" <?= $semester_filter == '3' ? 'selected' : '' ?>>Semester 3</option>
                        <option value="4" <?= $semester_filter == '4' ? 'selected' : '' ?>>Semester 4</option>
                        <option value="5" <?= $semester_filter == '5' ? 'selected' : '' ?>>Semester 5</option>
                        <option value="6" <?= $semester_filter == '6' ? 'selected' : '' ?>>Semester 6</option>
                        <option value="7" <?= $semester_filter == '7' ? 'selected' : '' ?>>Semester 7</option>
                        <option value="8" <?= $semester_filter == '8' ? 'selected' : '' ?>>Semester 8</option>
                    </select>
                    <input type="text" class="form-control custom-rounded-input flex-item" placeholder="Cari mata kuliah...">
                    <select class="form-select custom-rounded-select flex-item">
                        <option>Tampilan Card</option>
                        <option>Tampilan List</option>
                    </select>
                </div>

                <div class="row g-4">
                    <?php if (mysqli_num_rows($courses) > 0) : ?>
                        <?php while ($row = mysqli_fetch_assoc($courses)) : ?>
                            <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-4">
                                <div class="card course-card">
                                    <img src="<?= !empty($row['gambar']) ? '../' . htmlspecialchars($row['gambar']) : '../assets/images/default.jpg' ?>"
                                        class="card-img-top" alt="Course Image" style="height: 170px; object-fit:cover;">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($row['kode_mk']) ?> - <?= htmlspecialchars($row['nama_mk']) ?></h5>
                                        <p class="card-text"><small>Semester <?= $row['semester'] ?></small></p>
                                        <p class="card-text"><small>SKS <?= $row['sks'] ?></small></p>
                                        <p class="card-text"><small>Dosen: <?= htmlspecialchars($row['dosen']) ?></small></p>
                                    </div>
                                    <div class="card-footer d-flex justify-content-between align-items-center">
                                        <a href="materi.php?course_id=<?= $row['id']; ?>" class="btn btn-outline-primary">Lihat Materi</a>
                                        <div class="dropdown">
                                            <button class="btn btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                <li><a class="dropdown-item" href="#">Detail Mata Kuliah</a></li>
                                                <li><a class="dropdown-item" href="#">Forum Diskusi</a></li>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li><a class="dropdown-item text-danger" href="#">Laporkan Masalah</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else : ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <i class="bi bi-emoji-frown"></i>
                                <h5>Belum Ada Mata Kuliah Ditemukan</h5>
                                <p>Coba sesuaikan filter semester atau tambahkan mata kuliah baru.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="offcanvas offcanvas-end" tabindex="-1" id="rightPanel" aria-labelledby="rightPanelLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="rightPanelLabel"><i class="bi bi-megaphone-fill"></i> Info & Notifikasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <h6>Pengumuman Terbaru</h6>
                <ul class="list-group list-group-flush">
                    <?php if (!empty($pengumuman)) : ?>
                        <?php foreach ($pengumuman as $p) : ?>
                            <li class="list-group-item">
                                <strong><?= htmlspecialchars($p['judul']) ?></strong><br>
                                <small class="text-muted"><i class="bi bi-calendar-event me-1"></i> <?= htmlspecialchars($p['tanggal']) ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="text-muted text-center">Tidak ada pengumuman terbaru.</p>
                    <?php endif; ?>
                </ul>

                <h6 class="mt-4">Pemberitahuan Sistem</h6>
                <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
                    <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                    <div>Selamat datang di dashboard baru Anda!</div>
                </div>
                <div class="alert alert-warning d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div>Pastikan profil Anda sudah lengkap.</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const wrapper = document.getElementById('wrapper');
            const navbarToggleButton = document.querySelector('.navbar .btn-outline-secondary');

            function toggleSidebar() {
                wrapper.classList.toggle('collapsed');
                // Simpan status collapsed ke localStorage
                if (wrapper.classList.contains('collapsed')) {
                    localStorage.setItem('sidebarCollapsed', 'true');
                } else {
                    localStorage.removeItem('sidebarCollapsed');
                }
            }

            // Memuat status collapsed dari localStorage saat halaman dimuat
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                wrapper.classList.add('collapsed');
            }

            if (navbarToggleButton) {
                navbarToggleButton.addEventListener('click', toggleSidebar);
            }

            // Atur status sidebar saat ukuran layar berubah (untuk responsif)
            function handleSidebarOnResize() {
                if (window.innerWidth <= 991) { // Ubah breakpoint menjadi 991px (kurang dari lg)
                    wrapper.classList.add('collapsed'); // Sembunyikan sidebar secara default pada layar kecil
                } else {
                    // Jika layar besar, pulihkan status dari localStorage atau tampilkan
                    if (localStorage.getItem('sidebarCollapsed') !== 'true') {
                        wrapper.classList.remove('collapsed');
                    }
                }
            }

            // Panggil fungsi saat halaman pertama kali dimuat dan saat ukuran jendela berubah
            handleSidebarOnResize();
            window.addEventListener('resize', handleSidebarOnResize);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize and show Bootstrap Toasts
        document.addEventListener('DOMContentLoaded', function() {
            const toastElList = document.querySelectorAll('.toast');
            toastElList.forEach(toastEl => {
                const toast = new bootstrap.Toast(toastEl, {
                    delay: 5000
                }); // Auto-hide after 5 seconds
                toast.show();
            });
        });
    </script>
</body>

</html>