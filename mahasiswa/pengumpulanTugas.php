<?php
session_start();
include "dbKonek.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'dosen') {
    header("Location: login.php");
    exit;
}

$course_id = intval($_GET['course_id']);

$tugas = mysqli_query($konek, "SELECT * FROM tb_tugas WHERE course_id = $course_id");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Lihat Tugas Mahasiswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <h2>Tugas Mahasiswa</h2>

    <?php while ($t = mysqli_fetch_assoc($tugas)) { ?>
        <div class="card mb-3">
            <div class="card-body">
                <h5><?= htmlspecialchars($t['judul_tugas']) ?></h5>
                <p><?= nl2br(htmlspecialchars($t['deskripsi'])) ?></p>

                <strong>Pengumpulan:</strong>
                <ul>
                    <?php
                    $pengumpulan = mysqli_query($konek, "SELECT * FROM tb_pengumpulan WHERE tugas_id=" . $t['id']);
                    while ($p = mysqli_fetch_assoc($pengumpulan)) {
                        echo "<li>" . htmlspecialchars($p['mahasiswa_username']) .
                            " - <a href='uploads/" . htmlspecialchars($p['file_tugas']) . "' target='_blank'>Lihat File</a>" .
                            " ( " . $p['tanggal_kumpul'] . " )</li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
    <?php } ?>

    <a href="dashboardDosen.php" class="btn btn-secondary">Kembali</a>
</body>

</html>