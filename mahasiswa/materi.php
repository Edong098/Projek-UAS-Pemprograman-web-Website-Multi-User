<?php
session_start();
date_default_timezone_set("Asia/Makassar");
include "../dbKonek.php";

// Pastikan pengguna sudah login dan memiliki peran 'mahasiswa'
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'mahasiswa') {
    header("Location: ../login.php");
    exit;
}

// Ambil username dari sesi
$username = $_SESSION['username'];

// Menggunakan filter_input untuk sanitasi input GET 'course_id'
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);

// Validasi course_id: harus ada dan lebih besar dari 0
if (!$course_id || $course_id <= 0) {
    header("Location: dashboardMahasiswa.php?error=course_not_found");
    exit;
}

// PERBAIKAN 1: Ambil data course terlebih dahulu
$course = [];
$stmt_course = mysqli_prepare($konek, "SELECT * FROM tb_course WHERE id = ?");
if ($stmt_course) {
    mysqli_stmt_bind_param($stmt_course, "i", $course_id);
    mysqli_stmt_execute($stmt_course);
    $course_result = mysqli_stmt_get_result($stmt_course);
    $course = mysqli_fetch_assoc($course_result);
    mysqli_stmt_close($stmt_course);
}

// Jika course tidak ditemukan
if (!$course) {
    header("Location: dashboardMahasiswa.php?error=course_not_found");
    exit;
}

// Ambil data tugas
$list_tugas = [];
$stmt_tugas = mysqli_prepare($konek, "SELECT * FROM tb_tugas WHERE course_id = ? ORDER BY deadline DESC");
if ($stmt_tugas) {
    mysqli_stmt_bind_param($stmt_tugas, "i", $course_id);
    mysqli_stmt_execute($stmt_tugas);
    $result = mysqli_stmt_get_result($stmt_tugas);
    while ($row = mysqli_fetch_assoc($result)) {
        $list_tugas[] = $row;
    }
    mysqli_stmt_close($stmt_tugas);
}

// Ambil materi yang dikelompokkan per pertemuan
$materi_by_section = [];
$total_materi = 0;
$stmt_materi = mysqli_prepare($konek, "SELECT * FROM tb_materi WHERE course_id = ? ORDER BY pertemuan ASC, created_at DESC");
if ($stmt_materi) {
    mysqli_stmt_bind_param($stmt_materi, "i", $course_id);
    mysqli_stmt_execute($stmt_materi);
    $materi_query_result = mysqli_stmt_get_result($stmt_materi);

    while ($m = mysqli_fetch_assoc($materi_query_result)) {
        $materi_by_section[$m['pertemuan']][] = $m;
        $total_materi++;
    }
    mysqli_stmt_close($stmt_materi);
} else {
    die("Prepared statement untuk materi gagal: " . mysqli_error($konek));
}

$daftar_tugas = [];
if (!empty($list_tugas)) {
    foreach ($list_tugas as $tugas_item) {
        $tugas_id = $tugas_item['id'];
        $sudahUpload = false;

        $stmt_check = mysqli_prepare($konek, "SELECT COUNT(*) as count FROM tb_pengumpulan WHERE mahasiswa_username = ? AND tugas_id = ?");
        if ($stmt_check) {
            mysqli_stmt_bind_param($stmt_check, "si", $username, $tugas_id);
            mysqli_stmt_execute($stmt_check);
            $check_result = mysqli_stmt_get_result($stmt_check);
            $check_data = mysqli_fetch_assoc($check_result);
            $sudahUpload = ($check_data['count'] > 0);
            mysqli_stmt_close($stmt_check);
        }

        // Tambahkan ke array daftar_tugas
        $daftar_tugas[] = [
            'data' => $tugas_item,
            'sudahUpload' => $sudahUpload
        ];
    }
}

