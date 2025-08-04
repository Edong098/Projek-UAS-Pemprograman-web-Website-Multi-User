<?php
session_start();
include "../dbKonek.php";

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// === Tambah User ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah'])) {
    $username = mysqli_real_escape_string($konek, $_POST['username']);
    $password = mysqli_real_escape_string($konek, $_POST['password']);
    $role = mysqli_real_escape_string($konek, $_POST['role']);

    // Simpan password secara langsung tanpa hashing
    $insertQuery = "INSERT INTO tb_user (username, password, role) VALUES ('$username', '$password', '$role')";

    if (mysqli_query($konek, $insertQuery)) {
        $_SESSION['pesan'] = ['type' => 'success', 'message' => 'User berhasil ditambahkan!'];
    } else {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal menambahkan user: ' . mysqli_error($konek)];
    }
    header("Location: manajemen_user.php");
    exit;
}


// === Edit User ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = (int) $_POST['id'];
    $username = mysqli_real_escape_string($konek, $_POST['username']);
    $password = mysqli_real_escape_string($konek, $_POST['password']);
    $role = mysqli_real_escape_string($konek, $_POST['role']);

    // Cek apakah password diubah atau tidak
    if (!empty($password)) {
        // TANPA enkripsi, langsung gunakan nilai password
        $updateQuery = "UPDATE tb_user SET username='$username', password='$password', role='$role' WHERE id=$id";
    } else {
        $updateQuery = "UPDATE tb_user SET username='$username', role='$role' WHERE id=$id";
    }

    if (mysqli_query($konek, $updateQuery)) {
        $_SESSION['pesan'] = ['type' => 'success', 'message' => 'User berhasil diperbarui!'];
    } else {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal memperbarui user: ' . mysqli_error($konek)];
    }
    header("Location: manajemen_user.php");
    exit;
}

if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    if (mysqli_query($konek, "DELETE FROM tb_user WHERE id = $id")) {
        $_SESSION['pesan'] = ['type' => 'success', 'message' => 'User berhasil dihapus!'];
    } else {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Gagal menghapus user: ' . mysqli_error($konek)];
    }

    header("Location: manajemen_user.php");
    exit;
}



// === Ambil Data untuk Edit ===
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $resultEdit = mysqli_query($konek, "SELECT * FROM tb_user WHERE id = $id");
    if ($resultEdit) {
        $editData = mysqli_fetch_assoc($resultEdit);
    } else {
        $_SESSION['pesan'] = ['type' => 'danger', 'message' => 'Data user tidak ditemukan.'];
        header("Location: manajemen_user.php");
        exit;
    }
}

// === Ambil Semua User ===
$user = mysqli_query($konek, "SELECT * FROM tb_user ORDER BY role ASC");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - Admin Panel</title>
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
            <h3><i class="fas fa-users me-2"></i>Manajemen User</h3>
            <a href="dashboardAdmin.php" class="btn btn-outline-secondary btn-sm"> Kembali</a>
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
            <h5 class="mb-4 text-primary"><?= $editData ? '<i class="fas fa-edit me-1"></i> Edit User' : '<i class="fas fa-user-plus me-1"></i> Tambah User Baru' ?></h5>
            <form method="POST" class="row g-3">
                <input type="hidden" name="id" value="<?= $editData['id'] ?? '' ?>">
                <div class="col-md-4">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Masukkan Username" value="<?= htmlspecialchars($editData['username'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="password" class="form-label">Password <?= $editData ? '<small class="text-muted">(Kosongkan jika tidak diubah)</small>' : '' ?></label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="<?= $editData ? 'Isi untuk mengubah password' : 'Masukkan Password' ?>" <?= $editData ? '' : 'required' ?>>
                </div>
                <div class="col-md-4">
                    <label for="role" class="form-label">Role</label>
                    <select name="role" id="role" class="form-select" required>
                        <option value="">-- Pilih Role --</option>
                        <option value="admin" <?= isset($editData) && $editData['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="dosen" <?= isset($editData) && $editData['role'] === 'dosen' ? 'selected' : '' ?>>Dosen</option>
                        <option value="mahasiswa" <?= isset($editData) && $editData['role'] === 'mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
                    </select>
                </div>
                <div class="col-12 mt-4">
                    <?php if ($editData): ?>
                        <button type="submit" name="update" class="btn btn-warning me-2"><i class="fas fa-save me-1"></i>Update User</button>
                        <a href="manajemen_user.php" class="btn btn-secondary"><i class="fas fa-times-circle me-1"></i>Batal Edit</a>
                    <?php else: ?>
                        <button type="submit" name="tambah" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i>Tambah User</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <h4 class="mb-3 text-secondary"><i class="fas fa-list me-2"></i>Daftar Pengguna Sistem</h4>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Password</th> <!-- Tambahan -->
                        <th>Role</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($user) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($user)): ?>
                            <tr>
                                <td><?= $row['id']; ?></td>
                                <td><?= htmlspecialchars($row['username']); ?></td>
                                <td><?= htmlspecialchars($row['password']); ?></td> <!-- Tampilkan password -->
                                <td>
                                    <span class="badge 
                        <?php
                            if ($row['role'] === 'admin') echo 'bg-danger';
                            else if ($row['role'] === 'dosen') echo 'bg-info';
                            else echo 'bg-success';
                        ?>">
                                        <?= ucfirst($row['role']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="?edit=<?= $row['id']; ?>" class="btn btn-sm btn-info btn-action" title="Edit">
                                        <i class="fas fa-pencil-alt"></i>
                                    </a>
                                    <a href="?hapus=<?= $row['id']; ?>" class="btn btn-sm btn-danger btn-action" title="Hapus"
                                        onclick="return confirm('Apakah Anda yakin ingin menghapus user ini? Tindakan ini tidak bisa dibatalkan!')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">Tidak ada data user.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>