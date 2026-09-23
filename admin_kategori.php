<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$successMsg = '';
$errorMsg = '';

$categoryTableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'tblkat_laporan'");
$hasCategoryTable = $categoryTableCheck && mysqli_num_rows($categoryTableCheck) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_kategori'])) {
    if (!$hasCategoryTable) {
        $errorMsg = "Jadual kategori aduan tidak wujud dalam pangkalan data.";
    } else {
        $namaKategori = trim($_POST['nama_kategori'] ?? '');

        if ($namaKategori === '') {
            $errorMsg = "Nama kategori tidak boleh kosong.";
        } else {
            $safeNamaKategori = mysqli_real_escape_string($conn, $namaKategori);
            $insertQuery = "INSERT INTO tblkat_laporan (jenis) VALUES ('$safeNamaKategori')";

            if (mysqli_query($conn, $insertQuery)) {
                $successMsg = "Kategori aduan berjaya ditambah.";
            } else {
                $errorMsg = "Ralat semasa menambah kategori: " . mysqli_error($conn);
            }
        }
    }
}

if (isset($_GET['delete_id'])) {
    $deleteId = (int) $_GET['delete_id'];

    if (!$hasCategoryTable) {
        $errorMsg = "Pemadaman kategori tidak boleh dilakukan kerana jadual tidak wujud.";
    } else {
        $deleteQuery = "DELETE FROM tblkat_laporan WHERE id = $deleteId";
        if (mysqli_query($conn, $deleteQuery)) {
            header("Location: admin_kategori.php?success=deleted");
            exit();
        } else {
            $errorMsg = "Ralat semasa memadam kategori.";
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == 'deleted') {
    $successMsg = "Kategori berjaya dipadam.";
}

if ($hasCategoryTable) {
    $categoryQuery = "SELECT * FROM tblkat_laporan ORDER BY id ASC";
    $categoryResult = mysqli_query($conn, $categoryQuery);
} else {
    $categoryResult = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Aduan - Sistem eHELPDESK PUO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: Arial, sans-serif; }
        .sidebar { background: #1e293b; color: white; min-height: 100vh; padding: 20px; }
        .sidebar a { color: #94a3b8; text-decoration: none; display: block; padding: 12px 18px; border-radius: 12px; margin-bottom: 8px; transition: 0.2s; font-size: 0.95rem; }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.05); color: #ffffff; }
        .sidebar a.active { background-color: #2563eb; color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar with Cetak Laporan added -->
            <div class="col-md-3 col-lg-2 sidebar d-none d-md-block">
                <h4 class="fw-bold text-white mb-4 ps-2">eHELPDESK <span class="text-primary fs-6">PUO</span></h4>
                <a href="admin_dashboard.php"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</a>
                <a href="admin_senarai.php"><i class="fa-solid fa-table-list me-2"></i> Senarai Aduan</a>
                <a href="admin_staf.php"><i class="fa-solid fa-users-gear me-2"></i> Pengurusan Staf</a>
                <a href="admin_kategori.php" class="active"><i class="fa-solid fa-list-check me-2"></i> Kategori Aduan</a>
                <a href="admin_cetak.php"><i class="fa-solid fa-print me-2"></i> Cetak Laporan</a>
                <a href="logout.php" class="text-danger mt-4"><i class="fa-solid fa-right-from-bracket me-2"></i> Log Keluar</a>
            </div>

            <div class="col-md-9 col-lg-10 ms-sm-auto px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">PENGURUSAN KATEGORI ADUAN</h3>
                        <p class="text-muted small mb-0">Tambah, semak dan padam kategori aduan yang dipaparkan di dalam borang laporan.</p>
                    </div>
                    <a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm px-3"><i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard</a>
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

                <?php if (!$hasCategoryTable): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fa-solid fa-circle-info me-2"></i> Jadual kategori aduan belum wujud. Sila cipta jadual <strong>tblkat_laporan</strong> dengan lajur <strong>id</strong> dan <strong>jenis</strong>.
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white fw-bold py-3">
                                <i class="fa-solid fa-plus me-2"></i> Tambah Kategori Baru
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small">Nama Kategori</label>
                                        <input type="text" name="nama_kategori" class="form-control" placeholder="Contoh: Elektrik" required <?php echo $hasCategoryTable ? '' : 'disabled'; ?>>
                                    </div>
                                    <button type="submit" name="tambah_kategori" class="btn btn-success w-100 fw-bold" <?php echo $hasCategoryTable ? '' : 'disabled'; ?>>
                                        <i class="fa-solid fa-save me-1"></i> Simpan Kategori
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header bg-dark text-white fw-bold py-3">
                                <i class="fa-solid fa-list me-2"></i> Senarai Kategori Aduan
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>No.</th>
                                                <th>Nama Kategori</th>
                                                <th class="text-center">Tindakan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($categoryResult && mysqli_num_rows($categoryResult) > 0) {
                                                $no = 1;
                                                while ($row = mysqli_fetch_assoc($categoryResult)) {
                                                    $categoryId = (int) ($row['id'] ?? 0);
                                                    $categoryName = $row['jenis'] ?? '-';
                                            ?>
                                                    <tr>
                                                        <td><?php echo $no++; ?></td>
                                                        <td><strong><?php echo htmlspecialchars($categoryName); ?></strong></td>
                                                        <td class="text-center">
                                                            <a href="admin_kategori.php?delete_id=<?php echo $categoryId; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Adakah anda pasti mahu memadam kategori ini?');">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                            <?php
                                                }
                                            } else {
                                                echo '<tr><td colspan="3" class="text-center text-muted py-4">Tiada kategori aduan ditemui.</td></tr>';
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>