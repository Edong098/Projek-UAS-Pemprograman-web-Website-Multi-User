<?php
session_start();
include "../dbKonek.php"; // Pastikan path ini benar

// Fungsi untuk mengatur pesan notifikasi (konsisten dengan kelola_course.php)
function set_message($message, $type)
{
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

// Cek apakah user sudah login dan role-nya adalah dosen
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'dosen') {
    set_message("Anda tidak memiliki akses ke halaman ini.", "danger");
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];
$course_id = $_GET['course_id'] ?? 0;
$materi_id = $_GET['materi_id'] ?? 0;

// Validasi parameter ID
$course_id = intval($course_id);
$materi_id = intval($materi_id);

if ($course_id === 0 || $materi_id === 0) {
    set_message("ID materi atau Course ID tidak valid.", "danger");
    header("Location: dasboardDosen.php"); // Atau halaman daftar course dosen
    exit;
}

// Ambil data course untuk ditampilkan di halaman
$course = null;
$course_stmt = mysqli_prepare($konek, "SELECT nama_mk, kode_mk FROM tb_course WHERE id = ? AND dosen = ?");
if (!$course_stmt) {
    set_message("Gagal menyiapkan statement course: " . mysqli_error($konek), "danger");
    header("Location: dasboardDosen.php");
    exit;
}
mysqli_stmt_bind_param($course_stmt, "is", $course_id, $username);
mysqli_stmt_execute($course_stmt);
$course_result = mysqli_stmt_get_result($course_stmt);
$course = mysqli_fetch_assoc($course_result);
mysqli_stmt_close($course_stmt);

if (!$course) {
    set_message("Mata kuliah tidak ditemukan atau Anda tidak memiliki akses.", "danger");
    header("Location: dasboardDosen.php");
    exit;
}

// Fetch existing materi data
$materi_data = null;
$stmt_fetch_materi = mysqli_prepare($konek, "SELECT tm.id, tm.judul, tm.keterangan, tm.file_path, tm.content, tm.jenis_materi FROM tb_materi tm JOIN tb_course tc ON tm.course_id = tc.id WHERE tm.id = ? AND tm.course_id = ? AND tc.dosen = ?");
if (!$stmt_fetch_materi) {
    set_message("Gagal menyiapkan statement untuk mengambil data materi: " . mysqli_error($konek), "danger");
    header("Location: manage_materi.php?course_id=$course_id"); // Kembali ke manage_materi
    exit;
}
mysqli_stmt_bind_param($stmt_fetch_materi, "iis", $materi_id, $course_id, $username);
mysqli_stmt_execute($stmt_fetch_materi);
$result_fetch_materi = mysqli_stmt_get_result($stmt_fetch_materi);
$materi_data = mysqli_fetch_assoc($result_fetch_materi);
mysqli_stmt_close($stmt_fetch_materi);

if (!$materi_data) {
    set_message("Materi tidak ditemukan atau Anda tidak memiliki izin untuk mengeditnya.", "danger");
    header("Location: manage_materi.php?course_id=$course_id"); // Kembali ke manage_materi
    exit;
}

