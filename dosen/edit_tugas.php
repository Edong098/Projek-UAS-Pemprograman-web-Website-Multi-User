<?php
session_start();
include "../dbKonek.php"; // Pastikan file ini mendefinisikan $konek

// Aktifkan pelaporan error untuk debugging (HAPUS ATAU UBAH DI LINGKUNGAN PRODUKSI)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fungsi untuk mengatur pesan notifikasi (konsisten dengan kelola_course.php dan edit_materi.php)
function set_message($message, $type)
{
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

// Cek login dan role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dosen') {
    set_message("Anda tidak memiliki akses ke halaman ini.", "danger");
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];

// Validasi parameter yang lebih detail
$tugas_id = 0;
$course_id = 0;

// Validasi ID tugas
if (isset($_GET['id'])) {
    if (is_numeric($_GET['id']) && $_GET['id'] > 0) {
        $tugas_id = intval($_GET['id']);
    } else {
        set_message("ID tugas tidak valid. Harus berupa angka positif.", "danger");
        header("Location: dashboardDosen.php");
        exit;
    }
} else {
    set_message("ID tugas tidak ditemukan dalam URL.", "danger");
    header("Location: dashboardDosen.php");
    exit;
}

// Validasi Course ID
if (isset($_GET['course_id'])) {
    if (is_numeric($_GET['course_id']) && $_GET['course_id'] > 0) {
        $course_id = intval($_GET['course_id']);
    } else {
        set_message("Course ID tidak valid. Harus berupa angka positif.", "danger");
        header("Location: dashboardDosen.php");
        exit;
    }
} else {
    set_message("Course ID tidak ditemukan dalam URL.", "danger");
    header("Location: dashboardDosen.php");
    exit;
}

// Cek koneksi database
if (!$konek) {
    set_message("Koneksi database gagal.", "danger");
    header("Location: dashboardDosen.php");
    exit;
}

// Validasi mata kuliah dengan error handling yang lebih baik
$course_stmt = mysqli_prepare($konek, "SELECT nama_mk, kode_mk FROM tb_course WHERE id = ? AND dosen = ?");
if (!$course_stmt) {
    set_message("Error preparing course query: " . mysqli_error($konek), "danger");
    header("Location: dashboardDosen.php");
    exit;
}

mysqli_stmt_bind_param($course_stmt, "is", $course_id, $username);
if (!mysqli_stmt_execute($course_stmt)) {
    set_message("Error executing course query: " . mysqli_stmt_error($course_stmt), "danger");
    mysqli_stmt_close($course_stmt);
    header("Location: dashboardDosen.php");
    exit;
}

$course_result = mysqli_stmt_get_result($course_stmt);
$course = mysqli_fetch_assoc($course_result);
mysqli_stmt_close($course_stmt);

if (!$course) {
    set_message("Mata kuliah dengan ID $course_id tidak ditemukan atau Anda tidak memiliki akses.", "danger");
    header("Location: dashboardDosen.php");
    exit;
}

// Validasi tugas dengan error handling yang lebih baik
$stmt_fetch = mysqli_prepare($konek, "SELECT tt.* FROM tb_tugas tt JOIN tb_course tc ON tt.course_id = tc.id WHERE tt.id = ? AND tt.course_id = ? AND tc.dosen = ?");
if (!$stmt_fetch) {
    set_message("Error preparing tugas query: " . mysqli_error($konek), "danger");
    header("Location: kelola_tugas.php?course_id=$course_id"); // Kembali ke manage_tugas
    exit;
}

mysqli_stmt_bind_param($stmt_fetch, "iis", $tugas_id, $course_id, $username);
if (!mysqli_stmt_execute($stmt_fetch)) {
    set_message("Error executing tugas query: " . mysqli_stmt_error($stmt_fetch), "danger");
    mysqli_stmt_close($stmt_fetch);
    header("Location: kelola_tugas.php?course_id=$course_id"); // Kembali ke manage_tugas
    exit;
}

$result_fetch = mysqli_stmt_get_result($stmt_fetch);
$tugas_data = mysqli_fetch_assoc($result_fetch);
mysqli_stmt_close($stmt_fetch);

if (!$tugas_data) {
    set_message("Tugas dengan ID $tugas_id tidak ditemukan dalam mata kuliah ini atau Anda tidak memiliki izin.", "danger");
    header("Location: kelola_tugas.php?course_id=$course_id"); // Kembali ke manage_tugas
    exit;
}

