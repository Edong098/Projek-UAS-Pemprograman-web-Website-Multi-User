<?php
session_start();
include "../dbKonek.php"; // Pastikan path ke dbKonek.php sudah benar dan koneksi berhasil

// 1. Verifikasi Sesi dan Role Pengguna
// Pastikan pengguna sudah login dan memiliki role 'dosen'
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'dosen') {
    header("Location: ../login.php");
    exit;
}

// 2. Amankan dan Validasi Input
// Gunakan operator null coalescing (??) untuk mendapatkan ID atau nilai default 0
$materi_id = $_GET['materi_id'] ?? 0;
$course_id = $_GET['course_id'] ?? 0;

// Pastikan ID adalah integer untuk keamanan tambahan
$materi_id = (int)$materi_id;
$course_id = (int)$course_id;

$file_to_delete_name = null; // Untuk menyimpan nama file dari database
$full_file_path = null;     // Untuk menyimpan path lengkap ke file fisik di server

$stmt_select = null; // Inisialisasi statement SELECT
$stmt_delete = null; // Inisialisasi statement DELETE

try {
    // 3. Ambil data file_path dari database sebelum menghapus record
    // Menggunakan Prepared Statement untuk mencegah SQL Injection
    $stmt_select = mysqli_prepare($konek, "SELECT file_path FROM tb_materi WHERE id = ?");
    if (!$stmt_select) {
        throw new Exception("Gagal menyiapkan statement SELECT: " . mysqli_error($konek));
    }
    mysqli_stmt_bind_param($stmt_select, "i", $materi_id);
    mysqli_stmt_execute($stmt_select);
    $result_select = mysqli_stmt_get_result($stmt_select);
    $data = mysqli_fetch_assoc($result_select);

    // Dapatkan nama file jika ada
    if ($data) {
        $file_to_delete_name = $data['file_path'];
    }

    // 4. Hapus file fisik dari folder jika ada
    // **** PENTING: Sesuaikan direktori dasar upload file Anda di sini ****
    // Contoh: Jika file Anda disimpan di `sistem_kuliah/assets/materi/`, maka path relatifnya adalah `../assets/materi/`
    $base_upload_dir = "../assets/materi/";

    if ($file_to_delete_name && !empty($file_to_delete_name)) {
        $full_file_path = $base_upload_dir . $file_to_delete_name;

        // Periksa apakah file ada dan memang file (bukan direktori) sebelum dihapus
        if (file_exists($full_file_path) && is_file($full_file_path)) {
            if (!unlink($full_file_path)) {
                // Jika gagal menghapus file, catat error (misalnya karena izin)
                error_log("Gagal menghapus file fisik materi: " . $full_file_path . " (Periksa izin folder!)");
                $_SESSION['error_message'] = "Materi berhasil dihapus dari database, tetapi gagal menghapus file terkait dari server. Mungkin masalah izin.";
            } else {
                $_SESSION['success_message'] = "Materi dan file terkait berhasil dihapus.";
            }
        } else {
            // File tidak ditemukan, mungkin sudah terhapus atau path salah
            error_log("File materi tidak ditemukan di path: " . $full_file_path);
            $_SESSION['warning_message'] = "Materi berhasil dihapus dari database, tetapi file terkait tidak ditemukan di server.";
        }
    } else {
        // Tidak ada file_path untuk materi ini di database
        $_SESSION['warning_message'] = "Materi berhasil dihapus dari database. Tidak ada file terkait yang perlu dihapus.";
    }

    // 5. Hapus record dari database
    // Menggunakan Prepared Statement untuk mencegah SQL Injection
    $stmt_delete = mysqli_prepare($konek, "DELETE FROM tb_materi WHERE id = ?");
    if (!$stmt_delete) {
        throw new Exception("Gagal menyiapkan statement DELETE: " . mysqli_error($konek));
    }
    mysqli_stmt_bind_param($stmt_delete, "i", $materi_id);
    mysqli_stmt_execute($stmt_delete);

    // Periksa apakah ada baris yang terpengaruh (terhapus) dari database
    if (mysqli_stmt_affected_rows($stmt_delete) == 0 && !isset($_SESSION['success_message']) && !isset($_SESSION['warning_message']) && !isset($_SESSION['error_message'])) {
        // Jika tidak ada baris terhapus DAN belum ada pesan yang diset (misal: file tidak ditemukan), set pesan error
        $_SESSION['error_message'] = "Materi tidak ditemukan atau gagal dihapus dari database.";
    }
} catch (Exception $e) {
    // Tangani error database atau kesalahan lainnya
    error_log("Kesalahan dalam hapus_materi.php: " . $e->getMessage()); // Catat error ke log server
    $_SESSION['error_message'] = "Terjadi kesalahan sistem saat menghapus materi.";
} finally {
    // Pastikan semua prepared statements ditutup
    if ($stmt_select) {
        mysqli_stmt_close($stmt_select);
    }
    if ($stmt_delete) {
        mysqli_stmt_close($stmt_delete);
    }
    // Menutup koneksi database di akhir skrip
    if ($konek) {
        mysqli_close($konek);
    }
}

// 6. Redirect kembali ke halaman kelola_course.php dengan course_id
header("Location: kelola_course.php?id=" . $course_id);
exit;
