<?php
session_start();
include "../dbKonek.php"; // Pastikan path ini benar

// Cek apakah user sudah login dan role-nya adalah dosen
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'dosen') {
    $_SESSION['message'] = "Anda tidak memiliki akses ke halaman ini.";
    $_SESSION['message_type'] = "danger";
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];
$course_id = $_GET['course_id'] ?? 0;
// Inisialisasi pesan di sesi, agar bisa ditampilkan sebagai toast
// Jika ada pesan dari operasi sebelumnya (misal dari edit_materi.php), akan ditimpa di sini jika ada operasi baru
if (!isset($_SESSION['message'])) {
    $_SESSION['message'] = "";
    $_SESSION['message_type'] = "";
}

// Ambil data course untuk ditampilkan di halaman
$course = null;
$course_stmt = mysqli_prepare($konek, "SELECT nama_mk, kode_mk FROM tb_course WHERE id = ? AND dosen = ?");
if ($course_stmt) {
    mysqli_stmt_bind_param($course_stmt, "is", $course_id, $username);
    mysqli_stmt_execute($course_stmt);
    $course_result = mysqli_stmt_get_result($course_stmt);
    $course = mysqli_fetch_assoc($course_result);
    mysqli_stmt_close($course_stmt);
}

if (!$course) {
    $_SESSION['message'] = "Mata kuliah tidak ditemukan atau Anda tidak memiliki akses.";
    $_SESSION['message_type'] = "danger";
    header("Location: dashboardDosen.php"); // Redirect ke dashboard jika course tidak valid
    exit;
}

// Handle Tambah Materi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah') {
    $judul = trim($_POST['judul']);
    $jenis_materi = $_POST['jenis_materi']; // 'file' or 'text'
    $keterangan = trim($_POST['keterangan']);

    // Validasi judul tidak boleh kosong
    if (empty($judul)) {
        $_SESSION['message'] = "Judul materi tidak boleh kosong.";
        $_SESSION['message_type'] = "danger";
    } else {
        $file_path = null;
        $content = null;

        if ($jenis_materi == 'file') {
            if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == UPLOAD_ERR_OK) {
                $file_name = $_FILES['file_materi']['name'];
                $tmp_name = $_FILES['file_materi']['tmp_name'];
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $unique_file_name = uniqid('materi_') . '.' . $file_extension; // Tambahkan prefix unik
                $destination = "../assets/materi/" . $unique_file_name;

                // Pastikan direktori tujuan ada
                if (!is_dir("../assets/materi/")) {
                    mkdir("../assets/materi/", 0777, true);
                }

                if (move_uploaded_file($tmp_name, $destination)) {
                    $file_path = $unique_file_name;
                } else {
                    $_SESSION['message'] = "Gagal mengupload file materi.";
                    $_SESSION['message_type'] = "danger";
                }
            } else if ($_FILES['file_materi']['error'] != UPLOAD_ERR_NO_FILE) {
                $_SESSION['message'] = "Terjadi kesalahan saat upload file: " . $_FILES['file_materi']['error'];
                $_SESSION['message_type'] = "danger";
            } else {
                // Jika jenis_materi adalah 'file' tetapi tidak ada file yang diupload
                $_SESSION['message'] = "Silakan pilih file untuk diupload.";
                $_SESSION['message_type'] = "danger";
            }
        } elseif ($jenis_materi == 'text') {
            $content = $_POST['text_materi'];
            if (empty($content)) {
                $_SESSION['message'] = "Konten materi teks tidak boleh kosong.";
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Jenis materi tidak valid.";
            $_SESSION['message_type'] = "danger";
        }

        // Lanjutkan jika tidak ada error upload/validation
        if ($_SESSION['message_type'] != "danger") {
            $query = "INSERT INTO tb_materi (course_id, judul, keterangan, file_path, content, jenis_materi, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = mysqli_prepare($konek, $query);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "isssss", $course_id, $judul, $keterangan, $file_path, $content, $jenis_materi);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['message'] = "Materi berhasil ditambahkan!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Gagal menyimpan data materi ke database: " . mysqli_error($konek);
                    $_SESSION['message_type'] = "danger";
                }
                mysqli_stmt_close($stmt);
            } else {
                $_SESSION['message'] = "Gagal menyiapkan statement database: " . mysqli_error($konek);
                $_SESSION['message_type'] = "danger";
            }
        }
    }
    // Redirect untuk mencegah form resubmission dan menampilkan pesan sebagai toast
    header("Location: apload_materi.php?course_id=" . $course_id);
    exit;
}

