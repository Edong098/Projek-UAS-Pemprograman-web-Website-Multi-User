<?php
session_start();
include "../dbKonek.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// === Tambah Dosen ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $username = trim(mysqli_real_escape_string($konek, $_POST['username']));
    $password = trim(mysqli_real_escape_string($konek, $_POST['password']));
    $nama_lengkap = trim(mysqli_real_escape_string($konek, $_POST['nama_lengkap']));
    $nidn = trim(mysqli_real_escape_string($konek, $_POST['nidn']));

    // Validasi kosong
    if (empty($username) || empty($password) || empty($nama_lengkap) || empty($nidn)) {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Semua kolom wajib diisi.'];
        header("Location: kelola_dosen.php");
        exit;
    }

    // Cek apakah username sudah digunakan siapa pun
    $cekUsername = mysqli_query($konek, "SELECT id FROM tb_user WHERE username = '$username'");
    if (mysqli_num_rows($cekUsername) > 0) {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Username sudah digunakan, silakan gunakan username lain.'];
        header("Location: kelola_dosen.php");
        exit;
    }

    // Simpan ke tb_user
    $insertUser = "INSERT INTO tb_user (username, password, role) VALUES ('$username', '$password', 'dosen')";
    if (mysqli_query($konek, $insertUser)) {
        $user_id = mysqli_insert_id($konek);

        // Simpan ke tb_dosen
        $insertDosen = "INSERT INTO tb_dosen (user_id, nama_lengkap, nidn) VALUES ($user_id, '$nama_lengkap', '$nidn')";
        if (mysqli_query($konek, $insertDosen)) {
            $_SESSION['pesan'] = ['type' => 'success', 'message' => 'Dosen berhasil ditambahkan!'];
        } else {
            $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal menyimpan ke tb_dosen: ' . mysqli_error($konek)];
        }
    } else {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal menambahkan user: ' . mysqli_error($konek)];
    }
    header("Location: kelola_dosen.php");
    exit;
}

// === Hapus Dosen ===
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    mysqli_query($konek, "DELETE FROM tb_dosen WHERE user_id = $id");
    if (mysqli_query($konek, "DELETE FROM tb_user WHERE id = $id AND role = 'dosen'")) {
        $_SESSION['pesan'] = ['type' => 'success', 'message' => 'Dosen berhasil dihapus!'];
    } else {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal menghapus dosen: ' . mysqli_error($konek)];
    }
    header("Location: kelola_dosen.php");
    exit;
}

// === Ambil Data untuk Edit ===
$edit_dosen = null;
if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    if ($edit_id > 0) {
        $query = "SELECT u.id, u.username, u.password, d.nama_lengkap, d.nidn
                  FROM tb_user u
                  JOIN tb_dosen d ON u.id = d.user_id
                  WHERE u.id = $edit_id AND u.role = 'dosen' LIMIT 1";
        $result = mysqli_query($konek, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            $edit_dosen = mysqli_fetch_assoc($result);
        } else {
            $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Data dosen tidak ditemukan.'];
            header("Location: kelola_dosen.php");
            exit;
        }
    } else {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'ID tidak valid.'];
        header("Location: kelola_dosen.php");
        exit;
    }
}

// === Update Dosen ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = (int) $_POST['id'];
    $username = trim(mysqli_real_escape_string($konek, $_POST['username']));
    $password = trim(mysqli_real_escape_string($konek, $_POST['password']));
    $nama_lengkap = trim(mysqli_real_escape_string($konek, $_POST['nama_lengkap']));
    $nidn = trim(mysqli_real_escape_string($konek, $_POST['nidn']));

    // Cek apakah username sudah digunakan oleh user lain
    $cekUsername = mysqli_query($konek, "SELECT id FROM tb_user WHERE username = '$username' AND id != $id");
    if (mysqli_num_rows($cekUsername) > 0) {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Username sudah digunakan, silakan gunakan username lain.'];
        header("Location: kelola_dosen.php");
        exit;
    }

    // Update tb_user
    if (!empty($password)) {
        $updateUser = "UPDATE tb_user SET username='$username', password='$password' WHERE id=$id AND role='dosen'";
    } else {
        $updateUser = "UPDATE tb_user SET username='$username' WHERE id=$id AND role='dosen'";
    }

    $updateDosen = "UPDATE tb_dosen SET nama_lengkap='$nama_lengkap', nidn='$nidn' WHERE user_id=$id";

    if (mysqli_query($konek, $updateUser) && mysqli_query($konek, $updateDosen)) {
        $_SESSION['pesan'] = ['type' => 'success', 'message' => 'Data dosen berhasil diperbarui!'];
    } else {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal memperbarui data dosen: ' . mysqli_error($konek)];
    }
    header("Location: kelola_dosen.php");
    exit;
}

