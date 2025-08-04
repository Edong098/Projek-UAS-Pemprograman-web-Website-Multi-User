<?php
session_start();
include "../dbKonek.php"; 

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// === tambah Course ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $kode = mysqli_real_escape_string($konek, $_POST['kode_mk']);
    $nama = mysqli_real_escape_string($konek, $_POST['nama_mk']);
    $semester = mysqli_real_escape_string($konek, $_POST['semester']);
    $sks = (int) $_POST['sks'];
    $dosen = mysqli_real_escape_string($konek, $_POST['dosen']);

    // Handle Gambar
    $gambarPath = null;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
        $targetDir = "../assets/gambar/";
        $gambarName = uniqid() . '_' . basename($_FILES["gambar"]["name"]);
        $targetFilePath = $targetDir . $gambarName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileType, $allowedTypes)) {
            if ($_FILES['gambar']['size'] < 5 * 1024 * 1024) {
                if (move_uploaded_file($_FILES["gambar"]["tmp_name"], $targetFilePath)) {
                    $gambarPath = "assets/gambar/" . $gambarName;
                } else {
                    $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal mengunggah gambar.'];
                }
            } else {
                $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Ukuran gambar terlalu besar (maksimal 5MB).'];
            }
        } else {
            $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Tipe file tidak diizinkan (JPG, JPEG, PNG, GIF).'];
        }
    }

    // Jika tidak ada error dari upload
    if (!isset($_SESSION['pesan']) || $_SESSION['pesan']['type'] !== 'danger') {
        // Masukkan ke tb_course
        $insertQuery = "INSERT INTO tb_course (kode_mk, nama_mk, semester, sks, dosen, gambar)
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($konek, $insertQuery);
        mysqli_stmt_bind_param($stmt, "sssiss", $kode, $nama, $semester, $sks, $dosen, $gambarPath);
        if (mysqli_stmt_execute($stmt)) {
            $course_id = mysqli_insert_id($konek);

            // Ambil semua mahasiswa di semester tersebut
            $queryMhs = "SELECT user_id FROM tb_mahasiswa WHERE semester = ?";
            $stmtMhs = mysqli_prepare($konek, $queryMhs);
            mysqli_stmt_bind_param($stmtMhs, "s", $semester);
            mysqli_stmt_execute($stmtMhs);
            $resultMhs = mysqli_stmt_get_result($stmtMhs);

            // Tambahkan ke tb_krs
            $queryKrs = "INSERT IGNORE INTO tb_krs (mahasiswa_id, course_id) VALUES (?, ?)";
            $stmtKrs = mysqli_prepare($konek, $queryKrs);

            while ($row = mysqli_fetch_assoc($resultMhs)) {
                $mhs_id = $row['user_id'];
                mysqli_stmt_bind_param($stmtKrs, "ii", $mhs_id, $course_id);
                mysqli_stmt_execute($stmtKrs);
            }

            $_SESSION['pesan'] = ['type' => 'success', 'message' => 'Mata kuliah berhasil ditambahkan dan langsung dikaitkan dengan mahasiswa semester terkait.'];
        } else {
            $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal menyimpan data mata kuliah.'];
        }
    }

    header("Location: manajemen_mk.php");
    exit;
}

// === Delete Course ===
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    // Get current image path to delete it from server
    $result = mysqli_query($konek, "SELECT gambar FROM tb_course WHERE id = $id");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $oldImagePath = '../' . $row['gambar'];
        if (file_exists($oldImagePath) && is_file($oldImagePath)) {
            unlink($oldImagePath);
        }
    }

    $deleteQuery = "DELETE FROM tb_course WHERE id = $id";
    if (mysqli_query($konek, $deleteQuery)) {
        $_SESSION['pesan'] = ['type' => 'success', 'message' => 'Mata kuliah berhasil dihapus!'];
    } else {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal menghapus mata kuliah: ' . mysqli_error($konek)];
    }
    header("Location: manajemen_mk.php");
    exit;
}

// === Fetch Data for Edit ===
$edit = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $resultEdit = mysqli_query($konek, "SELECT * FROM tb_course WHERE id = $id");
    if ($resultEdit && $row = mysqli_fetch_assoc($resultEdit)) {
        $edit = $row;
    } else {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Data mata kuliah tidak ditemukan.'];
        header("Location: manajemen_mk.php");
        exit;
    }
}

