<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch all complaints from the database
$query = "SELECT tl.*, COALESCE(NULLIF(tj.jabatan, ''), NULLIF(tl.lokasi, ''), '-') AS nama_jabatan 
          FROM tbllaporan tl 
          LEFT JOIN tbljabatan tj ON tj.id_jabatan = tl.jabatan 
          ORDER BY tl.id DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Senarai Aduan - Sistem eHELPDESK PUO</title>
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
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar d-none d-md-block">
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
                    <div>
                        <h3 class="fw-bold text-dark mb-1">SENARAI KESELURUHAN ADUAN</h3>
                        <p class="text-muted small mb-0">Semak senarai aduan pelapor dan lakukan penugasan tugas.</p>
                    </div>
                    <div>
                        <a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm px-3"><i class="fa-solid fa-chart-line me-1"></i> Kembali ke Dashboard</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No.</th>
                                        <th>Tarikh</th>
                                        <th>Pelapor</th>
                                        <th>Jabatan / Lokasi</th>
                                        <th>Lokasi</th>
                                        <th>Status Tugas</th>
                                        <th class="text-center">Tindakan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($result && mysqli_num_rows($result) > 0) {
                                        $no = 1;
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            $status = isset($row['status']) && $row['status'] !== '' ? $row['status'] : 'Baru';
                                            $badgeColor = ($status == 'Dalam Tindakan') ? 'bg-warning text-dark' : (($status == 'Selesai') ? 'bg-success' : 'bg-secondary');
                                    ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($row['tarikh_laporan'] ?? '-'); ?></td>
                                                <td><strong><?php echo htmlspecialchars(($row['pelapor'] ?? '') !== '0' && ($row['pelapor'] ?? '') !== '' ? $row['pelapor'] : '-'); ?></strong></td>
                                                <td><?php echo htmlspecialchars($row['nama_jabatan'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($row['lokasi'] ?? '-'); ?></td>
                                                <td><span class="badge <?php echo $badgeColor; ?>"><?php echo $status; ?></span></td>
                                                <td class="text-center">
                                                    <a href="admin_view.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fa-solid fa-pen-to-square me-1"></i> Lihat & Tugaskan
                                                    </a>
                                                </td>
                                            </tr>
                                    <?php 
                                        }
                                    } else {
                                        echo '<tr><td colspan="7" class="text-center text-muted py-4">Tiada aduan ditemui.</td></tr>';
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>