// === Ambil Semua Dosen ===
$dosen = mysqli_query($konek, "
    SELECT u.id, u.username, u.password, d.nama_lengkap, d.nidn
    FROM tb_user u
    LEFT JOIN tb_dosen d ON u.id = d.user_id
    WHERE u.role = 'dosen'
    ORDER BY u.username ASC
");
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Dosen - Admin Panel</title>
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

        .table thead {
            background-color: #007bff;
            color: white;
        }

        .table tbody tr:hover {
            background-color: #e9ecef;
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
            <h3><i class="fas fa-chalkboard-teacher me-2"></i>Manajemen Dosen</h3>
            <a href="dashboardAdmin.php" class="btn btn-outline-secondary btn-sm"> kembali</a>
        </div>

        <?php
        // Menampilkan pesan notifikasi
        if (isset($_SESSION['pesan'])) {
            $alertType = $_SESSION['pesan']['type'];
            $alertMessage = $_SESSION['pesan']['message'];
            echo '<div class="alert alert-' . $alertType . ' alert-dismissible fade show" role="alert">';
            echo $alertMessage;
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            echo '</div>';
            unset($_SESSION['pesan']); // Hapus pesan setelah ditampilkan
        }
        ?>

        <div class="form-section">
            <h5 class="mb-4 text-primary">
                <?= $edit_dosen ? '<i class="fas fa-edit me-1"></i> Edit Data Dosen' : '<i class="fas fa-user-tie me-1"></i> Tambah Dosen Baru' ?>
            </h5>

            <form method="POST" class="row g-3">
                <input type="hidden" name="id" value="<?= $edit_dosen['id'] ?? ''; ?>">

                <!-- Username -->
                <div class="col-md-5">
                    <label for="username" class="form-label">Username Dosen</label>
                    <input type="text" name="username" id="username" class="form-control"
                        placeholder="Masukkan Username Dosen" required
                        value="<?= htmlspecialchars($edit_dosen['username'] ?? ''); ?>">
                </div>

                <!-- Password -->
                <div class="col-md-5">
                    <label for="password" class="form-label">
                        Password <?= $edit_dosen ? '<small class="text-muted">(Kosongkan jika tidak diubah)</small>' : '' ?>
                    </label>
                    <input type="password" name="password" id="password" class="form-control"
                        placeholder="<?= $edit_dosen ? 'Isi untuk mengubah password' : 'Masukkan Password' ?>"
                        <?= $edit_dosen ? '' : 'required' ?>>
                </div>

                <!-- Nama Lengkap -->
                <div class="col-md-5">
                    <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-control"
                        placeholder="Nama lengkap dosen" required
                        value="<?= htmlspecialchars($edit_dosen['nama_lengkap'] ?? ''); ?>">
                </div>

                <!-- NIDN -->
                <div class="col-md-5">
                    <label for="nidn" class="form-label">NIDN</label>
                    <input type="text" name="nidn" id="nidn" class="form-control"
                        placeholder="NIDN dosen" required
                        value="<?= htmlspecialchars($edit_dosen['nidn'] ?? ''); ?>">
                </div>

                <!-- Tombol Aksi -->
                <div class="col-md-10 d-flex justify-content-start gap-3 mt-3">
                    <?php if ($edit_dosen): ?>
                        <button type="submit" name="update" class="btn btn-warning">
                            <i class="fas fa-save me-1"></i> Update
                        </button>
                        <a href="kelola_dosen.php" class="btn btn-secondary">
                            <i class="fas fa-times-circle me-1"></i> Batal
                        </a>
                    <?php else: ?>
                        <button type="submit" name="tambah" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i> Tambah Dosen
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h4 class="mb-3 text-secondary"><i class="fas fa-list-alt me-2"></i>Daftar Dosen Terdaftar</h4>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($dosen) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($dosen)) : ?>
                            <tr>
                                <td><?= $row['id']; ?></td>
                                <td><?= htmlspecialchars($row['username']); ?></td>
                                <td><?= htmlspecialchars($row['password']); ?></td>
                                <td class="text-center">
                                    <a href="?edit=<?= $row['id']; ?>" class="btn btn-sm btn-info btn-action" title="Edit"><i class="fas fa-pencil-alt"></i> Edit</a>
                                    <a href="?hapus=<?= $row['id']; ?>" class="btn btn-sm btn-danger btn-action" title="Hapus"
                                        onclick="return confirm('Apakah Anda yakin ingin menghapus dosen ini? Tindakan ini tidak bisa dibatalkan!')"><i class="fas fa-trash-alt"></i> Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">Belum ada data dosen.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>