if (isset($_POST['upload_tugas'])) {
    $tugas_id = filter_input(INPUT_POST, 'tugas_id', FILTER_VALIDATE_INT);
    $tanggal_kumpul = date('Y-m-d H:i:s');

    if (!$tugas_id || !isset($_FILES['file_tugas'])) {
        echo "<script>alert('Terjadi kesalahan. Tugas ID tidak valid atau file belum dipilih.');</script>";
    } else {
        $allowed_types = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];
        $max_file_size = 5 * 1024 * 1024; // 5 MB
        $target_dir = "../assets/tugas/";

        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $jumlahFile = count($_FILES['file_tugas']['name']);
        $berhasilUpload = false;

        for ($i = 0; $i < $jumlahFile; $i++) {
            $file_name = basename($_FILES["file_tugas"]["name"][$i]);
            $file_tmp = $_FILES["file_tugas"]["tmp_name"][$i];
            $file_size = $_FILES["file_tugas"]["size"][$i];
            $file_error = $_FILES["file_tugas"]["error"][$i];
            $file_type = mime_content_type($file_tmp);

            if ($file_error !== UPLOAD_ERR_OK) {
                continue;
            }

            if (!in_array($file_type, $allowed_types)) {
                echo "<script>alert('Jenis file tidak diizinkan: $file_name');</script>";
                continue;
            }

            if ($file_size > $max_file_size) {
                echo "<script>alert('Ukuran file terlalu besar: $file_name');</script>";
                continue;
            }

            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_file_name = uniqid($username . '_tugas_') . '.' . $file_extension;
            $file_path = $target_dir . $unique_file_name;
            $file_db_path = $unique_file_name;

            if (move_uploaded_file($file_tmp, $file_path)) {
                // Cek apakah sudah ada pengumpulan sebelumnya
                $stmt_check_existing = mysqli_prepare($konek, "SELECT id FROM tb_pengumpulan WHERE mahasiswa_username = ? AND tugas_id = ?");
                mysqli_stmt_bind_param($stmt_check_existing, "si", $username, $tugas_id);
                mysqli_stmt_execute($stmt_check_existing);
                $existing_result = mysqli_stmt_get_result($stmt_check_existing);

                if (mysqli_num_rows($existing_result) > 0) {
                    // Update file terakhir saja
                    $stmt_update = mysqli_prepare($konek, "UPDATE tb_pengumpulan SET file_path = ?, tanggal_kumpul = ? WHERE mahasiswa_username = ? AND tugas_id = ?");
                    mysqli_stmt_bind_param($stmt_update, "sssi", $file_db_path, $tanggal_kumpul, $username, $tugas_id);
                    mysqli_stmt_execute($stmt_update);
                    mysqli_stmt_close($stmt_update);
                } else {
                    // Insert baru
                    $stmt_insert = mysqli_prepare($konek, "INSERT INTO tb_pengumpulan (mahasiswa_username, tugas_id, file_path, tanggal_kumpul) VALUES (?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt_insert, "siss", $username, $tugas_id, $file_db_path, $tanggal_kumpul);
                    mysqli_stmt_execute($stmt_insert);
                    mysqli_stmt_close($stmt_insert);
                }
                mysqli_stmt_close($stmt_check_existing);

                $berhasilUpload = true;
            }
        }

        if ($berhasilUpload) {
            echo "<script>alert('Tugas berhasil diunggah!'); window.location.href='materi.php?course_id=$course_id';</script>";
        } else {
            echo "<script>alert('Tidak ada file yang berhasil diunggah.');</script>";
        }
    }
}

