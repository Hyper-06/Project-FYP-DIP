<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$successMsg = '';
$errorMsg = '';
$categoryOptions = [];
$categoryResult = mysqli_query($conn, "SELECT id, jenis FROM tblkat_laporan ORDER BY id ASC");
if ($categoryResult) {
    while ($categoryRow = mysqli_fetch_assoc($categoryResult)) {
        $categoryOptions[] = $categoryRow;
    }
}

// Handle form submission when admin clicks "TUGASKAN"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tugaskan'])) {
    $selectedCategory = trim($_POST['category_id'] ?? '');
    $categoryId = 0;
    $jenis_kerosakan_tugas = '';
    $categoryFound = false;
    foreach ($categoryOptions as $categoryOption) {
        if ($categoryOption['jenis'] === $selectedCategory) {
            $categoryId = (int) $categoryOption['id'];
            $jenis_kerosakan_tugas = mysqli_real_escape_string($conn, $categoryOption['jenis']);
            $categoryFound = true;
            break;
        }
    }
    $pemeriksa = mysqli_real_escape_string($conn, $_POST['pemeriksa']);
    $catatan_admin = mysqli_real_escape_string($conn, $_POST['catatan_admin']);

    $updateQuery = "UPDATE tbllaporan SET 
                    kat_laporan = $categoryId,
                    jenis_kerosakan = '$jenis_kerosakan_tugas', 
                    pemeriksa = '$pemeriksa', 
                    catatan = '$catatan_admin',
                    status = 'Dalam Tindakan'
                    WHERE id = $id";
    
    if ($categoryFound && $jenis_kerosakan_tugas !== '' && mysqli_query($conn, $updateQuery)) {
        $successMsg = "Penugasan berjaya dikemaskini!";
    } elseif (!$categoryFound) {
        $errorMsg = "Sila pilih kategori kerosakan yang sah.";
    } else {
        $errorMsg = "Ralat semasa mengemaskini penugasan: " . mysqli_error($conn);
    }
}

// Fetch the specific complaint details
$query = "SELECT tl.*, COALESCE(NULLIF(tj.jabatan, ''), NULLIF(tl.lokasi, ''), '-') AS nama_jabatan,
                 COALESCE(NULLIF(tl.jenis_kerosakan, ''), NULLIF(tk.jenis, ''), '-') AS nama_kategori
          FROM tbllaporan tl 
          LEFT JOIN tbljabatan tj ON tj.id_jabatan = tl.jabatan 
          LEFT JOIN (SELECT id, MIN(jenis) AS jenis FROM tblkat_laporan GROUP BY id) tk ON tk.id = tl.kat_laporan
          WHERE tl.id = $id";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);

if (!$row) {
    echo "<script>alert('Laporan tidak dijumpai!'); window.location='admin_senarai.php';</script>";
    exit();
}

$staffQuery = "SELECT nama, jawatan FROM tbladmin
    WHERE COALESCE(jawatan, '') <> 'Pengguna'
    ORDER BY nama ASC";
