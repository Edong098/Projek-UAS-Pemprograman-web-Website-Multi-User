<?php
session_start();
include "../dbKonek.php"; // Adjust your database connection path

// Ensure only students can access this page
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];
$absensi_id = isset($_GET['absensi_id']) ? intval($_GET['absensi_id']) : 0;
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;

// Initialize messages and submitted data
$message = '';
$message_type = '';
$submitted_status = '';
$submitted_keterangan = '';

// Fetch attendance session details
$absensi = null;
$absensi_stmt = mysqli_prepare($konek, "SELECT id, pertemuan_ke, tanggal, course_id FROM tb_absensi WHERE id = ?");
mysqli_stmt_bind_param($absensi_stmt, "i", $absensi_id);
mysqli_stmt_execute($absensi_stmt);
$absensi_result = mysqli_stmt_get_result($absensi_stmt);

if (mysqli_num_rows($absensi_result) > 0) {
    $absensi = mysqli_fetch_assoc($absensi_result);
    // Ensure course_id is set if not passed in GET, by taking it from absensi data
    if ($course_id == 0 && isset($absensi['course_id'])) {
        $course_id = $absensi['course_id'];
    }
}
mysqli_stmt_close($absensi_stmt);

// Fetch course name for the navbar title
$nama_mk = 'Mata Kuliah'; // Default value
if ($course_id > 0) {
    $course_name_stmt = mysqli_prepare($konek, "SELECT nama_mk FROM tb_course WHERE id = ?");
    mysqli_stmt_bind_param($course_name_stmt, "i", $course_id);
    mysqli_stmt_execute($course_name_stmt);
    $course_name_result = mysqli_stmt_get_result($course_name_stmt);
    if ($course_name_data = mysqli_fetch_assoc($course_name_result)) {
        $nama_mk = htmlspecialchars($course_name_data['nama_mk']);
    }
    mysqli_stmt_close($course_name_stmt);
}


// Redirect if attendance session not found or absensi_id is invalid
if (!$absensi) {
    $_SESSION['message'] = "Sesi absensi tidak ditemukan atau tidak valid.";
    $_SESSION['message_type'] = "danger";
    header("Location: ../dashboardMahasiswa.php"); // Adjust redirect path as needed
    exit;
}

// Check if student has already submitted attendance for this session
$already_submitted = false;
$check_stmt = mysqli_prepare($konek, "SELECT status, keterangan FROM tb_detail_absensi WHERE absensi_id = ? AND mahasiswa_username = ?");
mysqli_stmt_bind_param($check_stmt, "is", $absensi_id, $username);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
if (mysqli_num_rows($check_result) > 0) {
    $already_submitted = true;
    $submission_data = mysqli_fetch_assoc($check_result);
    $submitted_status = $submission_data['status'];
    $submitted_keterangan = $submission_data['keterangan'];
}
mysqli_stmt_close($check_stmt);


// Process form submission only if method is POST and not already submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$already_submitted) {
    $status = $_POST['status'] ?? '';
    $keterangan = $_POST['keterangan'] ?? '';

    $allowed_statuses = ['hadir', 'izin', 'sakit', 'alpa'];
    if (!in_array(strtolower($status), $allowed_statuses)) {
        $message = "❌ Status kehadiran tidak valid.";
        $message_type = "danger";
    } else {
        $insert_stmt = mysqli_prepare($konek, "INSERT INTO tb_detail_absensi (absensi_id, mahasiswa_username, status, keterangan, created_at) VALUES (?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($insert_stmt, "isss", $absensi_id, $username, $status, $keterangan);

        if (mysqli_stmt_execute($insert_stmt)) {
            $already_submitted = true;
            $submitted_status = $status; // Update submitted data for display
            $submitted_keterangan = $keterangan; // Update submitted data for display
            $message = "✅ Absensi berhasil dikirim untuk Pertemuan ke-" . htmlspecialchars($absensi['pertemuan_ke']) . ".";
            $message_type = "success";
        } else {
            if (mysqli_errno($konek) == 1062) {
                $message = "⚠️ Anda sudah mengisi absensi untuk pertemuan ini sebelumnya.";
                $message_type = "warning";
                $already_submitted = true;
                // Re-fetch submitted data if it was a duplicate attempt to show current status
                $check_stmt = mysqli_prepare($konek, "SELECT status, keterangan FROM tb_detail_absensi WHERE absensi_id = ? AND mahasiswa_username = ?");
                mysqli_stmt_bind_param($check_stmt, "is", $absensi_id, $username);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                if (mysqli_num_rows($check_result) > 0) {
                    $submission_data = mysqli_fetch_assoc($check_result);
                    $submitted_status = $submission_data['status'];
                    $submitted_keterangan = $submission_data['keterangan'];
                }
                mysqli_stmt_close($check_stmt);
            } else {
                $message = "❌ Terjadi kesalahan saat menyimpan absensi: " . mysqli_error($konek);
                $message_type = "danger";
            }
        }
        mysqli_stmt_close($insert_stmt);
    }
}

