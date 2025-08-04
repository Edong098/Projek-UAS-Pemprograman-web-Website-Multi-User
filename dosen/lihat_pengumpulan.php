<?php
session_start();
// Pastikan path ke dbKonek.php sudah benar, relatif dari lokasi file ini
include "../dbKonek.php";

// Pastikan pengguna sudah login dan role-nya adalah 'dosen'
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'dosen') {
    header("Location: ../login.php");
    exit;
}

$tugas_id = isset($_GET['tugas_id']) ? intval($_GET['tugas_id']) : 0;
$pengumpulan = [];
$tugas = null; // Inisialisasi variabel tugas

// Mendapatkan nama mata kuliah untuk judul navbar, jika ada course_id yang terkait
$nama_mk_navbar = "Pengelolaan Tugas"; // Default
if ($tugas_id > 0) {
    $stmt_get_course_id = mysqli_prepare($konek, "SELECT course_id FROM tb_tugas WHERE id = ?");
    if ($stmt_get_course_id) {
        mysqli_stmt_bind_param($stmt_get_course_id, "i", $tugas_id);
        mysqli_stmt_execute($stmt_get_course_id);
        $result_get_course_id = mysqli_stmt_get_result($stmt_get_course_id);
        $row_course_id = mysqli_fetch_assoc($result_get_course_id);
        mysqli_stmt_close($stmt_get_course_id);

        if ($row_course_id && $row_course_id['course_id']) {
            $course_id_for_navbar = $row_course_id['course_id'];
            $stmt_get_mk_name = mysqli_prepare($konek, "SELECT nama_mk FROM tb_course WHERE id = ?");
            if ($stmt_get_mk_name) {
                mysqli_stmt_bind_param($stmt_get_mk_name, "i", $course_id_for_navbar);
                mysqli_stmt_execute($stmt_get_mk_name);
                $result_get_mk_name = mysqli_stmt_get_result($stmt_get_mk_name);
                $row_mk_name = mysqli_fetch_assoc($result_get_mk_name);
                mysqli_stmt_close($stmt_get_mk_name);
                if ($row_mk_name) {
                    $nama_mk_navbar = htmlspecialchars($row_mk_name['nama_mk']);
                }
            }
        }
    }
}