// Handle Hapus Materi
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['materi_id'])) {
    $materi_id_to_delete = $_GET['materi_id'];

    // Pertama, ambil path file jika ada, dan pastikan materi dimiliki dosen
    $stmt_get_file = mysqli_prepare($konek, "SELECT m.file_path FROM tb_materi m JOIN tb_course c ON m.course_id = c.id WHERE m.id = ? AND c.dosen = ?");
    if ($stmt_get_file) {
        mysqli_stmt_bind_param($stmt_get_file, "is", $materi_id_to_delete, $username);
        mysqli_stmt_execute($stmt_get_file);
        $result_get_file = mysqli_stmt_get_result($stmt_get_file);
        $materi_data = mysqli_fetch_assoc($result_get_file);
        mysqli_stmt_close($stmt_get_file);

        if ($materi_data) {
            // Hapus file fisik jika ada
            if (!empty($materi_data['file_path'])) {
                $file_path_to_delete = "../assets/materi/" . $materi_data['file_path'];
                if (file_exists($file_path_to_delete)) {
                    if (!unlink($file_path_to_delete)) {
                        $_SESSION['message'] = "Gagal menghapus file materi fisik.";
                        $_SESSION['message_type'] = "danger";
                    }
                }
            }

            // Hapus record dari database hanya jika tidak ada error sebelumnya
            if ($_SESSION['message_type'] != "danger") {
                $query_delete = "DELETE FROM tb_materi WHERE id = ?";
                $stmt_delete = mysqli_prepare($konek, $query_delete);
                if ($stmt_delete) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $materi_id_to_delete);

                    if (mysqli_stmt_execute($stmt_delete)) {
                        $_SESSION['message'] = "Materi berhasil dihapus!";
                        $_SESSION['message_type'] = "success";
                    } else {
                        $_SESSION['message'] = "Gagal menghapus materi dari database: " . mysqli_error($konek);
                        $_SESSION['message_type'] = "danger";
                    }
                    mysqli_stmt_close($stmt_delete);
                } else {
                    $_SESSION['message'] = "Gagal menyiapkan statement database untuk penghapusan: " . mysqli_error($konek);
                    $_SESSION['message_type'] = "danger";
                }
            }
        } else {
            $_SESSION['message'] = "Materi tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya.";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Gagal menyiapkan statement database untuk mengambil data materi.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: apload_materi.php?course_id=" . $course_id); // Redirect untuk menampilkan toast
    exit;
}


// Ambil daftar materi untuk mata kuliah ini
$materi_list = [];
$materi_stmt = mysqli_prepare($konek, "SELECT id, judul, keterangan, file_path, content, jenis_materi, created_at FROM tb_materi WHERE course_id = ? ORDER BY created_at DESC");
if ($materi_stmt) {
    mysqli_stmt_bind_param($materi_stmt, "i", $course_id);
    mysqli_stmt_execute($materi_stmt);
    $materi_result = mysqli_stmt_get_result($materi_stmt);
    while ($row = mysqli_fetch_assoc($materi_result)) {
        $materi_list[] = $row;
    }
    mysqli_stmt_close($materi_stmt);
} else {
    $_SESSION['message'] = "Gagal mengambil daftar materi: " . mysqli_error($konek);
    $_SESSION['message_type'] = "danger";
}