mysqli_close($konek);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Isi Absensi: Pertemuan ke-<?= htmlspecialchars($absensi['pertemuan_ke']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            /* Bright Blue */
            --primary-dark: #0056b3;
            /* Darker Blue */
            --success-color: #28a745;
            /* Green (for success messages) */
            --success-dark: #218838;
            --secondary-color: #6c757d;
            /* Gray */
            --secondary-dark: #5a6268;
            --info-color: #17a2b8;
            /* Info Blue (for already submitted info) */
            --info-dark: #138496;
            --background-light: #f8f9fa;
            /* Light gray background, consistent with dosen page */
            --card-background: #ffffff;
            --border-color: #cce0ff;
            /* Lighter blue border */
            --shadow-light: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            /* Slightly stronger shadow */
            --text-dark: #212529;
            /* Dark text */
            --text-muted: #5a6268;
            /* Slightly darker muted text */
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-light);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 70px;
            /* Space for the top navbar */
        }

        /* Top Navbar Styles (Copied from dosen's input_absensi.php) */
        .top-navbar {
            width: 100%;
            position: fixed;
            top: 0;
            left: 0;
            background-color: var(--primary-dark);
            /* Tetap dark */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            color: #ffffff;
            /* Warna teks putih */
            font-size: 0.9rem;
            text-decoration: none;
            padding: 8px 15px;
            transition: color 0.3s ease, background-color 0.3s ease;
            border-radius: 5px;
        }

        .top-navbar .nav-link:hover {
            color: #dddddd;
            /* Sedikit abu-abu saat hover */
        }

        .top-navbar .nav-link i {
            font-size: 1.2rem;
            margin-right: 5px;
        }

        .top-navbar .nav-link.active {
            color: var(--primary-color);
            /* Misalnya biru terang sebagai highlight */
            font-weight: bold;
        }

        .top-navbar .navbar-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: var(--text-dark);
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
            }

            .top-navbar .nav-link i {
                font-size: 1.1rem;
                margin-right: 3px;
            }
        }

        .content-wrapper {
            flex-grow: 1;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            /* Center vertically */
            padding-bottom: 20px;
            /* Add some padding at the bottom */
        }

        .card {
            border: none;
            border-radius: 0.75rem;
            /* More rounded corners */
            box-shadow: var(--shadow-light);
            background-color: var(--card-background);
            width: 100%;
            /* Make card fill available width */
            max-width: 800px;
            /* Lebarkan kolom pembungkus */
        }

        .card-header-custom {
            background-color: var(--primary-color);
            color: white;
            padding: 1.25rem 1.5rem;
            /* Match dosen page card-header padding */
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            font-size: 1.25rem;
            /* Match dosen page h5 size */
            font-weight: 800;
            /* Match dosen page font-weight */
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 0;
            /* Remove default margin */
        }

        /* Adjusted specific card-header-custom to match card design */
        .card-header-custom {
            margin: 0;
            /* Remove negative margins from previous design */
            border-bottom: none;
            box-shadow: none;
            /* Shadow handled by .card itself */
            border-top-left-radius: 0.75rem;
            /* Ensure consistent radius */
            border-top-right-radius: 0.75rem;
            padding: 1.25rem 1.5rem;
            /* Consistent padding */
        }

        h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
            padding-left: 1.5rem;
            /* Align with card-body padding */
            padding-right: 1.5rem;
            padding-top: 1.5rem;
            /* Add padding for separation from header */
        }

        .text-muted {
            font-size: 0.95rem;
            /* Slightly larger for readability */
            color: var(--text-muted) !important;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }

        hr {
            margin: 1rem 1.5rem;
            /* Align hr with padding */
            border-color: rgba(0, 0, 0, .1);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .form-select,
        .form-control {
            border-radius: 0.5rem;
            /* Slightly less rounded than card, more typical for inputs */
            border: 1px solid var(--border-color);
            padding: 0.6rem 1rem;
            /* Slightly smaller padding for inputs */
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
            outline: none;
        }

        .btn {
            border-radius: 0.5rem;
            /* Match input border-radius */
            padding: 0.6rem 1.25rem;
            /* Adjusted padding */
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            /* Center icon and text */
            gap: 8px;
        }

        .btn-submit {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-submit:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-secondary:hover {
            background-color: var(--secondary-dark);
            border-color: var(--secondary-dark);
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 0.5rem;
            font-size: 0.9rem;
            /* Slightly smaller for alerts */
            margin-bottom: 1rem;
            /* Standard Bootstrap margin */
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert .fas,
        .alert .far {
            font-size: 1.1rem;
            /* Adjust icon size in alerts */
        }

        .submitted-info {
            background-color: #e0f2fe;
            /* Very light blue background for submitted info */
            border: 1px solid #90caf9;
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
            /* Adjusted padding */
            margin-bottom: 1.5rem;
        }

        .submitted-info p {
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }

        .submitted-info strong {
            color: var(--primary-dark);
        }

        /* Responsive adjustments for content padding */
        @media (max-width: 576px) {
            .content-wrapper {
                padding: 15px;
            }

            .card {
                margin: 0;
                /* Remove auto margin on small screens to expand */
                border-radius: 0;
                /* Optional: make card full width on mobile */
                box-shadow: none;
                /* Optional: remove shadow on mobile */
            }

            .card-header-custom {
                border-radius: 0;
                /* Match card if adjusted */
            }

            h4,
            .text-muted {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .card-body {
                /* Menyesuaikan padding di card-body untuk layar kecil */
                padding: 1rem;
            }

            hr {
                margin: 1rem 1rem;
            }
        }
    </style>
</head>

<body>
    <nav class="top-navbar">
        <div class="nav-links-group">
            <a class="nav-link" href="materi.php?course_id=<?= htmlspecialchars($course_id) ?>">
                <i class="bi bi-arrow-left-circle"></i>
                <span>Kembali</span>
            </a>
        </div>
        <div class="nav-links-group">
            <a class="nav-link" href="profil_mahasiswa.php"> <i class="bi bi-person-circle"></i>
                <span>Profil</span>
            </a>
        </div>
    </nav>

    <div class="content-wrapper container">
        <div class="card">
            <div class="card-header-custom">
                <i class="fas fa-clipboard-check"></i> Isi Absensi Mahasiswa
            </div>

            <div class="card-body">
                <p class="text-muted p-0"><i class="far fa-calendar-alt"></i> Tanggal: <?= date("d M Y", strtotime($absensi['tanggal'])); ?></p>
                <hr>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $message_type; ?> alert-dismissible fade show" role="alert">
                        <i class="fas <?= ($message_type == 'success' ? 'fa-check-circle' : ($message_type == 'danger' ? 'fa-times-circle' : ($message_type == 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'))); ?> me-2"></i>
                        <div>
                            <?= $message; ?>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($already_submitted): ?>
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <div>
                            Anda sudah mengisi absensi ini.
                        </div>
                    </div>
                    <div class="submitted-info">
                        <p>Status Kehadiran Anda: <strong><?= htmlspecialchars(ucfirst($submitted_status)); ?></strong></p>
                        <?php if (!empty($submitted_keterangan)): ?>
                            <p>Keterangan: <em><?= htmlspecialchars($submitted_keterangan); ?></em></p>
                        <?php else: ?>
                            <p>Keterangan: -</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <form method="POST" onsubmit="return validateForm()">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status Kehadiran <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="">-- Pilih Status --</option>
                                <option value="hadir">Hadir</option>
                                <option value="izin">Izin</option>
                                <option value="sakit">Sakit</option>
                                <option value="alpa">Alpa</option>
                            </select>
                            <div class="form-text">Pilih status kehadiranmu untuk absensi ini.</div>
                        </div>
                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan Tambahan (Opsional)</label>
                            <textarea name="keterangan" id="keterangan" class="form-control" rows="3" placeholder="Contoh: Izin karena urusan keluarga, Sakit demam"></textarea>
                            <div class="form-text">Berikan keterangan jika kamu izin atau sakit.</div>
                        </div>
                        <div class="gap-2">
                            <button type="submit" class="btn btn-submit btn-sm mt-2">
                                <i class="fas fa-paper-plane"></i> Kirim Absensi
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateForm() {
            var status = document.getElementById('status').value;
            if (status === "") {
                alert("Mohon pilih status kehadiran Anda.");
                return false;
            }
            return true;
        }
    </script>
</body>

</html>