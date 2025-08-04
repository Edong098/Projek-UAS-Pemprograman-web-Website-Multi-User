<?php
session_start();
include "../dbKonek.php"; // Pastikan path ini benar

// Cek apakah user sudah login dan role-nya adalah dosen
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'dosen') {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];
$course_id = $_GET['course_id'] ?? 0;
$pesan = "";
$pesan_tipe = ""; // Untuk menentukan alert success/danger

// Ambil data course untuk ditampilkan di halaman
$course_stmt = mysqli_prepare($konek, "SELECT nama_mk, kode_mk FROM tb_course WHERE id = ? AND dosen = ?");
mysqli_stmt_bind_param($course_stmt, "is", $course_id, $username);
mysqli_stmt_execute($course_stmt);
$course_result = mysqli_stmt_get_result($course_stmt);
$course = mysqli_fetch_assoc($course_result);

if (!$course) {
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><title>Akses Ditolak</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'></head><body><div class='container mt-5'><div class='alert alert-danger' role='alert'>Mata kuliah tidak ditemukan atau Anda tidak memiliki akses. <a href='dashboardDosen.php' class='alert-link'>Kembali ke Dashboard</a></div></div></body></html>";
    exit;
}

// Handle Tambah Tugas
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah') {
    $judul = trim($_POST['judul']);
    $deskripsi = trim($_POST['deskripsi']);
    $deadline = trim($_POST['deadline']);
    $file_tugas_path = NULL; // Default to NULL

    // Validasi input
    if (empty($judul) || empty($deskripsi) || empty($deadline)) {
        $pesan = "Judul, deskripsi, dan deadline tidak boleh kosong.";
        $pesan_tipe = "danger";
    } else {
        // Handle file upload for assignment
        if (isset($_FILES['file_tugas']) && $_FILES['file_tugas']['error'] == UPLOAD_ERR_OK) {
            $file_name = $_FILES['file_tugas']['name'];
            $tmp_name = $_FILES['file_tugas']['tmp_name'];
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_file_name = uniqid('tugas_') . '.' . $file_extension; // Tambahkan prefix
            $destination = "../assets/tugas/" . $unique_file_name;

            // Pastikan direktori tujuan ada
            if (!is_dir("../assets/tugas/")) {
                mkdir("../assets/tugas/", 0777, true); // Buat direktori jika tidak ada
            }

            if (move_uploaded_file($tmp_name, $destination)) {
                $file_tugas_path = $unique_file_name;
            } else {
                $pesan = "Gagal mengupload file tugas.";
                $pesan_tipe = "danger";
            }
        } else if ($_FILES['file_tugas']['error'] != UPLOAD_ERR_NO_FILE) {
            $pesan = "Terjadi kesalahan saat upload file: " . $_FILES['file_tugas']['error'];
            $pesan_tipe = "danger";
        }

        if ($pesan_tipe != "danger") { // Lanjutkan jika tidak ada error upload
            // Gunakan prepared statement untuk INSERT
            $query = "INSERT INTO tb_tugas (course_id, judul, deskripsi, deadline, file_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($konek, $query);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "issss", $course_id, $judul, $deskripsi, $deadline, $file_tugas_path);
                if (mysqli_stmt_execute($stmt)) {
                    $pesan = "Tugas berhasil ditambahkan!";
                    $pesan_tipe = "success";
                } else {
                    $pesan = "Gagal menyimpan tugas ke database: " . mysqli_error($konek);
                    $pesan_tipe = "danger";
                }
                mysqli_stmt_close($stmt);
            } else {
                $pesan = "Gagal menyiapkan statement database: " . mysqli_error($konek);
                $pesan_tipe = "danger";
            }
        }
    }
}

// Handle Hapus Tugas
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['tugas_id'])) {
    $tugas_id_to_delete = $_GET['tugas_id'];

    // Pertama, ambil path file jika ada, dan pastikan tugas dimiliki dosen
    $stmt_get_file = mysqli_prepare($konek, "SELECT t.file_path FROM tb_tugas t JOIN tb_course c ON t.course_id = c.id WHERE t.id = ? AND c.dosen = ?");
    mysqli_stmt_bind_param($stmt_get_file, "is", $tugas_id_to_delete, $username);
    mysqli_stmt_execute($stmt_get_file);
    $result_get_file = mysqli_stmt_get_result($stmt_get_file);
    $tugas_data = mysqli_fetch_assoc($result_get_file);
    mysqli_stmt_close($stmt_get_file);

    if ($tugas_data) {
        // Hapus file fisik jika ada
        if (!empty($tugas_data['file_path'])) {
            $file_path_to_delete = "../assets/tugas/" . $tugas_data['file_path'];
            if (file_exists($file_path_to_delete)) {
                if (!unlink($file_path_to_delete)) {
                    $pesan = "Gagal menghapus file tugas fisik.";
                    $pesan_tipe = "danger";
                }
            }
        }

        // Hapus record dari database
        if ($pesan_tipe != "danger") { // Hanya hapus record jika file fisik berhasil dihapus atau tidak ada
            $query_delete = "DELETE FROM tb_tugas WHERE id = ?";
            $stmt_delete = mysqli_prepare($konek, $query_delete);
            mysqli_stmt_bind_param($stmt_delete, "i", $tugas_id_to_delete);

            if (mysqli_stmt_execute($stmt_delete)) {
                $pesan = "Tugas berhasil dihapus!";
                $pesan_tipe = "success";
            } else {
                $pesan = "Gagal menghapus tugas dari database: " . mysqli_error($konek);
                $pesan_tipe = "danger";
            }
            mysqli_stmt_close($stmt_delete);
        }
    } else {
        $pesan = "Tugas tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya.";
        $pesan_tipe = "danger";
    }
}

