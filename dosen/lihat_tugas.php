<?php
session_start();
include "../dbKonek.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'dosen') {
    header("Location: ../login.php");
    exit;
}

$tugas_id = $_GET['tugas_id'] ?? 0;

$tugas = mysqli_fetch_assoc(mysqli_query($konek, "SELECT * FROM tb_tugas WHERE id = $tugas_id"));
$pengumpulan = mysqli_query($konek, "SELECT * FROM tb_pengumpulan WHERE tugas_id = $tugas_id");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Pengumpulan Tugas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container py-4">
        <h4>📥 Pengumpulan Tugas: <?= $tugas['judul']; ?></h4>

        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>Mahasiswa</th>
                    <th>File</th>
                    <th>Nilai</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($pengumpulan)): ?>
                    <tr>
                        <td><?= $row['mahasiswa_username']; ?></td>
                        <td><a href="../assets/uploads/tugas/<?= $row['file_path']; ?>" download>Lihat File</a></td>
                        <td><?= $row['nilai'] ?? '-'; ?></td>
                        <td>
                            <a href="beri_nilai.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-success">Beri Nilai</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>

</html>