// Cek apakah ada tugas_id yang diberikan di URL dan valid
if ($tugas_id > 0) {
    // Ambil data tugas berdasarkan ID
    $stmt_tugas = mysqli_prepare($konek, "SELECT * FROM tb_tugas WHERE id = ?");
    if ($stmt_tugas) {
        mysqli_stmt_bind_param($stmt_tugas, "i", $tugas_id);
        mysqli_stmt_execute($stmt_tugas);
        $result_tugas = mysqli_stmt_get_result($stmt_tugas);
        $tugas = mysqli_fetch_assoc($result_tugas);
        mysqli_stmt_close($stmt_tugas);

        if (!$tugas) {
            // Jika tugas tidak ditemukan di database
            $tugas_id = 0; // Reset tugas_id agar halaman menampilkan daftar tugas
            // echo "<div class='alert alert-danger mt-3 text-center'>❌ **Tugas tidak ditemukan!** Pastikan ID tugas valid.</div>"; // Alert ini akan tampil di posisi yang tidak bagus
        } else {
            // Jika tugas ditemukan, ambil data pengumpulan
            $stmt_pengumpulan = mysqli_prepare($konek, "
                SELECT p.*, u.username
                FROM tb_pengumpulan p
                JOIN tb_user u ON p.mahasiswa_username = u.username
                WHERE p.tugas_id = ?
                ORDER BY p.tanggal_kumpul DESC
            ");
            if ($stmt_pengumpulan) {
                mysqli_stmt_bind_param($stmt_pengumpulan, "i", $tugas_id);
                mysqli_stmt_execute($stmt_pengumpulan);
                $result_pengumpulan = mysqli_stmt_get_result($stmt_pengumpulan);

                while ($row = mysqli_fetch_assoc($result_pengumpulan)) {
                    $pengumpulan[] = $row;
                }
                mysqli_stmt_close($stmt_pengumpulan);
            } else {
                echo "<div class='alert alert-danger mt-3 text-center'>❌ Gagal menyiapkan query pengumpulan: " . mysqli_error($konek) . "</div>";
            }
        }
    } else {
        echo "<div class='alert alert-danger mt-3 text-center'>❌ Gagal menyiapkan query tugas: " . mysqli_error($konek) . "</div>";
    }
}
// Koneksi database akan ditutup di bagian paling bawah file setelah semua operasi selesai
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pengumpulan Tugas <?= $tugas ? ': ' . htmlspecialchars($tugas['judul']) : '' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #4338ca;
            /* Biru cerah, profesional */
            --primary-dark: #357ABD;
            /* Biru lebih gelap untuk hover/active */
            --secondary-color: #6C757D;
            /* Abu-abu netral */
            --secondary-dark: #5A6268;
            --success-color: #5cb85c;
            /* Hijau untuk sukses */
            --success-dark: #4CAE4C;
            --info-color: #5bc0de;
            /* Biru muda untuk info */
            --info-dark: #46B8DA;
            --danger-color: #dc3545;
            /* Merah untuk bahaya/error */
            --warning-color: #ffc107;
            /* Kuning untuk peringatan */

            --background-light: #f4f7f6;
            /* Latar belakang lebih lembut */
            --card-background: #ffffff;
            --border-color: #e0e6ed;
            /* Warna border yang lebih halus */
            --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08);
            /* Shadow yang lebih halus */
            --text-dark: #333333;
            /* Warna teks utama */
            --text-muted: #666666;
            /* Warna teks muted */
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-light);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            padding-top: 70px;
            /* Sesuaikan dengan tinggi navbar */
        }

        /* Top Navbar Styles */
        .top-navbar {
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--primary-color);
            /* Menggunakan primary-color */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
            /* Shadow lebih lembut */
            z-index: 1030;
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .top-navbar .nav-links-group {
            display: flex;
            align-items: center;
        }

        .top-navbar .nav-link {
            display: flex;
            align-items: center;
            color: #fff;
            font-size: 0.95rem;
            /* Ukuran font sedikit lebih besar */
            text-decoration: none;
            padding: 8px 15px;
            transition: all 0.3s ease;
            /* Transisi untuk semua properti */
            border-radius: 5px;
            margin: 0 5px;
            /* Tambahkan sedikit margin antar link */
        }

        .top-navbar .nav-link:hover {
            color: #fff;
            background-color: var(--primary-dark);
            /* Hover dengan biru lebih gelap */
            transform: translateY(-2px);
            /* Efek sedikit terangkat */
        }

        .top-navbar .nav-link i {
            font-size: 1.2rem;
            margin-right: 8px;
            /* Jarak ikon lebih jauh */
        }

        .top-navbar .nav-link.active {
            font-weight: bold;
            background-color: var(--primary-dark);
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.2);
            /* Efek inset untuk active */
        }

        .top-navbar .navbar-title {
            font-size: 1.2rem;
            /* Ukuran judul sedikit lebih besar */
            font-weight: 700;
            /* Lebih tebal */
            color: #fff;
            margin: 0;
            /* Pastikan tidak ada margin default */
        }

        /* Responsive adjustments for top navbar */
        @media (max-width: 576px) {
            .top-navbar {
                justify-content: space-around;
                padding: 0 10px;
                height: 55px;
            }

            .top-navbar .navbar-title {
                display: none;
            }

            .top-navbar .nav-link {
                font-size: 0.8rem;
                padding: 8px 10px;
                margin: 0 2px;
            }

            .top-navbar .nav-link i {
                font-size: 1.1rem;
                margin-right: 3px;
            }
        }

        /* End Top Navbar Styles */

        .content-wrapper {
            flex-grow: 1;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: var(--shadow-light);
            background-color: var(--card-background);
            width: 100%;
            max-width: 1000px;
            overflow: hidden;
            /* Memastikan border-radius bekerja pada children */
        }

        .card-header-custom {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem 2rem;
            /* Padding lebih besar */
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            /* Garis bawah transparan */
            font-size: 1.35rem;
            /* Ukuran font lebih besar */
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            /* Jarak ikon dengan teks */
        }

        h4.card-title-main {
            color: var(--primary-color);
            font-weight: 700;
            /* Lebih tebal */
            margin-bottom: 1.5rem;
            padding-top: 0.5rem;
            border-bottom: 2px solid var(--border-color);
            /* Garis bawah yang menonjol */
            padding-bottom: 10px;
        }

        .table-responsive {
            margin-top: 1.5rem;
        }

        .table {
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 0.5rem;
            overflow: hidden;
            /* Untuk memastikan border-radius pada tabel */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            /* Sedikit shadow pada tabel */
        }

        .table th,
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border: 1px solid var(--border-color);
            /* Border per sel */
        }

        .table thead th {
            background-color: var(--primary-dark);
            /* Header tabel lebih gelap */
            color: white;
            border-color: var(--primary-dark);
            font-weight: 600;
            /* Sedikit lebih tebal */
            white-space: nowrap;
        }

        .table tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
            /* Sedikit abu-abu untuk baris ganjil */
        }

        .table tbody tr:hover {
            background-color: rgba(74, 144, 226, 0.1);
            /* Hover dengan transparan dari primary-color */
            cursor: pointer;
            transform: scale(1.005);
            /* Sedikit membesar saat hover */
            transition: all 0.2s ease-in-out;
        }

        .btn-custom {
            border-radius: 0.5rem;
            padding: 0.7rem 1.4rem;
            /* Padding lebih nyaman */
            font-weight: 600;
            /* Lebih tebal */
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-transform: uppercase;
            /* Huruf kapital */
            letter-spacing: 0.5px;
        }

        .btn-download {
            background-color: var(--success-color);
            border-color: var(--success-color);
            color: white;
            box-shadow: 0 2px 5px rgba(92, 184, 92, 0.3);
            /* Shadow hijau */
        }

        .btn-download:hover {
            background-color: var(--success-dark);
            border-color: var(--success-dark);
            transform: translateY(-2px);
            /* Efek terangkat */
            box-shadow: 0 4px 8px rgba(92, 184, 92, 0.4);
        }

        .btn-back {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white;
            box-shadow: 0 2px 5px rgba(108, 117, 125, 0.3);
            /* Shadow abu-abu */
        }

        .btn-back:hover {
            background-color: var(--secondary-dark);
            border-color: var(--secondary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.4);
        }

        .badge-status {
            font-size: 0.9em;
            /* Ukuran badge sedikit lebih besar */
            padding: 0.6em 1em;
            /* Padding badge lebih baik */
            border-radius: 0.35rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
        }

        .list-group-item {
            font-size: 1.05rem;
            /* Ukuran font item list lebih besar */
            padding: 1.2rem 1.8rem;
            /* Padding lebih luas */
            border-radius: 0.6rem !important;
            /* Border radius lebih besar */
            margin-bottom: 0.75rem;
            /* Jarak antar item lebih lega */
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
            background-color: var(--card-background);
            /* Pastikan background putih */
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            /* Sedikit shadow pada item list */
        }

        .list-group-item:hover {
            background-color: rgba(74, 144, 226, 0.08);
            /* Hover lebih menonjol */
            border-color: var(--primary-color);
            transform: translateY(-3px);
            /* Efek terangkat lebih terasa */
            box-shadow: 0 6px 12px rgba(74, 144, 226, 0.2);
            /* Shadow hover yang lebih kuat */
        }

        .list-group-item .badge {
            font-size: 0.95em;
            padding: 0.5em 0.9em;
            background-color: var(--primary-color);
            font-weight: 700;
        }

        /* Alert styling */
        .alert {
            border-radius: 0.5rem;
            font-size: 1rem;
            /* Ukuran font alert lebih standar */
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            /* Shadow pada alert */
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }


        /* Responsive adjustments for content */
        @media (max-width: 768px) {
            .card-header-custom {
                font-size: 1.2rem;
                padding: 1.2rem 1.5rem;
            }

            h4.card-title-main {
                font-size: 1.3rem;
            }

            .table th,
            .table td {
                padding: 0.8rem;
                font-size: 0.9rem;
            }

            .btn-custom {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }

            .list-group-item {
                padding: 1rem 1.5rem;
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .content-wrapper {
                padding: 15px;
            }

            .card {
                border-radius: 0;
                box-shadow: none;
            }

            .card-header-custom {
                border-radius: 0;
                font-size: 1.1rem;
                padding: 1rem 1.2rem;
            }

            h4.card-title-main {
                font-size: 1.2rem;
                margin-bottom: 1rem;
                padding-top: 0;
            }

            .table-responsive {
                margin-top: 1rem;
            }

            .table th,
            .table td {
                padding: 0.6rem;
                font-size: 0.85rem;
            }

            .list-group-item {
                padding: 0.9rem 1.2rem;
                font-size: 0.95rem;
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
        <div class="card">
            <?php if ($tugas_id > 0 && $tugas) : // Tampilkan detail pengumpulan jika tugas valid dan ditemukan
            ?>
                <div class="card-header-custom">
                    <i class="fas fa-inbox"></i> Daftar Pengumpulan Tugas
                </div>
                <div class="card-body">
                    <h4 class="card-title-main mb-3">Tugas: <span class="fw-bold"><?= htmlspecialchars($tugas['judul']); ?></span></h4>
                    <?php if (empty($pengumpulan)) : ?>
                        <div class="alert alert-info py-3 text-center">
                            <i class="fas fa-info-circle me-2"></i> Belum ada mahasiswa yang mengumpulkan tugas ini.
                        </div>
                    <?php else : ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th scope="col" class="text-center">No</th>
                                        <th scope="col">Username Mahasiswa</th>
                                        <th scope="col">Waktu Pengumpulan</th>
                                        <th scope="col" class="text-center">File</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pengumpulan as $index => $row) : ?>
                                        <tr>
                                            <td class="text-center"><?= $index + 1 ?></td>
                                            <td><i class="fas fa-user-graduate me-2"></i><?= htmlspecialchars($row['username']) ?></td>
                                            <td><i class="far fa-clock me-2"></i><?= htmlspecialchars(date('d M Y H:i', strtotime($row['tanggal_kumpul']))) ?></td>
                                            <td class="text-center">
                                                <?php if (!empty($row['file_path'])) : ?>
                                                    <a href="../assets/tugas/<?= htmlspecialchars($row['file_path']) ?>" class="btn btn-sm btn-download btn-custom" download>
                                                        <i class="fas fa-download me-1"></i> Download
                                                    </a>
                                                <?php else : ?>
                                                    <span class="badge bg-danger badge-status"><i class="fas fa-times-circle"></i> Belum Upload</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else : // Tampilkan daftar tugas untuk dipilih jika tidak ada tugas_id atau tugas tidak ditemukan
            ?>

                <div class="card-header-custom">
                    <i class="fas fa-folder-open"></i> Pilih Tugas untuk Melihat Pengumpulan
                </div>
                <div class="card-body">
                    <?php
                    // Ambil semua tugas untuk ditampilkan dalam daftar pilihan
                    // Ambil tugas yang dibuat oleh dosen yang sedang login
                    $username_dosen_login = $_SESSION['username'];
                    $tugas_all_stmt = mysqli_prepare($konek, "
                        SELECT t.id, t.judul, c.nama_mk
                        FROM tb_tugas t
                        JOIN tb_course c ON t.course_id = c.id
                        WHERE c.dosen = ?
                        ORDER BY t.created_at DESC
                    ");
                    $tugas_list_available = false; // Flag untuk mengecek apakah ada tugas
                    if ($tugas_all_stmt) {
                        mysqli_stmt_bind_param($tugas_all_stmt, "s", $username_dosen_login);
                        mysqli_stmt_execute($tugas_all_stmt);
                        $result_tugas_all = mysqli_stmt_get_result($tugas_all_stmt);

                        if (mysqli_num_rows($result_tugas_all) > 0) :
                            $tugas_list_available = true;
                    ?>
                            <div class="list-group">
                                <?php while ($row = mysqli_fetch_assoc($result_tugas_all)) : ?>
                                    <a href="lihat_pengumpulan.php?tugas_id=<?= $row['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-medium"><i class="fas fa-file-alt me-2 text-muted"></i><?= htmlspecialchars($row['judul']); ?></span>
                                            <small class="text-muted d-block ms-4">Mata Kuliah: <?= htmlspecialchars($row['nama_mk']); ?></small>
                                        </div>
                                        <span class="badge bg-primary rounded-pill"><i class="fas fa-eye"></i> Lihat</span>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                        <?php else : ?>
                            <div class="alert alert-info py-3 text-center">
                                <i class="fas fa-info-circle me-2"></i> Belum ada tugas yang dibuat oleh Anda.
                            </div>
                    <?php endif;
                        mysqli_stmt_close($tugas_all_stmt);
                    } else {
                        echo "<div class='alert alert-danger py-3 text-center'>Gagal mengambil daftar tugas. " . mysqli_error($konek) . "</div>";
                    }
                    ?>
                </div>

            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>