if (isset($_POST['hapus_tugas'])) {
    $tugas_id = filter_input(INPUT_POST, 'tugas_id', FILTER_VALIDATE_INT);

    if ($tugas_id) {
        // Validasi deadline
        $stmt_deadline = mysqli_prepare($konek, "SELECT deadline FROM tb_tugas WHERE id = ?");
        mysqli_stmt_bind_param($stmt_deadline, "i", $tugas_id);
        mysqli_stmt_execute($stmt_deadline);
        $result_deadline = mysqli_stmt_get_result($stmt_deadline);
        $data = mysqli_fetch_assoc($result_deadline);
        mysqli_stmt_close($stmt_deadline);

        $now = date('Y-m-d H:i:s');
        if (!$data || $now > $data['deadline']) {
            echo "<script>alert('Batas waktu penghapusan tugas telah lewat!');</script>";
            return;
        }

        // Ambil data file yang akan dihapus
        $stmt_get_file = mysqli_prepare($konek, "SELECT file_path FROM tb_pengumpulan WHERE mahasiswa_username = ? AND tugas_id = ?");
        if ($stmt_get_file) {
            mysqli_stmt_bind_param($stmt_get_file, "si", $username, $tugas_id);
            mysqli_stmt_execute($stmt_get_file);
            $file_result = mysqli_stmt_get_result($stmt_get_file);
            $file_data = mysqli_fetch_assoc($file_result);

            if ($file_data) {
                $file_path = "../assets/tugas/" . $file_data['file_path'];

                if (file_exists($file_path)) {
                    unlink($file_path);
                }

                $stmt_delete = mysqli_prepare($konek, "DELETE FROM tb_pengumpulan WHERE mahasiswa_username = ? AND tugas_id = ?");
                if ($stmt_delete) {
                    mysqli_stmt_bind_param($stmt_delete, "si", $username, $tugas_id);
                    if (mysqli_stmt_execute($stmt_delete)) {
                        echo "<script>alert('Tugas berhasil dihapus!'); window.location.href='materi.php?course_id=$course_id';</script>";
                    } else {
                        echo "<script>alert('Gagal menghapus tugas dari database.');</script>";
                    }
                    mysqli_stmt_close($stmt_delete);
                }
            }
            mysqli_stmt_close($stmt_get_file);
        }
    }
}

// Fungsi untuk menentukan icon file
function getFileIcon(string $file_path): string
{
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf':
            return 'bi-file-earmark-pdf text-danger';
        case 'doc':
        case 'docx':
            return 'bi-file-earmark-word text-primary';
        case 'ppt':
        case 'pptx':
            return 'bi-file-earmark-ppt text-warning';
        case 'xls':
        case 'xlsx':
            return 'bi-file-earmark-excel text-success';
        case 'mp4':
        case 'avi':
        case 'mkv':
        case 'mov':
            return 'bi-play-circle text-info';
        case 'mp3':
        case 'wav':
        case 'aac':
            return 'bi-music-note text-purple';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
        case 'webp':
            return 'bi-image text-success';
        case 'zip':
        case 'rar':
        case '7z':
            return 'bi-file-earmark-zip text-muted';
        default:
            return 'bi-file-earmark text-secondary';
    }
}

