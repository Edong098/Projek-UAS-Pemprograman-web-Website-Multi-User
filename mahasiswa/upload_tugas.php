<?php
session_start();
include "../dbKonek.php";

// Cek login dan role
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION['username'];
$tugas_id = isset($_GET['tugas_id']) ? intval($_GET['tugas_id']) : 0;

// Cek validitas tugas
$tugas = mysqli_fetch_assoc(mysqli_query($konek, "SELECT * FROM tb_tugas WHERE id = $tugas_id"));
if (!$tugas) {
    die("Tugas tidak ditemukan.");
}

// Cek apakah mahasiswa sudah upload sebelumnya
$pengumpulan = mysqli_fetch_assoc(mysqli_query($konek, "SELECT * FROM tb_pengumpulan WHERE username='$username' AND tugas_id=$tugas_id"));

// Handle form upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['file'];
    $allowed_types = ['pdf', 'doc', 'docx', 'zip'];

    if ($file['error'] === 0) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), $allowed_types)) {
            $newName = uniqid() . '.' . $ext;
            $targetPath = "../assets/tugas/" . $newName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Hapus file lama jika ada
                if ($pengumpulan && file_exists("../assets/uploads/tugas/" . $pengumpulan['file_path'])) {
                    unlink("../assets/uploads/tugas/" . $pengumpulan['file_path']);
                }

                // Simpan baru atau update
                if ($pengumpulan) {
                    mysqli_query($konek, "UPDATE tb_pengumpulan SET file_path='$newName', submitted_at=NOW() WHERE id=" . $pengumpulan['id']);
                } else {
                    mysqli_query($konek, "INSERT INTO tb_pengumpulan (username, tugas_id, file_path) VALUES ('$username', $tugas_id, '$newName')");
                }

                header("Location: detail_course.php?id=" . $tugas['course_id']);
                exit;
            } else {
                $error = "Gagal mengupload file.";
            }
        } else {
            $error = "Tipe file tidak diperbolehkan (pdf, doc, docx, zip).";
        }
    } else {
        $error = "Terjadi kesalahan pada file upload.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Upload Tugas - <?= htmlspecialchars($tugas['judul']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-4">
        <h3>Upload Tugas: <?= htmlspecialchars($tugas['judul']); ?></h3>
        <p>Deadline: <?= date("d M Y H:i", strtotime($tugas['deadline'])); ?></p>

        <?php if (isset($error)) : ?>
            <div class="alert alert-danger"><?= $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <?php if ($pengumpulan): ?>
                <div class="mb-3">
                    <label class="form-label">File yang sudah Anda upload:</label><br>
                    <a class="btn btn-sm btn-outline-success" href="../assets/uploads/tugas/<?= htmlspecialchars($pengumpulan['file_path']); ?>" download>
                        <?= htmlspecialchars($pengumpulan['file_path']); ?> ⬇️
                    </a>
                </div>
                <div class="alert alert-warning">Anda dapat mengganti file dengan upload ulang.</div>
            <?php endif; ?>

            <div class="mb-3">
                <label for="file" class="form-label">Pilih File Tugas (pdf, doc, zip)</label>
                <input type="file" name="file" id="file" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary"><?= $pengumpulan ? 'Re-Upload' : 'Upload' ?></button>
            <a href="detail_course.php?id=<?= $tugas['course_id']; ?>" class="btn btn-secondary">Kembali</a>
        </form>
    </div>
</body>

</html>