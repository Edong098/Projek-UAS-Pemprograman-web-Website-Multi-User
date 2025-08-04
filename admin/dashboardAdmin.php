<?php
session_start();
include "../dbKonek.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];

// Pastikan koneksi database ada sebelum melakukan query
if ($konek) {
    $jumlah_dosen = mysqli_num_rows(mysqli_query($konek, "SELECT * FROM tb_user WHERE role='dosen'"));
    $jumlah_mahasiswa = mysqli_num_rows(mysqli_query($konek, "SELECT * FROM tb_user WHERE role='mahasiswa'"));
    $jumlah_mk = mysqli_num_rows(mysqli_query($konek, "SELECT * FROM tb_course"));
} else {
    // Handle jika koneksi gagal
    $jumlah_dosen = 0;
    $jumlah_mahasiswa = 0;
    $jumlah_mk = 0;
    error_log("Koneksi database gagal di dashboardAdmin.php");
}

// Menutup koneksi database di akhir skrip
if (isset($konek) && is_object($konek)) {
    mysqli_close($konek);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Variabel CSS untuk konsistensi & mudah diubah */
        :root {
            --sidebar-width: 280px;
            /* Lebar sidebar */
            --primary-blue: #007bff;
            /* Bootstrap primary blue */
            --dark-blue: #0056b3;
            /* Darker blue for sidebar background */
            --sidebar-bg: var(--dark-blue);
            /* Sidebar background */
            --sidebar-link-hover: #004085;
            /* Hover link sidebar */
            --navbar-bg: #FFFFFF;
            /* Navbar background tetap putih */
            --background-light: #F9FAFB;
            --card-background: #FFFFFF;
            --text-dark: #1F2937;
            --text-muted: #6B7280;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            /* Font modern */
            background-color: var(--background-light);
            color: var(--text-dark);
            overflow-x: hidden;
        }

        .wrapper {
            display: flex;
            transition: all 0.3s ease-in-out;
        }

        /* Sidebar Styling */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: white;
            height: 100vh;
            position: fixed;
            transition: all 0.3s ease-in-out;
            box-shadow: var(--shadow-lg);
            /* Tambahkan shadow */
            padding-top: 20px;
            z-index: 1000;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 15px 25px;
            /* Padding lebih besar */
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            gap: 15px;
            /* Jarak antara ikon dan teks */
            border-left: 5px solid transparent;
            /* Untuk highlight aktif */
        }

        .sidebar a:hover,
        .sidebar a.active {
            /* Kelas 'active' untuk halaman yang sedang dibuka */
            background-color: var(--sidebar-link-hover);
            color: white;
            border-left-color: var(--primary-blue);
            /* Warna highlight */
        }

        .sidebar .logo {
            font-family: 'Plus Jakarta Sans', sans-serif;
            /* Font khusus untuk logo */
            font-size: 1.8rem;
            font-weight: 700;
            padding: 15px 25px 30px;
            color: white;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar .logo i {
            margin-right: 10px;
            font-size: 2rem;
        }

        /* Content Area */
        .content {
            margin-left: var(--sidebar-width);
            width: 100%;
            transition: all 0.3s ease-in-out;
            padding: 20px;
        }

        /* Collapsed Sidebar State */
        .collapsed .sidebar {
            margin-left: calc(-1 * var(--sidebar-width));
        }

        .collapsed .content {
            margin-left: 0;
        }

        /* Navbar Styling */
        .navbar {
            background-color: var(--navbar-bg);
            /* Tetap putih */
            box-shadow: var(--shadow-sm);
            padding: 15px 25px;
            /* Padding lebih besar */
            border-radius: 12px;
            /* Sudut membulat */
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-dark);
            /* Warna teks di navbar menjadi dark */
        }

        .navbar .btn-toggle {
            background-color: var(--primary-blue);
            /* Warna tombol toggle biru */
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }

        .navbar .btn-toggle:hover {
            background-color: var(--dark-blue);
        }

        .navbar .fw-semibold,
        .navbar .fw-bold {
            color: var(--text-dark) !important;
            /* Pastikan teks di navbar berwarna dark */
        }

        /* Card Box Styling (Statistics) */
        .card-box {
            min-height: 120px;
            border-radius: 15px;
            /* Lebih membulat */
            color: white;
            padding: 25px;
            /* Padding lebih besar */
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .card-box::before {
            /* Efek overlay transparan */
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
            z-index: 1;
            pointer-events: none;
        }

        .card-box:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .card-box h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            z-index: 2;
        }

        .card-box p.fs-3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            font-size: 2.8rem !important;
            margin-top: auto;
            z-index: 2;
        }

        /* Card Colors */
        .bg-blue {
            background-color: #3498db;
            /* Biru cerah */
        }

        .bg-green {
            background-color: #2ecc71;
            /* Hijau emerald */
        }

        .bg-yellow {
            background-color: #f1c40f;
            /* Kuning cerah */
            color: var(--card-background);
            /* Ubah warna teks untuk kontras */
        }

        .bg-yellow::before {
            background: linear-gradient(45deg, rgba(231, 228, 228, 0.05) 0%, rgba(248, 248, 248, 0) 100%);
        }

        /* Admin Guide Section */
        .admin-guide {
            background-color: var(--card-background);
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--shadow-md);
            margin-top: 40px;
        }

        .admin-guide h5 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--primary-blue);
            /* Warna judul panduan */
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .admin-guide ul {
            list-style: none;
            padding: 0;
        }

        .admin-guide ul li {
            margin-bottom: 15px;
            color: var(--text-dark);
            font-size: 1rem;
            line-height: 1.5;
            display: flex;
            align-items: flex-start;
        }

        .admin-guide ul li i {
            color: var(--primary-blue);
            /* Warna ikon panduan */
            margin-right: 10px;
            font-size: 1.2rem;
            margin-top: 3px;
        }

        /* Responsive Adjustments */
        @media (max-width: 991.98px) {
            :root {
                --sidebar-width: 250px;
            }

            .sidebar {
                width: var(--sidebar-width);
            }

            .content {
                margin-left: var(--sidebar-width);
            }

            .collapsed .sidebar {
                margin-left: calc(-1 * var(--sidebar-width));
            }
        }

        @media (max-width: 767.98px) {
            .wrapper {
                flex-direction: column;
                /* Stack sidebar and content */
            }

            .sidebar {
                position: relative;
                /* Make sidebar flow naturally */
                width: 100%;
                height: auto;
                margin-left: 0;
                box-shadow: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                padding-top: 0;
            }

            .content {
                margin-left: 0;
                padding-top: 20px;
            }

            .collapsed .sidebar {
                /* Sembunyikan sidebar sepenuhnya pada mobile saat dicollapse */
                height: 0;
                overflow: hidden;
                padding-top: 0;
                margin-bottom: 0;
            }

            .collapsed .sidebar .logo,
            .collapsed .sidebar a {
                display: none;
            }

            .navbar {
                border-radius: 0;
                margin-bottom: 20px;
                position: sticky;
                /* Agar navbar tetap di atas saat scroll */
                top: 0;
                z-index: 100;
            }

            .card-box {
                min-height: 100px;
                padding: 20px;
            }

            .card-box p.fs-3 {
                font-size: 2.2rem !important;
            }

            .admin-guide {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper" id="wrapper">
        <div class="sidebar">
            <div class="logo"><i class="bi bi-mortarboard-fill"></i>LMS</div>
            <a href="dashboardAdmin.php" class="active"><i class="bi bi-house-door-fill"></i> Dashboard</a>
            <a href="manajemen_user.php"><i class="bi bi-people-fill"></i> Manajemen User</a>
            <a href="kelola_dosen.php"><i class="bi bi-person-badge-fill"></i> Manajemen Dosen</a>
            <a href="manajemen_mk.php"><i class="bi bi-journals"></i> Manajemen Mata Kuliah</a>
            <a href="kelola_mahasiswa.php"><i class="bi bi-person-lines-fill"></i> Manajemen mahasiswa</a>
            <a href="../logout.php" onclick=" return confirmLogout()"><i class="bi bi-box-arrow-right"></i> Logout</a>

            <script>
                function confirmLogout() {
                    return confirm("Apakah Anda yakin ingin keluar?");
                }
            </script>
        </div>

        <div class="content">
            <nav class="navbar d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
                    <span class="ms-3 fw-semibold fs-5">Dashboard Admin</span>
                </div>
                <a href="profil.php"><span class="fw-bold"><i class="bi bi-person-circle me-2"></i> <?= htmlspecialchars($username); ?></span>
                </a>
            </nav>

            <div class="container-fluid mt-4">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card-box bg-blue">
                            <h5><i class="bi bi-person-badge-fill"></i> Dosen</h5>
                            <p class="fs-3"><?= $jumlah_dosen; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-box bg-green">
                            <h5><i class="bi bi-person-lines-fill"></i> Mahasiswa</h5>
                            <p class="fs-3"><?= $jumlah_mahasiswa; ?></p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-box bg-yellow">
                            <h5><i class="bi bi-journal-code"></i> Mata Kuliah</h5>
                            <p class="fs-3"><?= $jumlah_mk; ?></p>
                        </div>
                    </div>
                </div>

                <div class="admin-guide">
                    <h5><i class="bi bi-info-circle-fill me-2"></i> Panduan Admin</h5>
                    <ul>
                        <li><i class="bi bi-check-circle-fill"></i> Gunakan menu "Manajemen User" untuk menambah atau mengelola akun dosen & mahasiswa.</li>
                        <li><i class="bi bi-check-circle-fill"></i> Gunakan menu "Manajemen Mata Kuliah" untuk menambahkan dan mengatur mata kuliah.</li>
                        <li><i class="bi bi-check-circle-fill"></i> Logout jika sesi telah selesai.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('wrapper').classList.toggle('collapsed');
        }

        // Add active class to current sidebar link
        document.addEventListener("DOMContentLoaded", function() {
            const currentPath = window.location.pathname.split('/').pop();
            const sidebarLinks = document.querySelectorAll('.sidebar a');
            sidebarLinks.forEach(link => {
                const linkPath = link.getAttribute('href').split('/').pop();
                if (linkPath === currentPath) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active'); // Remove active from others
                }
            });
        });
    </script>
</body>

</html>