// Fungsi untuk format ukuran file
function formatFileSize(int $bytes): string
{
    if ($bytes <= 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// PERBAIKAN 5: Logika absensi yang diperbaiki
$latest_absensi_id = 0;
if ($course_id > 0 && isset($_SESSION['username'])) {
    $stmt_absensi = mysqli_prepare(
        $konek,
        "SELECT a.id 
         FROM tb_absensi a
         LEFT JOIN tb_detail_absensi da ON a.id = da.absensi_id AND da.mahasiswa_username = ?
         WHERE a.course_id = ? AND da.id IS NULL
         ORDER BY a.tanggal DESC, a.pertemuan_ke DESC
         LIMIT 1"
    );

    if ($stmt_absensi) {
        mysqli_stmt_bind_param($stmt_absensi, "si", $username, $course_id);
        mysqli_stmt_execute($stmt_absensi);
        $absensi_result = mysqli_stmt_get_result($stmt_absensi);

        if (mysqli_num_rows($absensi_result) > 0) {
            $absensi_data = mysqli_fetch_assoc($absensi_result);
            $latest_absensi_id = $absensi_data['id'];
        }
        mysqli_stmt_close($stmt_absensi);
    }
}

// Menutup koneksi database
mysqli_close($konek);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materi Mata Kuliah - Mahasiswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            /* Indigo */
            --primary-dark: #4338ca;
            --secondary-color: #6c757d;
            /* Gray */
            --background-light: #f9fafb;
            --card-background: #ffffff;
            --border-color: #e0e0e0;
            --shadow-light: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-medium: 0 4px 12px rgba(0, 0, 0, 0.08);
            --text-dark: #343a40;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-light);
            margin: 0;
            color: var(--text-dark);
        }

        .wrapper {
            display: flex;
            transition: all 0.3s ease;
        }

        .sidebar {
            width: 250px;
            background-color: var(--primary-color);
            color: white;
            height: 100vh;
            position: fixed;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            z-index: 1000;
        }

        .sidebar .logo {
            font-size: 1.5rem;
            font-weight: 700;
            padding: 25px 20px;
            text-align: center;
            background-color: var(--primary-dark);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            letter-spacing: 0.5px;
        }

        .sidebar .logo i {
            margin-right: 8px;
        }

        .sidebar .nav-link-list {
            /* Gunakan class custom untuk daftar link */
            flex-grow: 1;
            /* Agar menu mengisi sisa ruang */
            padding-top: 10px;
        }

        .sidebar .nav-link-list a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .sidebar .nav-link-list a i {
            font-size: 1.3rem;
            width: 25px;
            /* Pastikan lebar ikon konsisten */
            text-align: center;
        }

        .sidebar .nav-link-list a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: white;
        }

        .sidebar .nav-link-list a.active {
            background-color: var(--primary-dark);
            color: white;
            border-left-color: white;
            font-weight: 600;
        }

        .sidebar .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .content {
            margin-left: 250px;
            width: calc(100% - 250px);
            transition: all 0.3s ease;
            padding-top: 80px;
            /* Padding untuk mengompensasi navbar fixed */
            padding-right: 20px;
            /* Padding di sisi kanan konten utama */
            padding-bottom: 20px;
            /* Padding di bagian bawah konten utama */
        }

        .collapsed .sidebar {
            margin-left: -250px;
        }

        .collapsed .content {
            margin-left: 0;
            width: 100%;
        }

        .navbar {
            background-color: var(--card-background);
            padding: 15px 25px;
            box-shadow: var(--shadow-light);
            position: fixed;
            top: 0;
            left: 250px;
            /* Sesuaikan dengan lebar sidebar */
            width: calc(100% - 250px);
            /* Sesuaikan dengan lebar sidebar */
            z-index: 1030;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .collapsed .navbar {
            left: 0;
            width: 100%;
        }

        .navbar .btn-outline-secondary {
            border: none;
            background-color: #f0f2f5;
            color: var(--primary-color);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 1.1rem;
            transition: background-color 0.2s ease;
        }

        .navbar .btn-outline-secondary:hover {
            background-color: #e0e2e5;
        }

        .navbar h5 {
            color: var(--text-dark);
            margin: 0;
            font-weight: 600;
            font-size: 1.3rem;
            margin-left: 15px;
        }

        .navbar .icon-group i {
            color: var(--secondary-color);
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .navbar .icon-group i:hover {
            color: var(--primary-color);
        }

        .btn-sm {
            font-size: 0.875rem;
            padding: 6px 12px;
            border-radius: 5px;
            line-height: 1.5;
        }

        .input-group-sm>.form-control,
        .input-group-sm>.btn {
            font-size: 0.875rem;
            padding: 6px 12px;
        }


        .course-header {
            background-image: linear-gradient(135deg, #163cacff 0%, #3147a8ff 100%);
            /* Gradien yang lebih menarik */
            padding: 30px 40px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-medium);
            color: white;
            /* Teks putih di atas latar belakang gradien */
            position: relative;
            overflow: hidden;
        }

        .course-header::before {
            content: '';
            position: absolute;
            top: -20px;
            left: -20px;
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            filter: blur(30px);
        }

        .course-header h4 {
            color: white;
            margin-bottom: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            font-size: 1.8rem;
        }

        .course-header h4 i {
            margin-right: 15px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 2.2rem;
        }

        .course-info {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .course-info span {
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .course-info span i {
            margin-right: 10px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.2rem;
        }

        .section-box {
            background-color: var(--card-background);
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }

        .section-box h5 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f2f5;
            font-size: 1.4rem;
        }

        .section-box h5 i {
            margin-right: 12px;
            font-size: 1.6rem;
            color: var(--primary-dark);
        }

        .material-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            margin-bottom: 12px;
            background-color: #fdfefe;
            transition: all 0.3s ease-in-out;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
            gap: 15px;
            /* Jarak antar elemen */
        }

        .material-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
            border-color: #cdd4e0;
        }

        .material-item .material-icon {
            font-size: 2.5rem;
            /* Ukuran ikon lebih besar */
            margin-right: 15px;
            color: var(--secondary-color);
            flex-shrink: 0;
            /* Pastikan ikon tidak menyusut */
        }

        /* Warna kustom untuk icon file */
        .material-item .material-icon.bi-file-earmark-pdf {
            color: #dc3545;
        }

        .material-item .material-icon.bi-file-earmark-word {
            color: #007bff;
        }

        .material-item .material-icon.bi-file-earmark-ppt {
            color: #ffc107;
        }

        .material-item .material-icon.bi-file-earmark-excel {
            color: #28a745;
        }

        .material-item .material-icon.bi-play-circle {
            color: #17a2b8;
        }

        .material-item .material-icon.bi-music-note {
            color: #6f42c1;
        }

        .material-item .material-icon.bi-image {
            color: #20c997;
        }

        .material-item .material-icon.bi-file-earmark-zip {
            color: #6c757d;
        }

        .material-item .material-icon.bi-file-earmark {
            color: #adb5bd;
        }


        .material-info {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .material-info h6 {
            margin-bottom: 3px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.1rem;
        }

        .material-info small {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .material-actions {
            display: flex;
            color: var(--primary-dark);
            gap: 10px;
            flex-shrink: 0;
            /* Pastikan tombol tidak menyusut */
        }

        .btn-action {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .btn-view {
            background-color: #e3f2fd;
            /* Light blue */
            color: #2196f3;
            /* Blue */
            border: 1px solid #bbdefb;
        }

        .btn-view:hover {
            background-color: #bbdefb;
            color: #1976d2;
        }

        .btn-download {
            background-color: #e8f5e9;
            /* Light green */
            color: #4caf50;
            /* Green */
            border: 1px solid #c8e6c9;
        }

        .btn-download:hover {
            background-color: #c8e6c9;
            color: #388e3c;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            background-color: var(--background-light);
            border-radius: 12px;
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

        /* Offcanvas (Right Panel) styling */
        .offcanvas {
            background-color: var(--card-background);
            box-shadow: -4px 0 15px rgba(0, 0, 0, 0.1);
        }

        .offcanvas-header {
            border-bottom: 1px solid var(--border-color);
            padding: 20px;
        }

        .offcanvas-title {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.25rem;
        }

        .offcanvas-body {
            padding: 20px;
        }

        /* Responsiveness */
        @media (max-width: 992px) {
            .sidebar {
                margin-left: -250px;
                position: fixed;
                /* Penting untuk mobile agar tidak mengganggu layout */
                height: 100vh;
                top: 0;
            }

            .content {
                margin-left: 0;
                width: 100%;
                padding-left: 20px;
                /* Tambahkan padding di kiri untuk konten */
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
            }

            .collapsed .navbar {
                left: 250px;
                width: calc(100% - 250px);
            }
        }

        @media (max-width: 768px) {
            .navbar h5 {
                font-size: 1.1rem;
                margin-left: 10px;
            }

            .navbar .btn-outline-secondary {
                padding: 6px 10px;
                font-size: 1rem;
            }

            .course-header {
                padding: 25px 25px;
            }

            .course-header h4 {
                font-size: 1.5rem;
            }

            .course-header h4 i {
                font-size: 1.8rem;
            }

            .course-info {
                flex-direction: column;
                gap: 15px;
            }

            .section-box h5 {
                font-size: 1.2rem;
            }

            .section-box h5 i {
                font-size: 1.4rem;
            }

            .material-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 15px;
            }

            .material-item .material-icon {
                font-size: 2rem;
                margin-bottom: 5px;
                /* Tambahkan sedikit jarak bawah ikon saat mobile */
            }

            .material-info {
                width: 100%;
                /* Pastikan info memenuhi lebar */
            }

            .material-actions {
                width: 100%;
                justify-content: flex-end;
                /* Pindahkan tombol ke kanan */
            }

            .btn-action {
                padding: 8px 10px;
                font-size: 0.85rem;
            }
        }

        @media (max-width: 576px) {
            .navbar .icon-group {
                gap: 15px;
            }

            .navbar .icon-group i {
                font-size: 1.5rem;
            }

            .sidebar .logo {
                font-size: 1.2rem;
                padding: 15px;
            }

            .sidebar .nav-link-list a {
                padding: 12px 15px;
                font-size: 0.95rem;
            }

            .course-header h4 {
                font-size: 1.3rem;
                margin-bottom: 10px;
            }

            .course-header h4 i {
                font-size: 1.5rem;
            }

            .course-info span {
                font-size: 0.9rem;
            }

            .section-box {
                padding: 15px 20px;
            }

            .section-box h5 {
                font-size: 1.1rem;
            }

            .section-box h5 i {
                font-size: 1.3rem;
            }

            .material-item {
                padding: 12px;
            }

            .material-info h6 {
                font-size: 1rem;
            }

            .material-info small {
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper" id="wrapper">
        <div class="sidebar">
            <div class="logo">
                <i class="bi bi-book-half"></i> Akademik
            </div>
            <div class="nav-link-list">
                <a href="dasboardMahasiswa.php"> <i class="bi bi-house-door-fill"></i> Dashboard</a>
                <a href="materi.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'materi.php' || (isset($_GET['course_id']) && basename($_SERVER['PHP_SELF']) == 'materi.php')) ? 'active' : ''; ?>">
                    <i class="bi bi-journal-text"></i> Materi
                </a>
                <a href="tugas.php"><i class="bi bi-pencil-square"></i> Tugas</a>
                <a href="isi_absensi.php"><i class="bi bi-calendar-check"></i> Absensi</a>
                <a href="../logout.php" onclick="return confirm('Apakah Anda yakin ingin keluar?')">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout</a>
            </div>
            <div class="sidebar-footer">
            </div>
        </div>

        <div class="content">
            <nav class="navbar">
                <div class="d-flex align-items-center">
                    <button class="btn btn-outline-secondary fs-6 me-3" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
                    <h5 class="m-0">Materi Mata Kuliah</h5>
                </div>
                <div class="d-flex align-items-center gap-3 icon-group">
                    <span class="me-2 fs-3"><i class="bi bi-person-circle"></i></span>
                    <i class="bi bi-bell fs-4" data-bs-toggle="offcanvas" data-bs-target="#rightPanel"></i>
                    <i class="bi bi-gear fs-4"></i>
                </div>
            </nav>

            <div class="container mt-4">
                <div class="row">
                    <div class="col-md-12">
                        <div class="course-header">
                            <h4><i class="bi bi-book-fill"></i> <?= htmlspecialchars($course['kode_mk'] ?? 'N/A') ?> - <?= htmlspecialchars($course['nama_mk'] ?? 'Mata Kuliah Tidak Ditemukan') ?></h4>
                            <div class="course-info">
                                <span><i class="bi bi-calendar3"></i> Semester <?= htmlspecialchars($course['semester'] ?? 'N/A') ?></span>
                                <span><i class="bi bi-person"></i> Dosen: <?= htmlspecialchars($course['dosen'] ?? 'N/A') ?></span>
                                <span><i class="bi bi-clock-history"></i> SKS: <?= htmlspecialchars($course['sks'] ?? 'N/A') ?></span>
                                <span><i class="bi bi-files"></i> Total Materi: <?= $total_materi ?></span>
                            </div>
                        </div>

                        <div class="section-box mb-4" id="absensi-section">
                            <h5><i class="bi bi-folder-fill"></i> Umum</h5>
                            <div class="material-item">
                                <i class="bi bi-calendar-check material-icon text-success"></i>
                                <div class="material-info">
                                    <h6>Daftar Kehadiran</h6>
                                    <small>Lihat dan kelola catatan kehadiran Anda untuk mata kuliah ini.</small>
                                </div>
                                <div class="material-actions">
                                    <?php if ($latest_absensi_id > 0): ?>
                                        <a href="isi_absensi.php?absensi_id=<?= $latest_absensi_id ?>&course_id=<?= $course_id ?>" class="btn btn-primary btn-sm">
                                            Isi Absensi
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary btn-sm" disabled>
                                            Belum ada absensi
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($materi_by_section)) : ?>
                            <?php foreach ($materi_by_section as $pertemuan_ke => $materis) : ?>
                                <div class="section-box mb-4">
                                    <h5><i class="bi bi-folder-fill"></i> Pertemuan ke-<?= htmlspecialchars($pertemuan_ke) ?></h5>
                                    <?php foreach ($materis as $materi) : ?>
                                        <div class="material-item">
                                            <i class="<?= getFileIcon($materi['file_path']) ?> material-icon"></i>
                                            <div class="material-info">
                                                <h6><?= htmlspecialchars($materi['judul']) ?></h6>
                                                <small>Diunggah pada: <?= date('d M Y, H:i', strtotime($materi['created_at'])) ?>
                                                    <?php if ($materi['file_path']) : ?>
                                                        (<?= formatFileSize(filesize("../assets/materi/" . $materi['file_path'])) ?>)
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div class="material-actions">
                                                <?php if ($materi['file_path']) : ?>
                                                    <a href="../assets/materi/<?= htmlspecialchars($materi['file_path']) ?>" download class="btn btn-download btn-action">
                                                        <i class="bi bi-download"></i> Unduh
                                                    </a>
                                                <?php else : ?>
                                                    <span class="text-muted">Tidak Ada File</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <div class="empty-state">
                                <i class="bi bi-folder-open"></i>
                                <h5>Belum Ada Materi</h5>
                                <p>Dosen belum mengunggah materi untuk mata kuliah ini.</p>
                            </div>
                        <?php endif; ?>

                        <div class="section-box mb-4" id="tugas-section">
                            <h5><i class="bi bi-journal-check"></i> Tugas</h5>

                            <?php if (!empty($daftar_tugas)) : ?>
                                <?php foreach ($daftar_tugas as $tugas_entry) : ?>
                                    <?php
                                    $tugas = $tugas_entry['data'];
                                    $sudahUpload = $tugas_entry['sudahUpload'];
                                    $deadlineTimestamp = strtotime($tugas['deadline']);
                                    ?>

                                    <div class="material-item">
                                        <i class="bi bi-journal-check material-icon text-info"></i>
                                        <div class="material-info">
                                            <h6><strong><?= htmlspecialchars($tugas['judul']) ?></strong></h6>
                                            <p class="mb-1">Deadline: <?= date('d M Y, H:i', $deadlineTimestamp) ?></p>
                                            <p class="mb-1"><strong>Instruksi:</strong> <?= nl2br(htmlspecialchars($tugas['deskripsi'])) ?></p>

                                            <!-- Status -->
                                            <?php if ($sudahUpload) : ?>
                                                <p class="text-success mt-1 mb-0">
                                                    <i class="bi bi-check-circle-fill"></i> <strong>Sudah Mengumpulkan</strong>
                                                </p>
                                            <?php else : ?>
                                                <p class="text-warning mt-1 mb-0">
                                                    <i class="bi bi-exclamation-triangle-fill"></i> <strong>Belum Mengumpulkan</strong>
                                                </p>
                                            <?php endif; ?>

                                            <!-- Tampilkan file tugas jika ada -->
                                            <?php if (!empty($tugas['file_path'])) : ?>
                                                <p class="mt-2">
                                                    <a href="../assets/tugas/<?= htmlspecialchars($tugas['file_path']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-eye"></i> Lihat File Tugas
                                                    </a>
                                                </p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Form Upload atau Upload Ulang -->
                                        <div class="material-actions mt-2">
                                            <form method="POST" action="materi.php?course_id=<?= $course_id ?>" enctype="multipart/form-data" class="mb-2">
                                                <input type="hidden" name="tugas_id" value="<?= $tugas['id'] ?>">
                                                <div class="input-group input-group-sm">
                                                    <input type="file" name="file_tugas[]" class="form-control" multiple required>
                                                    <button type="submit" name="upload_tugas" class="btn btn-success">
                                                        <i class="bi bi-upload"></i>
                                                        <?= $sudahUpload ? 'Unggah Ulang' : 'Unggah Tugas' ?>
                                                    </button>
                                                </div>
                                                <small class="form-text text-muted">Maks. ukuran file 10MB.</small>
                                            </form>

                                            <!-- Tombol hapus jika sudah upload dan sebelum deadline -->
                                            <?php if ($sudahUpload && $deadlineTimestamp > time()) : ?>
                                                <form method="POST" action="materi.php?course_id=<?= $course_id ?>" onsubmit="return confirm('Yakin ingin menghapus tugas yang sudah dikumpulkan?');">
                                                    <input type="hidden" name="tugas_id" value="<?= $tugas['id'] ?>">
                                                    <button class="btn btn-danger btn-sm" type="submit" name="hapus_tugas">
                                                        <i class="bi bi-trash"></i> Hapus Tugas
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                            <?php else : ?>
                                <div class="material-item">
                                    <i class="bi bi-journal-x material-icon text-secondary"></i>
                                    <div class="material-info">
                                        <h6>Belum Ada Tugas</h6>
                                        <small>Dosen belum mengunggah tugas.</small>
                                    </div>
                                    <div class="material-actions">
                                        <button class="btn btn-outline-secondary btn-sm" disabled>Tidak Ada Tugas</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <div class="modal fade" id="uploadTugasModal" tabindex="-1" aria-labelledby="uploadTugasModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadTugasModalLabel">Unggah Tugas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="materi.php?course_id=<?= $course_id ?>" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="tugas_id" id="modalTugasId">
                        <div class="mb-3">
                            <label for="file_tugas" class="form-label">Pilih File Tugas (PDF, Word, Excel, PowerPoint, maks 5MB)</label>
                            <input class="form-control" type="file" id="file_tugas" name="file_tugas" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="upload_tugas" class="btn btn-primary">Unggah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="rightPanel" aria-labelledby="offcanvasRightLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="offcanvasRightLabel"><i class="bi bi-bell me-2"></i>Notifikasi</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <p>Belum ada notifikasi baru.</p>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('wrapper').classList.toggle('collapsed');
        }

        // Ambil ID tugas saat modal dibuka
        var uploadTugasModal = document.getElementById('uploadTugasModal');
        uploadTugasModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var tugasId = button.getAttribute('data-tugas-id'); // Extract info from data-bs-* attributes
            var modalTugasId = uploadTugasModal.querySelector('#modalTugasId');
            modalTugasId.value = tugasId;
        });
    </script>
</body>

</html>