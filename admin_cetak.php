<?php
session_start();
include 'db.php';

if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch complaints/reports data from the database
$query = "SELECT tl.*, COALESCE(NULLIF(tk.jenis, ''), NULLIF(tl.jenis_kerosakan, ''), '-') AS nama_kategori
          FROM tbllaporan tl
          LEFT JOIN tblkat_laporan tk ON tk.id = tl.kat_laporan
          ORDER BY tl.id DESC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Laporan - Sistem eHELPDESK PUO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: Arial, sans-serif; }
        .sidebar { background: #1e293b; color: white; min-height: 100vh; padding: 20px; }
        .admin-logo { display: block; width: 155px; max-height: 100px; object-fit: contain; background: #fff; border-radius: 10px; padding: 10px; margin: 0 auto 18px; }
        .sidebar a { color: #94a3b8; text-decoration: none; display: block; padding: 12px 18px; border-radius: 12px; margin-bottom: 8px; transition: 0.2s; font-size: 0.95rem; }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.05); color: #ffffff; }
        .sidebar a.active { background-color: #2563eb; color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        
        /* Print styles: hides sidebar and buttons when printing to PDF/Paper */
        @media print {
            .sidebar, .no-print, .btn, form {
                display: none !important;
            }
            body {
                background-color: white !important;
            }
            .col-md-9, .col-lg-10, .ms-sm-auto {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: none !important;
            }
        }
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
                <a href="admin_senarai.php"><i class="fa-solid fa-table-list me-2"></i> Senarai Aduan</a>
                <a href="admin_staf.php"><i class="fa-solid fa-users-gear me-2"></i> Pengurusan Staf</a>
                <a href="admin_kategori.php"><i class="fa-solid fa-list-check me-2"></i> Kategori Aduan</a>
                <a href="admin_cetak.php" class="active"><i class="fa-solid fa-print me-2"></i> Cetak Laporan</a>
                <a href="logout.php" class="text-danger mt-4"><i class="fa-solid fa-right-from-bracket me-2"></i> Log Keluar</a>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">CETAK LAPORAN ADUAN</h3>
                        <p class="text-muted small mb-0">Jana dan cetak laporan rasmi sistem eHELPDESK PUO.</p>
                    </div>
                    <button onclick="window.print()" class="btn btn-primary fw-bold">
                        <i class="fa-solid fa-print me-2"></i> Cetak / Simpan PDF
                    </button>
                </div>

                <div class="card">
                    <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-file-lines me-2"></i> Senarai Rekod Laporan Rasmi</span>
                        <span class="small text-light d-none d-md-inline">Politeknik Ungku Omar</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center" style="width: 5%;">No.</th>
                                        <th style="width: 15%;">Tarikh</th>
                                        <th style="width: 20%;">Pengadu</th>
                                        <th style="width: 20%;">Kategori</th>
                                        <th style="width: 30%;">Butiran Aduan</th>
                                        <th class="text-center" style="width: 10%;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result && mysqli_num_rows($result) > 0) {
                                        $no = 1;
                                        while ($row = mysqli_fetch_assoc($result)) {
                                    ?>
                                            <tr>
                                                <td class="text-center"><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($row['tarikh_laporan'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($row['pelapor'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($row['nama_kategori'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($row['masalah'] ?? '-'); ?></td>
                                                <td class="text-center">
                                                    <?php 
                                                        $status = $row['status'] ?? 'Baru';
                                                        $badgeClass = 'bg-secondary';
                                                        if ($status == 'Selesai') $badgeClass = 'bg-success';
                                                        elseif ($status == 'Dalam Proses') $badgeClass = 'bg-warning text-dark';
                                                    ?>
                                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($status); ?></span>
                                                </td>
                                            </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="6" class="text-center text-muted py-4">Tiada data laporan ditemui dalam pangkalan data.</td></tr>';
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