$staffResult = mysqli_query($conn, $staffQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Butiran Laporan Kerosakan - Sistem eHELPDESK PUO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: Arial, sans-serif; }
        .sidebar { background: #1e293b; color: white; min-height: 100vh; padding: 20px; }
        .admin-logo { display: block; width: 155px; max-height: 100px; object-fit: contain; background: #fff; border-radius: 10px; padding: 10px; margin: 0 auto 18px; }
        .sidebar a { color: #94a3b8; text-decoration: none; display: block; padding: 12px 18px; border-radius: 12px; margin-bottom: 8px; transition: 0.2s; font-size: 0.95rem; }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.05); color: #ffffff; }
        .sidebar a.active { background-color: #2563eb; color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .table-custom th { background-color: #f8fafc; width: 25%; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar d-none d-md-block">
                <img src="puo_logo.png" alt="Logo PUO" class="admin-logo">
                <h4 class="fw-bold text-white mb-4 ps-2">eHELPDESK <span class="text-primary fs-6">PUO</span></h4>
                <a href="admin_dashboard.php"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</a>
                <a href="admin_senarai.php" class="active"><i class="fa-solid fa-table-list me-2"></i> Senarai Aduan</a>
                <a href="admin_staf.php"><i class="fa-solid fa-users-gear me-2"></i> Pengurusan Staf</a>
                <a href="admin_kategori.php"><i class="fa-solid fa-list-check me-2"></i> Kategori Aduan</a>
                <a href="admin_cetak.php"><i class="fa-solid fa-print me-2"></i> Cetak Laporan</a>
                <a href="logout.php" class="text-danger mt-4"><i class="fa-solid fa-right-from-bracket me-2"></i> Log Keluar</a>
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold text-dark mb-0">BUTIRAN LAPORAN KEROSAKAN</h3>
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

                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark fw-bold py-2">
                        <i class="fa-solid fa-user me-2"></i> Maklumat Pelapor
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-bordered table-custom mb-0">
                            <tr>
                                <th>Pelapor :</th>
                                <td colspan="3"><strong><?php echo htmlspecialchars(($row['pelapor'] ?? '') !== '0' && ($row['pelapor'] ?? '') !== '' ? $row['pelapor'] : '-'); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Emel :</th>
                                <td><?php echo htmlspecialchars(($row['emel'] ?? '') !== '0' && ($row['emel'] ?? '') !== '' ? $row['emel'] : '-'); ?></td>
                                <th>No. Tel :</th>
                                <td><?php echo htmlspecialchars(($row['ext'] ?? '') !== '0' && ($row['ext'] ?? '') !== '' ? $row['ext'] : '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Lokasi :</th>
                                <td><?php echo htmlspecialchars($row['lokasi'] ?? '-'); ?></td>
                                <th>Kategori Aduan :</th>
                                <td><?php echo htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Tarikh Lapor :</th>
                                <td colspan="3"><?php echo htmlspecialchars($row['tarikh_laporan'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark fw-bold py-2">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> Maklumat Masalah
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small">Lokasi Tempat:</label>
                            <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row['lokasi'] ?? ''); ?>" readonly>
                        </div>
                        <div class="mb-0">
                            <label class="form-label fw-semibold text-muted small">Masalah / Keterangan:</label>
                            <textarea class="form-control" rows="3" readonly><?php echo htmlspecialchars($row['masalah'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Assignment Form Matching Your Image -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark fw-bold py-2">
                        <i class="fa-solid fa-clipboard-list me-2"></i> Penugasan
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <label class="col-md-3 col-form-label fw-semibold">Kerosakan</label>
                                <div class="col-md-9">
                                    <select name="category_id" class="form-select" required>
                                        <option value="">Sila Pilih</option>
                                        <?php foreach ($categoryOptions as $categoryOption): ?>
                                            <option value="<?php echo htmlspecialchars($categoryOption['jenis']); ?>" <?php echo (($row['jenis_kerosakan'] ?? '') === $categoryOption['jenis']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoryOption['jenis']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-md-3 col-form-label fw-semibold">Pemeriksa / Pegawai Bertugas</label>
                                <div class="col-md-9">
                                    <select name="pemeriksa" class="form-select" required>
                                        <option value="">Sila Pilih</option>
                                        <?php 
                                        if ($staffResult) {
                                            while ($staf = mysqli_fetch_assoc($staffResult)) {
                                                $staffName = $staf['nama'];
                                                $selected = (isset($row['pemeriksa']) && $row['pemeriksa'] == $staffName) ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($staffName) . '" ' . $selected . '>' . htmlspecialchars($staffName) . ' (' . htmlspecialchars($staf['jawatan']) . ')</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label class="col-md-3 col-form-label fw-semibold">Catatan Admin</label>
                                <div class="col-md-9">
                                    <textarea name="catatan_admin" class="form-control" rows="2" placeholder="Masukkan arahan atau catatan tambahan (jika ada)"><?php echo htmlspecialchars($row['catatan'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-9 offset-md-3">
                                    <button type="submit" name="tugaskan" class="btn btn-primary px-4 fw-bold">TUGASKAN</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>