// Handle Update Materi
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $judul = trim($_POST['judul']);
    $jenis_materi = trim($_POST['jenis_materi']);
    $keterangan = trim($_POST['keterangan']);

    $existing_file_path = $materi_data['file_path']; // Path file yang sudah ada di database
    $new_file_path = $existing_file_path; // Default, jika tidak ada file baru diunggah
    $new_content = $materi_data['content']; // Default, jika tidak ada perubahan konten teks

    $errors = [];

    // Validasi input
    if (empty($judul)) {
        $errors[] = "Judul materi tidak boleh kosong.";
    }
    if (empty($jenis_materi)) {
        $errors[] = "Jenis materi tidak boleh kosong.";
    } elseif (!in_array($jenis_materi, ['file', 'text'])) {
        $errors[] = "Jenis materi tidak valid.";
    }

    // Logika berdasarkan jenis materi yang dipilih di form
    if ($jenis_materi == 'file') {
        // Jika materi sebelumnya adalah 'text' dan diubah ke 'file', pastikan konten_text dihapus
        if ($materi_data['jenis_materi'] == 'text') {
            $new_content = null; // Hapus konten teks jika beralih ke file
        }

        // Proses Unggah File Baru (jika ada)
        if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../assets/materi/";

            // Pastikan direktori ada
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $errors[] = "Gagal membuat direktori unggahan file.";
                }
            }

            if (empty($errors)) { // Lanjutkan jika tidak ada error direktori
                $file_extension = pathinfo($_FILES['file_materi']['name'], PATHINFO_EXTENSION);
                $file_name_unik = uniqid('materi_') . '.' . $file_extension; // Nama file unik
                $target_path = $upload_dir . $file_name_unik;

                // Pindahkan file baru
                if (move_uploaded_file($_FILES['file_materi']['tmp_name'], $target_path)) {
                    // Hapus file lama hanya jika upload file baru sukses dan ada file lama
                    if (!empty($existing_file_path) && file_exists($upload_dir . $existing_file_path)) {
                        if (!unlink($upload_dir . $existing_file_path)) {
                            error_log("Gagal menghapus file lama materi: " . $upload_dir . $existing_file_path);
                            // Lanjutkan proses update database meskipun file lama gagal dihapus
                        }
                    }
                    $new_file_path = $file_name_unik;
                } else {
                    $errors[] = "Gagal mengunggah file baru materi.";
                }
            }
        } elseif (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Tangani error upload lainnya (misal: ukuran file terlalu besar)
            $errors[] = "Terjadi kesalahan saat mengunggah file: " . $_FILES['file_materi']['error'];
        } else {
            // Jika tidak ada file baru diunggah DAN jenis materi berubah dari text ke file, maka file_path harus NULL
            if ($materi_data['jenis_materi'] == 'text' && $jenis_materi == 'file') {
                $new_file_path = NULL; // Tidak ada file sebelumnya dan tidak ada file baru diunggah
            }
            // Jika tidak ada file baru diunggah dan jenis materi tetap 'file', $new_file_path tetap $existing_file_path (sudah diinisialisasi)
        }
    } elseif ($jenis_materi == 'text') {
        $new_content = $_POST['text_materi']; // Ambil konten dari TinyMCE
        if (empty($new_content)) {
            $errors[] = "Konten teks materi tidak boleh kosong.";
        }
        // Jika beralih dari 'file' ke 'text', hapus file lama
        if ($materi_data['jenis_materi'] == 'file' && !empty($existing_file_path) && file_exists("../assets/materi/" . $existing_file_path)) {
            if (!unlink("../assets/materi/" . $existing_file_path)) {
                error_log("Gagal menghapus file lama materi saat beralih ke teks: " . "../assets/materi/" . $existing_file_path);
            }
        }
        $new_file_path = null; // Set null karena ini materi teks
    }

    // Jika tidak ada error validasi atau upload file
    if (empty($errors)) {
        $query_update = "UPDATE tb_materi SET judul = ?, keterangan = ?, file_path = ?, content = ?, jenis_materi = ? WHERE id = ? AND course_id = ?";
        $stmt_update = mysqli_prepare($konek, $query_update);

        if (!$stmt_update) {
            set_message("Gagal menyiapkan statement update materi: " . mysqli_error($konek), "danger");
            // Tidak perlu redirect di sini, biarkan pesan error muncul di halaman
        } else {
            mysqli_stmt_bind_param($stmt_update, "sssssii", $judul, $keterangan, $new_file_path, $new_content, $jenis_materi, $materi_id, $course_id);

            if (mysqli_stmt_execute($stmt_update)) {
                set_message("Materi berhasil diperbarui.", "success");
                mysqli_stmt_close($stmt_update);
            } else {
                set_message("Gagal menyimpan perubahan materi: " . mysqli_error($konek), "danger");
                mysqli_stmt_close($stmt_update);
            }
        }
    } else {
        // Tampilkan semua error yang terkumpul
        set_message(implode("<br>", $errors), "danger");
    }

    // Jika ada error, data yang dikirim melalui POST mungkin berbeda dengan yang diambil dari DB
    // Untuk mengisi ulang form dengan data yang di-submit user (saat ada error), kita perlu memperbarui $materi_data
    // Namun, jika berhasil akan ada redirect, jadi ini hanya berlaku untuk kegagalan.
    // Kita bisa mengisi ulang $materi_data dengan nilai POST agar user tidak kehilangan inputnya.
    $materi_data['judul'] = $judul;
    $materi_data['keterangan'] = $keterangan;
    $materi_data['jenis_materi'] = $jenis_materi;
    $materi_data['file_path'] = $new_file_path; // Ini akan menjadi path baru jika sukses upload, atau tetap yang lama jika tidak ada upload baru/error
    $materi_data['content'] = $new_content; // Ini akan menjadi konten teks baru
}

