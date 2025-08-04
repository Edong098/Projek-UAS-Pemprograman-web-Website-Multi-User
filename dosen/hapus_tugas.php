<?php
session_start();
include "../dbKonek.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'dosen') {
    header("Location: ../login.php");
    exit;
}

$tugas_id = $_GET['tugas_id'] ?? 0;
$course_id = $_GET['course_id'] ?? 0;
$tugas_id = intval($tugas_id);
$course_id = intval($course_id);

if ($tugas_id === 0) {
    $_SESSION['message'] = "ID tugas tidak valid.";
    $_SESSION['message_type'] = "danger";
    header("Location: kelola_course.php?id=$course_id");
    exit;
}

mysqli_begin_transaction($konek);

try {
    // Ambil path file tugas jika ada
    $stmt_file = mysqli_prepare($konek, "SELECT file_path FROM tb_tugas WHERE id = ?");
    if (!$stmt_file) {
        throw new Exception("Gagal menyiapkan statement untuk mengambil file tugas: " . mysqli_error($konek));
    }
    mysqli_stmt_bind_param($stmt_file, "i", $tugas_id);
    mysqli_stmt_execute($stmt_file);
    $result = mysqli_stmt_get_result($stmt_file);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_file);

    $file_tugas = $data['file_path'] ?? null;

    // Hapus file fisik jika ada
    if ($file_tugas) {
        $path = "../assets/tugas/" . $file_tugas;
        if (file_exists($path)) {
            // Coba hapus file, jika gagal, catat error tapi jangan hentikan transaksi
            if (!unlink($path)) {
                error_log("Gagal menghapus file tugas: " . $path);
                // Kita bisa tambahkan pesan khusus jika penghapusan file gagal namun database tetap dihapus
                // Misalnya: $_SESSION['message'] = "Tugas berhasil dihapus, namun file terkait gagal dihapus.";
                // Untuk saat ini, kita biarkan pesan sukses jika database berhasil dihapus.
            }
        }
    }

    // Hapus pengumpulan tugas terkait
    $stmt_delete_pengumpulan = mysqli_prepare($konek, "DELETE FROM tb_pengumpulan WHERE tugas_id = ?");
    if (!$stmt_delete_pengumpulan) {
        throw new Exception("Gagal menyiapkan statement untuk menghapus pengumpulan: " . mysqli_error($konek));
    }
    mysqli_stmt_bind_param($stmt_delete_pengumpulan, "i", $tugas_id);
    mysqli_stmt_execute($stmt_delete_pengumpulan);
    mysqli_stmt_close($stmt_delete_pengumpulan);

    // Hapus tugas utama
    $stmt_delete_tugas = mysqli_prepare($konek, "DELETE FROM tb_tugas WHERE id = ?");
    if (!$stmt_delete_tugas) {
        throw new Exception("Gagal menyiapkan statement untuk menghapus tugas: " . mysqli_error($konek));
    }
    mysqli_stmt_bind_param($stmt_delete_tugas, "i", $tugas_id);
    mysqli_stmt_execute($stmt_delete_tugas);
    mysqli_stmt_close($stmt_delete_tugas);

    mysqli_commit($konek);

    // Pesan sukses yang lebih spesifik
} catch (Exception $e) {
    mysqli_rollback($konek);
    // Pesan error yang lebih ramah pengguna
    $_SESSION['message'] = "Terjadi kesalahan saat menghapus tugas. Silakan coba lagi nanti.";
    $_SESSION['message_type'] = "danger";
    // Opsional: Anda bisa tetap mencatat pesan teknis ke log server untuk debugging
    error_log("Error saat menghapus tugas ID $tugas_id: " . $e->getMessage());
}

mysqli_close($konek);
header("Location: kelola_course.php?id=$course_id");
exit;