// Ambil daftar tugas untuk mata kuliah ini
$tugas_list = [];
$tugas_stmt = mysqli_prepare($konek, "SELECT id, judul, deskripsi, deadline, file_path, created_at FROM tb_tugas WHERE course_id = ? ORDER BY deadline ASC");
mysqli_stmt_bind_param($tugas_stmt, "i", $course_id);
mysqli_stmt_execute($tugas_stmt);
$tugas_result = mysqli_stmt_get_result($tugas_stmt);
while ($row = mysqli_fetch_assoc($tugas_result)) {
    $tugas_list[] = $row;
}
mysqli_stmt_close($tugas_stmt);

mysqli_close($konek);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tugas - <?= htmlspecialchars($course['nama_mk'] ?? 'Mata Kuliah'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --background-light: #f4f7f6;
            --card-background: #ffffff;
            --border-color: #e0e0e0;
            --shadow-light: 0 2px 4px rgba(0, 0, 0, 0.05);
            --text-dark: #212529;
            --text-muted: #6c757d;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-light);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: var(--primary-dark);
            padding: 15px 30px;
            box-shadow: var(--shadow-light);
            border-bottom: 1px solid var(--border-color);
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
        }

        .navbar-brand:hover {
            color: var(--border-color);
        }

        .container {
            flex-grow: 1;
            padding-top: 30px;
            padding-bottom: 30px;
            color: #ffffff;
            /* Warna teks putih */
        }


        .card {
            border-radius: 15px;
            border: 1px solid var(--border-color);
            background: var(--card-background);
            box-shadow: var(--shadow-light);
        }

        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-top-left-radius: 14px;
            border-top-right-radius: 14px;
            padding: 18px 25px;
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 25px;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid var(--border-color);
            padding: 10px 15px;
            font-size: 0.95rem;
            box-shadow: none;
            transition: all 0.2s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
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

        .list-group-item {
            /* ... properti yang sudah ada ... */
            align-items: flex-start;
            /* Tambahkan ini jika belum ada */
        }

        .list-group-item .btn-group {
            margin-left: 15px;
            flex-shrink: 0;
            margin-top: 10px;
            /* Menambahkan margin-top */
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
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="kelola_course.php?id=<?= $course_id ?>">
                <i class="bi bi-arrow-left-circle-fill"></i> Kembali
            </a>
            <span class="fw-bold text-white"><i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($username); ?></span>
        </div>
    </nav>

    <div class="container">
        <h3 class="mb-4 text-dark">
            <i class="bi bi-clipboard-check me-2"></i> Kelola Tugas untuk <?= htmlspecialchars($course['kode_mk'] ?? '') . ' - ' . htmlspecialchars($course['nama_mk'] ?? ''); ?>
        </h3>

        <?php if ($pesan) : ?>
            <div class="alert alert-<?= $pesan_tipe ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($pesan); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-plus-circle"></i> Buat Tugas Baru
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="tambah">
                    <div class="mb-3">
                        <label for="judul" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                        <input type="text" name="judul" id="judul" class="form-control" required placeholder="Contoh: Tugas Mandiri Bab 1">
                    </div>
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi Tugas <span class="text-danger">*</span></label>
                        <textarea name="deskripsi" id="deskripsi" class="form-control" rows="4" required placeholder="Jelaskan instruksi tugas di sini..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="deadline" class="form-label">Deadline Pengumpulan <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="deadline" id="deadline" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="file_tugas" class="form-label">File Lampiran Tugas (Opsional)</label>
                        <input type="file" name="file_tugas" id="file_tugas" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar,.jpg,.jpeg,.png">
                        <div class="form-text">Maksimal ukuran file: 5MB. Format yang didukung: PDF, DOCX, PPTX, ZIP, RAR, Gambar.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send-fill me-2"></i> Buat Tugas
                    </button>
                </form>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>