mysqli_close($konek);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Materi - <?= htmlspecialchars($course['nama_mk'] ?? 'Mata Kuliah'); ?></title>
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
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
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

        .navbar {
            background-color: var(--card-background);
            padding: 15px 30px;
            box-shadow: var(--shadow-light);
            border-bottom: 1px solid var(--border-color);
        }

        .navbar-brand {
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
        }

        .navbar-brand:hover {
            color: var(--primary-dark);
        }

        .container {
            flex-grow: 1;
            /* Allow container to grow and push footer down */
            padding-top: 30px;
            padding-bottom: 30px;
        }

        .card {
            border-radius: 15px;
            border: 1px solid var(--border-color);
            background: var(--card-background);
            box-shadow: var(--shadow-light);
            margin-bottom: 25px;
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
            gap: 15px;
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
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
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
            background-color: #f8f8f8;
        }

        /* TinyMCE specific styling */
        .tox-tinymce {
            border-radius: 10px !important;
            border: 1px solid var(--border-color) !important;
        }

        .tox-editor-header {
            background-color: #f0f2f5 !important;
            border-bottom: 1px solid var(--border-color) !important;
        }

        .tox-menubar,
        .tox-toolbar-group {
            background-color: #f0f2f5 !important;
        }

        .tox-statusbar {
            border-top: 1px solid var(--border-color) !important;
            background-color: #f0f2f5 !important;
        }

        .footer {
            background-color: var(--primary-dark);
            color: white;
            text-align: center;
            padding: 20px 0;
            margin-top: auto;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Styles for Bootstrap Toasts (from kelola_course.php) */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1080;
            /* Higher than navbar */
        }

        .toast {
            min-width: 250px;
        }

        .toast-header .btn-close {
            margin-left: .5rem;
        }

        /* Responsive adjustments */
        @media (max-width: 767.98px) {
            .navbar {
                padding: 15px 20px;
            }

            .container {
                padding-left: 15px;
                padding-right: 15px;
            }

            .toast-container {
                top: 70px;
                /* Adjust for fixed navbar on small screens */
                right: 15px;
                left: 15px;
                max-width: none;
                width: auto;
            }
        }
    </style>
</head>