// Proses update dengan validasi yang lebih ketat
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $deadline_str = trim($_POST['deadline'] ?? '');
    $errors = [];

    $existing_file_path = $tugas_data['file_path'];
    $new_file_path = $existing_file_path;

    // Validasi input
    if (empty($judul)) {
        $errors[] = "Judul tidak boleh kosong.";
    } elseif (strlen($judul) > 255) {
        $errors[] = "Judul terlalu panjang (maksimal 255 karakter).";
    }

    if (empty($deadline_str)) {
        $errors[] = "Deadline tidak boleh kosong.";
    } else {
        $deadline_timestamp = strtotime($deadline_str);
        if ($deadline_timestamp === false) {
            $errors[] = "Format deadline tidak valid.";
        } else {
            $deadline = date('Y-m-d H:i:s', $deadline_timestamp);
            // Validasi deadline tidak boleh di masa lalu
            if ($deadline_timestamp < time()) {
                $errors[] = "Deadline tidak boleh di masa lalu.";
            }
        }
    }

    // Upload file dengan validasi yang lebih ketat
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'jpg', 'jpeg', 'png'];
        $file_info = pathinfo($_FILES['file']['name']);
        $file_ext = strtolower($file_info['extension'] ?? '');

        if (!in_array($file_ext, $allowed_types)) {
            $errors[] = "Tipe file tidak diizinkan. Hanya: " . implode(', ', $allowed_types);
        } elseif ($_FILES['file']['size'] > 20971520) { // 20MB
            $errors[] = "Ukuran file maksimal 20MB.";
        } else {
            $upload_dir = "../assets/tugas/";
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors[] = "Gagal membuat direktori upload.";
                }
            }

            if (empty($errors)) {
                $tmp_name = $_FILES['file']['tmp_name'];
                $unique_name = uniqid('tugas_') . '.' . $file_ext;
                $target_path = $upload_dir . $unique_name;

                if (move_uploaded_file($tmp_name, $target_path)) {
                    // Hapus file lama jika ada dan berhasil diunggah file baru
                    if (!empty($existing_file_path) && file_exists($upload_dir . $existing_file_path)) {
                        unlink($upload_dir . $existing_file_path);
                    }
                    $new_file_path = $unique_name;
                } else {
                    $errors[] = "Gagal mengunggah file baru.";
                }
            }
        }
    } elseif (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_errors_map = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran file terlalu besar (melebihi upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file terlalu besar (melebihi MAX_FILE_SIZE pada form HTML).',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian.',
            UPLOAD_ERR_NO_TMP_DIR => 'Direktori temporary tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            UPLOAD_ERR_EXTENSION => 'Ekstensi PHP menghentikan proses upload file.'
        ];
        $errors[] = $upload_errors_map[$_FILES['file']['error']] ?? 'Error upload tidak dikenal.';
    }

    // Update database dengan transaction
    if (empty($errors)) {
        mysqli_begin_transaction($konek);
        try {
            $stmt_update = mysqli_prepare($konek, "UPDATE tb_tugas SET judul = ?, deskripsi = ?, deadline = ?, file_path = ?, updated_at = NOW() WHERE id = ? AND course_id = ?");
            if (!$stmt_update) {
                throw new Exception("Error preparing update query: " . mysqli_error($konek));
            }

            mysqli_stmt_bind_param($stmt_update, "ssssii", $judul, $deskripsi, $deadline, $new_file_path, $tugas_id, $course_id);

            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception("Error executing update: " . mysqli_stmt_error($stmt_update));
            }

            mysqli_stmt_close($stmt_update);
            mysqli_commit($konek);

            set_message("Tugas berhasil diperbarui.", "success");
            header("Location: manage_tugas.php?course_id=$course_id"); // Redirect ke halaman manage_tugas
            exit;
        } catch (Exception $e) {
            mysqli_rollback($konek);
            set_message("Gagal memperbarui tugas: " . $e->getMessage(), "danger");
        }
    } else {
        set_message(implode("<br>", $errors), "danger");
        // Simpan input user untuk ditampilkan kembali di form
        $tugas_data['judul'] = $judul;
        $tugas_data['deskripsi'] = $deskripsi;
        $tugas_data['deadline'] = $deadline_str;
    }
}

mysqli_close($konek);