// === Update Course ===
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $id = (int) $_POST['id'];
    $kode = mysqli_real_escape_string($konek, $_POST['kode_mk']);
    $nama = mysqli_real_escape_string($konek, $_POST['nama_mk']);
    $semester = mysqli_real_escape_string($konek, $_POST['semester']);
    $sks = (int) $_POST['sks'];
    $dosen = mysqli_real_escape_string($konek, $_POST['dosen']);

    $gambarPath = null;
    $updateImage = false;

    // Check if a new image is uploaded
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === 0) {
        $targetDir = "../assets/gambar/";
        $gambarName = uniqid() . '_' . basename($_FILES["gambar"]["name"]);
        $targetFilePath = $targetDir . $gambarName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($fileType, $allowedTypes)) {
            if ($_FILES['gambar']['size'] < 5 * 1024 * 1024) { // 5 MB
                // Get old image path to delete it
                $resultOldImage = mysqli_query($konek, "SELECT gambar FROM tb_course WHERE id = $id");
                if ($resultOldImage && $oldRow = mysqli_fetch_assoc($resultOldImage)) {
                    $oldImagePath = '../' . $oldRow['gambar'];
                    if (file_exists($oldImagePath) && is_file($oldImagePath)) {
                        unlink($oldImagePath); // Delete old file
                    }
                }

                if (move_uploaded_file($_FILES["gambar"]["tmp_name"], $targetFilePath)) {
                    $gambarPath = "assets/gambar/" . $gambarName;
                    $updateImage = true;
                } else {
                    $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal mengunggah gambar baru.'];
                }
            } else {
                $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Ukuran gambar terlalu besar (maksimal 5MB).'];
            }
        } else {
            $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Tipe file gambar tidak diizinkan. Hanya JPG, JPEG, PNG, GIF.'];
        }
    }

    $updateQuery = "UPDATE tb_course SET kode_mk='$kode', nama_mk='$nama', semester='$semester', sks='$sks', dosen='$dosen'";
    if ($updateImage) {
        $updateQuery .= ", gambar='$gambarPath'";
    }
    $updateQuery .= " WHERE id = $id";

    // Only proceed with update if there's no critical image upload error
    if (!isset($_SESSION['pesan']) || $_SESSION['pesan']['type'] !== 'danger') {
        if (mysqli_query($konek, $updateQuery)) {
            $_SESSION['pesan'] = ['type' => 'success', 'message' => 'Mata kuliah berhasil diperbarui!'];
        } else {
            $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal memperbarui mata kuliah: ' . mysqli_error($konek)];
        }
    }

    header("Location: manajemen_mk.php");
    exit;
}