<body>
    <div class="toast-container">
        <?php if (isset($_SESSION['message'])) : ?>
            <div class="toast align-items-center text-white bg-<?= $_SESSION['message_type']; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <?= $_SESSION['message']; ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>
    </div>

    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="kelola_course.php?id=<?= $course_id ?>">
                <i class="bi bi-arrow-left-circle-fill"></i> Kembali ke Kelola Materi
            </a>
            <span class="fw-bold text-dark"><i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($username); ?></span>
        </div>
    </nav>

    <div class="container">
        <h3 class="mb-4 text-dark">
            <i class="bi bi-pencil-square me-2"></i> Edit Materi: <?= htmlspecialchars($materi_data['judul'] ?? ''); ?>
        </h3>

        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-pencil"></i> Form Edit Materi
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="judul" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                        <input type="text" name="judul" id="judul" class="form-control" required value="<?= htmlspecialchars($materi_data['judul'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="keterangan" class="form-label">Keterangan (Opsional)</label>
                        <textarea name="keterangan" id="keterangan" class="form-control" rows="3" placeholder="Tambahkan deskripsi singkat tentang materi ini..."><?= htmlspecialchars($materi_data['keterangan'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label d-block">Pilih Jenis Materi</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="jenis_materi" id="jenisMateriFile" value="file" <?= ($materi_data['jenis_materi'] == 'file') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="jenisMateriFile">Upload File</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="jenis_materi" id="jenisMateriText" value="text" <?= ($materi_data['jenis_materi'] == 'text') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="jenisMateriText">Tulis Teks/Konten</label>
                        </div>
                    </div>

                    <div id="fileUploadSection" class="mb-3" style="display: <?= ($materi_data['jenis_materi'] == 'file') ? 'block' : 'none'; ?>;">
                        <label for="file_materi" class="form-label">Pilih File Materi Baru (biarkan kosong jika tidak ingin mengubah file)</label>
                        <input type="file" name="file_materi" id="file_materi" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar,.mp4,.mp3">
                        <div class="form-text">
                            <?php if ($materi_data['jenis_materi'] == 'file' && !empty($materi_data['file_path'])) : ?>
                                File saat ini: <a href="../assets/materi/<?= htmlspecialchars($materi_data['file_path']); ?>" target="_blank" download><?= htmlspecialchars($materi_data['file_path']); ?></a>
                                <br>
                            <?php endif; ?>
                            Format yang didukung: PDF, DOCX, PPTX, ZIP, RAR, MP4, MP3. Maksimal ukuran file: 20MB.
                        </div>
                    </div>

                    <div id="textEditorSection" class="mb-3" style="display: <?= ($materi_data['jenis_materi'] == 'text') ? 'block' : 'none'; ?>;">
                        <label for="text_materi" class="form-label">Konten Materi Teks</label>
                        <textarea name="text_materi" id="text_materi" class="form-control" rows="10"><?= htmlspecialchars($materi_data['content'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-arrow-repeat me-2"></i> Perbarui Materi
                    </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inisialisasi dan tampilkan Bootstrap Toasts
            const toastElList = document.querySelectorAll('.toast');
            toastElList.forEach(toastEl => {
                const toast = new bootstrap.Toast(toastEl, {
                    delay: 5000
                }); // Auto-hide setelah 5 detik
                toast.show();
            });

            const jenisMateriFile = document.getElementById('jenisMateriFile');
            const jenisMateriText = document.getElementById('jenisMateriText');
            const fileUploadSection = document.getElementById('fileUploadSection');
            const textEditorSection = document.getElementById('textEditorSection');
            const fileInput = document.getElementById('file_materi');
            const textInput = document.getElementById('text_materi'); // Ini adalah textarea

            let editorInstance = null; // Untuk menyimpan instance editor TinyMCE

            function initializeTinyMCE() {
                if (editorInstance) {
                    editorInstance.destroy(); // Pastikan editor lama dihancurkan jika ada
                    editorInstance = null;
                }
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

            function toggleMateriType() {
                if (jenisMateriFile.checked) {
                    fileUploadSection.style.display = 'block';
                    textEditorSection.style.display = 'none';
                    // Ketika beralih ke file, pastikan textarea tidak required
                    textInput.removeAttribute('required');

                    // Hancurkan TinyMCE jika aktif
                    if (editorInstance) {
                        tinymce.get('text_materi').destroy(); // Hancurkan instance TinyMCE
                        editorInstance = null;
                    }

                } else if (jenisMateriText.checked) {
                    fileUploadSection.style.display = 'none';
                    textEditorSection.style.display = 'block';
                    // Ketika beralih ke teks, pastikan textarea required
                    textInput.setAttribute('required', 'required');

                    // Inisialisasi TinyMCE jika belum
                    if (!editorInstance || tinymce.get('text_materi') === null) {
                        initializeTinyMCE();
                    }
                }
            }

            // Atur status awal berdasarkan data materi yang dimuat
            toggleMateriType();

            // Tambahkan event listener untuk radio buttons
            jenisMateriFile.addEventListener('change', toggleMateriType);
            jenisMateriText.addEventListener('change', toggleMateriType);
        });
    </script>
</body>

</html>