// Format deadline untuk input datetime-local
$deadline_value = '';
if (!empty($tugas_data['deadline'])) {
    $deadline_timestamp = strtotime($tugas_data['deadline']);
    if ($deadline_timestamp !== false) {
        $deadline_value = date('Y-m-d\TH:i', $deadline_timestamp);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Tugas - <?= htmlspecialchars($course['nama_mk'] ?? 'Mata Kuliah'); ?></title>
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

        /* Styles for Bootstrap Toasts */
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

        .footer {
            background-color: var(--primary-dark);
            color: white;
            text-align: center;
            padding: 20px 0;
            margin-top: auto;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
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
            // Hapus pesan dari sesi setelah ditampilkan
            unset($_SESSION['message']);
            unset($_SESSION['message_type']);
            ?>
        <?php endif; ?>
    </div>

    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="kelola_course.php?id=<?= $course_id ?>">
                <i class="bi bi-arrow-left-circle-fill"></i> Kembali ke Kelola Tugas
            </a>
            <span class="fw-bold text-dark"><i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($username); ?></span>
        </div>
    </nav>

    <div class="container">
        <h3 class="mb-4 text-dark">
            <i class="bi bi-pencil-square me-2"></i> Edit Tugas: <?= htmlspecialchars($tugas_data['judul'] ?? ''); ?>
        </h3>

        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-journal-text"></i> Form Edit Tugas
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="editTugasForm">
                    <div class="mb-3">
                        <label for="judul" class="form-label">
                            <i class="bi bi-card-heading me-1"></i> Judul Tugas <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="judul" id="judul" class="form-control" required value="<?= htmlspecialchars($tugas_data['judul'] ?? '') ?>" maxlength="255">
                        <div class="form-text">Maksimal 255 karakter</div>
                    </div>

                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">
                            <i class="bi bi-text-left me-1"></i> Deskripsi (Opsional)
                        </label>
                        <textarea name="deskripsi" id="deskripsi" class="form-control" rows="5" placeholder="Tambahkan deskripsi singkat tentang tugas ini..."><?= htmlspecialchars($tugas_data['deskripsi'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="deadline" class="form-label">
                            <i class="bi bi-calendar-event me-1"></i> Deadline <span class="text-danger">*</span>
                        </label>
                        <input type="datetime-local" name="deadline" id="deadline" class="form-control" required value="<?= $deadline_value ?>" min="<?= date('Y-m-d\TH:i') ?>">
                        <div class="form-text">Pilih tanggal dan waktu deadline</div>
                    </div>

                    <div class="mb-3">
                        <label for="file" class="form-label">
                            <i class="bi bi-cloud-arrow-up me-1"></i> Unggah File Baru (opsional)
                        </label>
                        <input type="file" name="file" id="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.zip,.rar,.txt,.jpg,.jpeg,.png">
                        <div class="form-text">
                            Maksimal 20MB. Format yang didukung: PDF, DOC, DOCX, PPT, PPTX, ZIP, RAR, TXT, JPG, JPEG, PNG.
                        </div>
                        <?php if (!empty($tugas_data['file_path'])) : ?>
                            <div class="mt-2 p-2 bg-light rounded border">
                                <small class="text-muted">
                                    <i class="bi bi-file-earmark-fill me-1"></i> File saat ini:
                                    <a href="../assets/tugas/<?= htmlspecialchars($tugas_data['file_path']) ?>" target="_blank" download class="text-decoration-none">
                                        <?= htmlspecialchars($tugas_data['file_path']) ?>
                                    </a>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-star">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-arrow-repeat me-1"></i> Perbarui Tugas
                        </button>
                    </div>
                </form>
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
                    delay: 5000 // Auto-hide setelah 5 detik
                });
                toast.show();
            });

            // Validasi client-side
            document.getElementById('editTugasForm').addEventListener('submit', function(e) {
                const judul = document.getElementById('judul').value.trim();
                const deadline = document.getElementById('deadline').value;
                const fileInput = document.getElementById('file');

                if (judul === '') {
                    alert('Judul tugas tidak boleh kosong!');
                    e.preventDefault();
                    return;
                }

                if (deadline === '') {
                    alert('Deadline tidak boleh kosong!');
                    e.preventDefault();
                    return;
                }

                const deadlineDate = new Date(deadline);
                const now = new Date();

                // Membandingkan tanggal dan waktu secara spesifik
                // Jika deadline yang dipilih sama atau kurang dari waktu sekarang, anggap sebagai masa lalu.
                if (deadlineDate <= now) {
                    alert('Deadline tidak boleh di masa lalu. Pilih tanggal dan waktu di masa depan.');
                    e.preventDefault();
                    return;
                }

                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    const maxSize = 20 * 1024 * 1024; // 20MB

                    if (file.size > maxSize) {
                        alert('Ukuran file maksimal 20MB!');
                        e.preventDefault();
                        return;
                    }

                    const allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'jpg', 'jpeg', 'png'];
                    const fileName = file.name;
                    const fileExtension = fileName.split('.').pop().toLowerCase();

                    if (!allowedExtensions.includes(fileExtension)) {
                        alert('Tipe file tidak diizinkan. Hanya: ' + allowedExtensions.join(', '));
                        e.preventDefault();
                        return;
                    }
                }
            });
        });
    </script>
</body>

</html>