// === Get All Courses ===
// Make sure to select the 'gambar' column as well
$mk = mysqli_query($konek, "SELECT id, kode_mk, nama_mk, semester, sks, dosen, gambar FROM tb_course ORDER BY semester, nama_mk ASC");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Mata Kuliah - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .container {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
        }

        .header-section {
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-section h3 {
            color: #343a40;
            font-weight: 600;
        }

        .form-section {
            background-color: #f2f4f6;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 40px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        /* Card specific styles */
        .card {
            border: none;
            transition: transform 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-img-top {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            object-fit: cover;
            height: 180px;
            /* Consistent image height */
        }

        .card-body {
            padding: 20px;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #007bff;
            margin-bottom: 10px;
        }

        .card-text {
            font-size: 0.95rem;
            color: #555;
            line-height: 1.6;
        }

        .card-text strong {
            color: #333;
        }

        .btn-action {
            margin-right: 5px;
        }

        .alert-dismissible .btn-close {
            font-size: 0.8rem;
            padding: 0.5em 0.5em;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header-section">
            <h3><i class="fas fa-book-open me-2"></i>Manajemen Mata Kuliah</h3>
            <a href="dashboardAdmin.php" class="btn btn-outline-secondary btn-sm">Kembali</a>
        </div>

        <?php
        // Displaying notification messages
        if (isset($_SESSION['pesan'])) {
            $alertType = $_SESSION['pesan']['type'];
            $alertMessage = $_SESSION['pesan']['message'];
            echo '<div class="alert alert-' . $alertType . ' alert-dismissible fade show" role="alert">';
            echo $alertMessage;
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['pesan']); // Clear message after displaying
        }
        ?>

        <div class="form-section">
            <h5 class="mb-4 text-primary"><?= $edit ? '<i class="fas fa-edit me-1"></i> Edit Data Mata Kuliah' : '<i class="fas fa-folder-plus me-1"></i> Tambah Mata Kuliah Baru' ?></h5>
            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
                <div class="col-md-3">
                    <label for="kode_mk" class="form-label">Kode MK</label>
                    <input type="text" name="kode_mk" id="kode_mk" class="form-control" placeholder="Contoh: IF001" required value="<?= htmlspecialchars($edit['kode_mk'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="nama_mk" class="form-label">Nama Mata Kuliah</label>
                    <input type="text" name="nama_mk" id="nama_mk" class="form-control" placeholder="Contoh: Pemrograman Web" required value="<?= htmlspecialchars($edit['nama_mk'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="semester" class="form-label">Semester</label>
                    <input type="text" name="semester" id="semester" class="form-control" placeholder="1-8" required value="<?= htmlspecialchars($edit['semester'] ?? '') ?>">
                </div>
                <div class="col-md-1">
                    <label for="sks" class="form-label">SKS</label>
                    <input type="number" name="sks" id="sks" class="form-control" placeholder="2-4" required value="<?= htmlspecialchars($edit['sks'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="dosen" class="form-label">Dosen Pengampu</label>
                    <input type="text" name="dosen" id="dosen" class="form-control" placeholder="Contoh: Budi Santoso" required value="<?= htmlspecialchars($edit['dosen'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="gambar" class="form-label">Gambar Mata Kuliah <?= $edit ? '<small class="text-muted">(Pilih untuk mengubah)</small>' : '' ?></label>
                    <input type="file" name="gambar" id="gambar" class="form-control" accept="image/jpeg, image/png, image/gif">
                </div>

                <div class="col-md-10 d-flex justify-content-start gap-3 mt-3">
                    <?php if ($edit): ?>
                        <button type="submit" name="update" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i> Update
                        </button>
                        <a href="manajemen_mk.php" class="btn btn-secondary">
                            <i class="fas fa-times-circle me-1"></i> Batal
                        </a>
                    <?php else: ?>
                        <button type="submit" name="simpan" class="btn btn-primary">
                            <i class="fas fa-plus-circle me-1"></i> Tambah Mata Kuliah
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h4 class="mb-3 text-secondary"><i class="fas fa-list-alt me-2"></i>Daftar Mata Kuliah</h4>

        <?php if (mysqli_num_rows($mk) > 0): ?>
            <div class="table-responsive text-center">
                <table class="table table-bordered align-middle table-hover shadow-sm ">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Kode MK</th>
                            <th>Nama MK</th>
                            <th>Semester</th>
                            <th>SKS</th>
                            <th>Dosen</th>
                            <th>Gambar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1;
                        while ($row = mysqli_fetch_assoc($mk)) : ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['kode_mk']) ?></td>
                                <td><?= htmlspecialchars($row['nama_mk']) ?></td>
                                <td><?= htmlspecialchars($row['semester']) ?></td>
                                <td><?= htmlspecialchars($row['sks']) ?></td>
                                <td><?= htmlspecialchars($row['dosen']) ?></td>
                                <td style="width: 100px;">
                                    <img src="../<?= htmlspecialchars($row['gambar'] ?: 'assets/images/default_course.jpg') ?>"
                                        class="img-fluid rounded" style="height: 60px; object-fit: cover;"
                                        alt="Gambar Mata Kuliah <?= htmlspecialchars($row['nama_mk']) ?>">
                                </td>
                                <td>
                                    <a href="?edit=<?= $row['id'] ?>" class="btn btn-sm btn-info mb-1"><i class="fas fa-pencil-alt"></i> Edit</a>
                                    <a href="?hapus=<?= $row['id'] ?>" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Yakin ingin menghapus mata kuliah ini?')">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center" role="alert">
                Belum ada data mata kuliah yang terdaftar.
            </div>
        <?php endif; ?>
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>