mysqli_close($konek);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Materi - <?= htmlspecialchars($course['nama_mk'] ?? 'Mata Kuliah'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

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

        /* --- Navbar Styling --- */
        /* --- Navbar Styling --- */
        .navbar-custom {
            background-color: var(--primary-color);
            padding: 15px 30px;
            box-shadow: var(--shadow-light);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 1000;
            color: #ffffff;
            /* Warna teks navbar putih */
        }

        .navbar-brand-custom {
            font-weight: 600;
            color: #ffffff;
            /* Warna teks brand juga putih */
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .navbar-brand-custom:hover {
            color: #f1f1f1;
            /* Efek hover: tetap putih tapi sedikit lebih terang */
            transform: translateX(-3px);
        }

        .navbar-brand-custom i {
            font-size: 1.5rem;
            color: var(--background-light);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-dark);
            font-weight: 500;
        }

        .user-info i {
            font-size: 1.3rem;
            color: var(--primary-color);
        }

        .container {
            flex-grow: 1;
            padding-top: 30px;
            padding-bottom: 30px;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            border-radius: 10px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-1px);
        }

        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: #fff;
            /* Pastikan teks putih untuk kontras */
        }

        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-danger:hover {
            background-color: #bd2130;
            border-color: #b21f2d;
        }


        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        #fileUploadSection,
        #textEditorSection {
            border: 1px dashed var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
            background-color: #fcfcfc;
        }

        /* TinyMCE specific styling */
        .tox-tinymce {
            border-radius: 10px !important;
            border: 1px solid var(--border-color) !important;
            box-shadow: none !important;
        }

        .tox-editor-header {
            background-color: #f0f2f5 !important;
            border-bottom: 1px solid var(--border-color) !important;
            border-top-left-radius: 10px !important;
            border-top-right-radius: 10px !important;
        }

        .tox-menubar,
        .tox-toolbar-group {
            background-color: #f0f2f5 !important;
        }

        .tox-statusbar {
            border-top: 1px solid var(--border-color) !important;
            background-color: #f0f2f5 !important;
            border-bottom-left-radius: 10px !important;
            border-bottom-right-radius: 10px !important;
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

        @media (max-width: 767.98px) {
            .navbar-custom {
                padding: 15px 20px;
            }

            .navbar-brand-custom {
                font-size: 1rem;
            }

            .user-info {
                font-size: 0.9rem;
            }

            .user-info i {
                font-size: 1.1rem;
            }

            .container {
                padding-left: 15px;
                padding-right: 15px;
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
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <!-- Kiri: Tombol Kembali -->
            <a class="navbar-brand-custom" href="kelola_course.php?id=<?= $course_id ?>">
                <i class="bi bi-arrow-left-circle-fill"></i>
                <span class="d-none d-sm-inline">Kembali</span>
            </a>

            <!-- Kanan: Info Dosen -->
            <div class="d-flex align-items-center text-white">
                <i class="bi bi-person-circle me-2"></i>
                <span class="fw-bold"><?= htmlspecialchars($username); ?></span>
            </div>
        </div>
    </nav>


    <div class="container">
        <!-- <h3 class="mb-4 text-dark">
            <i class="bi bi-book me-2"></i> Kelola Materi untuk <?= htmlspecialchars($course['kode_mk'] ?? '') . ' - ' . htmlspecialchars($course['nama_mk'] ?? ''); ?>
        </h3> -->

        <div class="toast-container">
            <?php if (!empty($_SESSION['message'])) : ?>
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

        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-cloud-arrow-up"></i> Upload Materi Baru
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="tambah">
                    <div class="mb-3">
                        <label for="judul" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                        <input type="text" name="judul" id="judul" class="form-control" required placeholder="Contoh: Pengantar Algoritma">
                    </div>

                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan (Opsional)</label>
                        <textarea name="keterangan" id="keterangan" class="form-control" rows="3" placeholder="Tambahkan deskripsi singkat tentang materi ini..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Pilih Jenis Materi</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="jenis_materi" id="jenisMateriFile" value="file" checked>
                            <label class="form-check-label" for="jenisMateriFile">Upload File</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="jenis_materi" id="jenisMateriText" value="text">
                            <label class="form-check-label" for="jenisMateriText">Tulis Teks/Konten</label>
                        </div>
                    </div>

                    <div id="fileUploadSection" class="mb-3">
                        <label for="file_materi" class="form-label">Pilih File Materi</label>
                        <input type="file" name="file_materi" id="file_materi" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar,.mp4,.mp3">
                        <div class="form-text">Format yang didukung: PDF, DOCX, PPTX, ZIP, RAR, MP4, MP3. Maksimal ukuran file: 20MB (sesuaikan dengan `upload_max_filesize` di PHP.ini).</div>
                    </div>

                    <div id="textEditorSection" class="mb-3" style="display: none;">
                        <label for="text_materi" class="form-label">Konten Materi Teks</label>
                        <textarea name="text_materi" id="text_materi" class="form-control" rows="10"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-upload me-2"></i> Tambah Materi
                    </button>
                    <a href="kelola_course.php?id=<?= $course_id ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-2"></i> Batal
                    </a>
                </form>
            </div>
        </div>

        <!-- <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul"></i> Daftar Materi Terunggah
            </div>
            <div class="card-body">
                <?php if (!empty($materi_list)) : ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($materi_list as $m) : ?>
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
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group" role="group" aria-label="Aksi Materi">
                                    <a href="edit_materi.php?materi_id=<?= $m['id'] ?>&course_id=<?= $course_id ?>" class="btn btn-warning btn-sm text-white">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="apload_materi.php?action=hapus&materi_id=<?= $m['id']; ?>&course_id=<?= $course_id ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin menghapus materi ini? Ini juga akan menghapus file jika ada.')">
                                        <i class="bi bi-trash"></i> Hapus
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="empty-state">
                        <i class="bi bi-journal-x"></i>
                        <h6>Belum Ada Materi Terunggah</h6>
                        <p>Materi yang Anda tambahkan akan muncul di sini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div> -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const jenisMateriFile = document.getElementById('jenisMateriFile');
            const jenisMateriText = document.getElementById('jenisMateriText');
            const fileUploadSection = document.getElementById('fileUploadSection');
            const textEditorSection = document.getElementById('textEditorSection');
            const fileInput = document.getElementById('file_materi');
            const textInput = document.getElementById('text_materi');

            let editorInstance; // Untuk menyimpan instance editor TinyMCE

            function toggleMateriType() {
                if (jenisMateriFile.checked) {
                    fileUploadSection.style.display = 'block';
                    textEditorSection.style.display = 'none';
                    fileInput.required = true; // File input wajib jika jenis file
                    if (editorInstance) {
                        tinymce.get('text_materi').setContent(''); // Kosongkan konten TinyMCE saat beralih
                        editorInstance.destroy();
                        editorInstance = null;
                    }
                } else if (jenisMateriText.checked) {
                    fileUploadSection.style.display = 'none';
                    textEditorSection.style.display = 'block';
                    fileInput.required = false; // File input tidak wajib jika jenis teks
                    if (!editorInstance) {
                        tinymce.init({
                            selector: '#text_materi',
                            plugins: 'advlist autolink lists link image charmap print preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste code help wordcount',
                            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
                            height: 400,
                            menubar: false,
                            statusbar: false,
                            content_style: 'body { font-family: \'Poppins\', sans-serif; font-size:14px; }',
                            setup: function(editor) {
                                editorInstance = editor; // Simpan instance editor
                            }
                        });
                    }
                }
            }

            // Set state awal berdasarkan pilihan default (File)
            toggleMateriType();

            // Tambahkan event listener untuk radio button
            jenisMateriFile.addEventListener('change', toggleMateriType);
            jenisMateriText.addEventListener('change', toggleMateriType);

            // Tampilkan toast jika ada pesan dari sesi
            var toastElList = [].slice.call(document.querySelectorAll('.toast'))
            var toastList = toastElList.map(function(toastEl) {
                return new bootstrap.Toast(toastEl, {
                    autohide: true,
                    delay: 5000 // Toast akan hilang setelah 5 detik
                })
            })
            toastList.forEach(toast => toast.show());
        });
    </script>
</body>

</html>