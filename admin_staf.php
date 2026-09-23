<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$successMsg = '';
$errorMsg = '';

$staffTableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'tblstaf'");
$hasStaffTable = $staffTableCheck && mysqli_num_rows($staffTableCheck) > 0;
$staffIdKey = $hasStaffTable ? 'id_staf' : 'id';
$staffNameKey = $hasStaffTable ? 'nama_staf' : 'nama';
$staffRoleKey = 'jawatan';

// Handle form submission to add new staff if the staff table exists
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_staf'])) {
    if (!$hasStaffTable) {
        $errorMsg = "Jadual staf tidak wujud dalam pangkalan data. Sila buat jadual tblstaf atau gunakan jadual admin sebagai sumber data staf.";
    } else {
        $nama_staf = mysqli_real_escape_string($conn, $_POST['nama_staf']);
        $jawatan = mysqli_real_escape_string($conn, $_POST['jawatan']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $telefon = mysqli_real_escape_string($conn, $_POST['telefon']);

        if (!empty($nama_staf)) {
            $insertQuery = "INSERT INTO tblstaf (nama_staf, jawatan, email, telefon) VALUES ('$nama_staf', '$jawatan', '$email', '$telefon')";
            if (mysqli_query($conn, $insertQuery)) {
                $successMsg = "Staf berjaya ditambah!";
            } else {
                $errorMsg = "Ralat semasa menambah staf: " . mysqli_error($conn);
            }
        } else {
            $errorMsg = "Nama staf tidak boleh kosong.";
        }
    }
}

// Handle staff deletion if the staff table exists
if (isset($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];
    if (!$hasStaffTable) {
        $errorMsg = "Pemadaman staf tidak boleh dilakukan kerana jadual tblstaf tidak wujud.";
    } else {
        $deleteQuery = "DELETE FROM tblstaf WHERE id_staf = $deleteId";
        if (mysqli_query($conn, $deleteQuery)) {
            header("Location: admin_staf.php?success=deleted");
            exit();
        } else {
            $errorMsg = "Ralat semasa memadam staf.";
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 'deleted') {
    $successMsg = "Staf berjaya dipadam!";
}

// Fetch all staff members from the real staff table if it exists; otherwise fallback to tbladmin
if ($hasStaffTable) {
    $staffQuery = "SELECT * FROM tblstaf ORDER BY nama_staf ASC";
} else {
    $staffQuery = "SELECT id, nama, jawatan FROM tbladmin WHERE nama IS NOT NULL AND nama <> '' ORDER BY nama ASC";
}
$staffResult = mysqli_query($conn, $staffQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengurusan Staf Bertugas - Sistem eHELPDESK PUO</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: Arial, sans-serif; }
        .sidebar { background: #1e293b; color: white; min-height: 100vh; padding: 20px; }
        .sidebar a { color: #94a3b8; text-decoration: none; display: block; padding: 12px 18px; border-radius: 12px; margin-bottom: 8px; transition: 0.2s; font-size: 0.95rem; }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.05); color: #ffffff; }
        .sidebar a.active { background-color: #2563eb; color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-md-3 col-lg-2 sidebar d-none d-md-block">
                <h4 class="fw-bold text-white mb-4 ps-2">eHELPDESK <span class="text-primary fs-6">PUO</span></h4>
                <a href="admin_dashboard.php"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</a>
                <a href="admin_senarai.php"><i class="fa-solid fa-table-list me-2"></i> Senarai Aduan</a>
                <a href="admin_staf.php" class="active"><i class="fa-solid fa-users-gear me-2"></i> Pengurusan Staf</a>
                <a href="admin_kategori.php"><i class="fa-solid fa-list-check me-2"></i> Kategori Aduan</a>
                <a href="admin_cetak.php"><i class="fa-solid fa-print me-2"></i> Cetak Laporan</a>
                <a href="logout.php" class="text-danger mt-4"><i class="fa-solid fa-right-from-bracket me-2"></i> Log Keluar</a>
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">PENGURUSAN STAF / PEGAWAI BERTUGAS</h3>
                        <p class="text-muted small mb-0">Tambah dan uruskan senarai pegawai yang bertugas untuk rujukan penugasan aduan.</p>
                    </div>
                    <a href="admin_senarai.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Senarai</a>
                </div>

                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-check-circle me-2"></i> <?php echo $successMsg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $errorMsg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$hasStaffTable): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fa-solid fa-circle-info me-2"></i> Jadual staf khas belum wujud dalam pangkalan data. Sistem sedang menggunakan senarai admin sedia ada sebagai rujukan sementara.
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Form to Add Staff -->
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white fw-bold py-3">
                                <i class="fa-solid fa-user-plus me-2"></i> Tambah Staf Baru
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small">Nama Staf / Pegawai</label>
                                        <input type="text" name="nama_staf" class="form-control" placeholder="Cth: Encik Ahmad" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small">Jawatan / Unit</label>
                                        <input type="text" name="jawatan" class="form-control" placeholder="Cth: Teknisi Elektrik">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small">Emel</label>
                                        <input type="email" name="email" class="form-control" placeholder="cth@puo.edu.my">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small">No. Telefon</label>
                                        <input type="text" name="telefon" class="form-control" placeholder="0123456789">
                                    </div>
                                    <button type="submit" name="tambah_staf" class="btn btn-success w-100 fw-bold" <?php echo $hasStaffTable ? '' : 'disabled'; ?>>
                                        <i class="fa-solid fa-save me-1"></i> Simpan Staf
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Staff Table List -->
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header bg-dark text-white fw-bold py-3">
                                <i class="fa-solid fa-users me-2"></i> Senarai Staf In-Charge
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>No.</th>
                                                <th>Nama Staf</th>
                                                <th>Jawatan</th>
                                                <th>Emel / Tel</th>
                                                <th class="text-center">Tindakan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            if ($staffResult && mysqli_num_rows($staffResult) > 0) {
                                                $no = 1;
                                                while ($staf = mysqli_fetch_assoc($staffResult)) {
                                                    $staffName = $hasStaffTable ? ($staf['nama_staf'] ?? '-') : ($staf['nama'] ?? '-');
                                                    $staffPosition = $staf['jawatan'] ?? '-';
                                                    $staffEmail = $hasStaffTable ? ($staf['email'] ?? '-') : '-';
                                                    $staffPhone = $hasStaffTable ? ($staf['telefon'] ?? '-') : '-';
                                                    $staffId = $staf[$staffIdKey] ?? 0;
                                            ?>
                                                    <tr>
                                                        <td><?php echo $no++; ?></td>
                                                        <td><strong><?php echo htmlspecialchars($staffName); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($staffPosition); ?></td>
                                                        <td>
                                                            <small class="d-block text-muted"><?php echo htmlspecialchars($staffEmail); ?></small>
                                                            <small class="d-block text-muted"><?php echo htmlspecialchars($staffPhone); ?></small>
                                                        </td>
                                                        <td class="text-center">
                                                            <a href="admin_staf.php?delete_id=<?php echo $staffId; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Adakah anda pasti mahu memadam staf ini?');" <?php echo $hasStaffTable ? '' : 'style="pointer-events:none; opacity:0.5;"'; ?>>
                                                                <i class="fa-solid fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                            <?php 
                                                }
                                            } else {
                                                echo '<tr><td colspan="5" class="text-center text-muted py-4">Tiada rekod staf ditemui. Sila tambah staf di sebelah.</td></tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>