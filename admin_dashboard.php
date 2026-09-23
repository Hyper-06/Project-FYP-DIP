<?php
session_start();
include 'db.php';

// Check if admin is logged in
if (!isset($_SESSION['username']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

// ---------------------------------------------------------
// FILTER INPUTS HANDLING (Tahun & Bulan)
// ---------------------------------------------------------
$selectedYear = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int) $_GET['tahun'] : '';
$selectedMonthStart = isset($_GET['bulan_mula']) && $_GET['bulan_mula'] !== '' ? (int) $_GET['bulan_mula'] : '';
$selectedMonthEnd = isset($_GET['bulan_tamat']) && $_GET['bulan_tamat'] !== '' ? (int) $_GET['bulan_tamat'] : '';

$whereClause = "WHERE 1=1";
$dateFilterSub = ""; // Used for subqueries if needed
if (!empty($selectedYear)) {
    $whereClause .= " AND YEAR(STR_TO_DATE(tarikh_laporan, '%Y-%m-%d %H:%i:%s')) = " . (int) $selectedYear;
    $dateFilterSub .= " AND YEAR(STR_TO_DATE(tarikh_laporan, '%Y-%m-%d %H:%i:%s')) = " . (int) $selectedYear;
}
if (!empty($selectedMonthStart) && !empty($selectedMonthEnd)) {
    $startMonth = min((int) $selectedMonthStart, (int) $selectedMonthEnd);
    $endMonth = max((int) $selectedMonthStart, (int) $selectedMonthEnd);
    $whereClause .= " AND MONTH(STR_TO_DATE(tarikh_laporan, '%Y-%m-%d %H:%i:%s')) BETWEEN " . $startMonth . " AND " . $endMonth;
    $dateFilterSub .= " AND MONTH(STR_TO_DATE(tarikh_laporan, '%Y-%m-%d %H:%i:%s')) BETWEEN " . $startMonth . " AND " . $endMonth;
}

$isFiltered = isset($_GET['tahun']) || isset($_GET['bulan_mula']) || isset($_GET['bulan_tamat']);

$jabatanLabels = []; $jabatanData = [];
$kategoriLabels = []; $kategoriData = [];
$lokasiLabels = []; $lokasiData = [];
$statusCounts = ['total' => 0, 'Baru' => 0, 'Dalam Tindakan' => 0, 'Selesai' => 0];
$trendLabels = []; $trendData = [];

$statusQuery = "SELECT COALESCE(NULLIF(status, ''), 'Baru') AS status_label, COUNT(*) AS total
                FROM tbllaporan
                $whereClause
                GROUP BY COALESCE(NULLIF(status, ''), 'Baru')";
$statusResult = mysqli_query($conn, $statusQuery);
if ($statusResult) {
    while ($row = mysqli_fetch_assoc($statusResult)) {
        $statusLabel = $row['status_label'];
        $statusTotal = (int) $row['total'];
        $statusCounts['total'] += $statusTotal;
        if (isset($statusCounts[$statusLabel])) {
            $statusCounts[$statusLabel] = $statusTotal;
        }
    }
}

$trendQuery = "SELECT DATE_FORMAT(STR_TO_DATE(tarikh_laporan, '%Y-%m-%d %H:%i:%s'), '%b %Y') AS label,
                      COUNT(*) AS total,
                      YEAR(STR_TO_DATE(tarikh_laporan, '%Y-%m-%d %H:%i:%s')) AS tahun,
                      MONTH(STR_TO_DATE(tarikh_laporan, '%Y-%m-%d %H:%i:%s')) AS bulan
               FROM tbllaporan
               $whereClause
               AND tarikh_laporan IS NOT NULL AND tarikh_laporan <> ''
               GROUP BY tahun, bulan, label
               ORDER BY tahun ASC, bulan ASC";
$trendResult = mysqli_query($conn, $trendQuery);
if ($trendResult) {
    while ($row = mysqli_fetch_assoc($trendResult)) {
        $trendLabels[] = $row['label'];
        $trendData[] = (int) $row['total'];
    }
}

// Load dashboard breakdowns using all records or the selected filters.
// 1. Statistik Jabatan
    $jabatanQuery = "
        SELECT COALESCE(tj.jabatan, 'Lain-lain') AS label, COUNT(*) AS total
        FROM tbllaporan tl
        LEFT JOIN tbljabatan tj ON tj.id_jabatan = tl.jabatan
        $whereClause
        GROUP BY COALESCE(tj.jabatan, 'Lain-lain')
        ORDER BY total DESC
    ";
    $jabatanResult = mysqli_query($conn, $jabatanQuery);
    if ($jabatanResult) {
        while ($row = mysqli_fetch_assoc($jabatanResult)) {
            $jabatanLabels[] = $row['label'] ?? 'Lain-lain';
            $jabatanData[] = (int) $row['total'];
        }
    }

    // 2. Statistik Kategori Aduan (Linked with category table / text values)
    $kategoriQuery = "
        SELECT COALESCE(NULLIF(tk.jenis, ''), NULLIF(tl.jenis_kerosakan, ''), 'Tidak Diketahui') AS label, COUNT(tl.id) AS total
        FROM tbllaporan tl
        LEFT JOIN tblkat_laporan tk ON tk.id = tl.kat_laporan
        $whereClause
        GROUP BY COALESCE(NULLIF(tk.jenis, ''), NULLIF(tl.jenis_kerosakan, ''), 'Tidak Diketahui')
        ORDER BY total DESC
    ";
    $kategoriResult = mysqli_query($conn, $kategoriQuery);
    if ($kategoriResult) {
        while ($row = mysqli_fetch_assoc($kategoriResult)) {
            $label = ($row['label'] !== null && trim($row['label']) !== '') ? $row['label'] : 'Tidak Diketahui';
            $total = (int) $row['total'];
            $kategoriLabels[] = $label;
            $kategoriData[] = $total;
        }
    }

    // 3. Statistik Lokasi Tempat
    $lokasiQuery = "
        SELECT COALESCE(lokasi, 'Tidak Dinyatakan') AS label, COUNT(*) AS total
        FROM tbllaporan
        $whereClause
        GROUP BY COALESCE(lokasi, 'Tidak Dinyatakan')
        ORDER BY total DESC
    ";
    $lokasiResult = mysqli_query($conn, $lokasiQuery);
    if ($lokasiResult) {
        while ($row = mysqli_fetch_assoc($lokasiResult)) {
            $lokasiLabels[] = $row['label'] ?? 'Tidak Dinyatakan';
            $lokasiData[] = (int) $row['total'];
        }
}

$yearsQuery = "
    SELECT DISTINCT YEAR(STR_TO_DATE(tarikh_laporan, '%Y-%m-%d %H:%i:%s')) AS tahun
    FROM tbllaporan
    WHERE tarikh_laporan IS NOT NULL AND tarikh_laporan <> ''
    ORDER BY tahun DESC
";
$yearsResult = mysqli_query($conn, $yearsQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sistem eHELPDESK PUO</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f4f6f9; font-family: Arial, sans-serif; }
        .sidebar { background: #1e293b; color: white; min-height: 100vh; padding: 20px; }
        .sidebar a { color: #94a3b8; text-decoration: none; display: block; padding: 12px 18px; border-radius: 12px; margin-bottom: 8px; transition: 0.2s; font-size: 0.95rem; }
        .sidebar a:hover { background-color: rgba(255, 255, 255, 0.05); color: #ffffff; }
        .sidebar a.active { background-color: #2563eb; color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .overview-card { min-height: 126px; overflow: hidden; position: relative; }
        .overview-card .card-body { position: relative; z-index: 1; }
        .overview-card .metric-value { font-size: 1.8rem; line-height: 1; font-weight: 700; }
        .overview-card .metric-label { color: #64748b; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .overview-card .metric-icon { font-size: 2.15rem; opacity: 0.18; position: absolute; right: 18px; top: 22px; }
        .accent-orange { border-top: 4px solid #f59e0b; }
        .accent-blue { border-top: 4px solid #2563eb; }
        .accent-pink { border-top: 4px solid #ef4444; }
        .accent-green { border-top: 4px solid #10b981; }
        .chart-container { position: relative; height: 330px; width: 100%; }
        .small-chart-container { position: relative; height: 270px; width: 100%; }
        .section-heading { font-size: 0.95rem; font-weight: 700; color: #1e293b; }
        .dashboard-subtitle { color: #64748b; font-size: 0.8rem; }
        .filter-section { background: #ffffff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px; }
        @media (max-width: 767.98px) {
            .chart-container { height: 280px; }
            .small-chart-container { height: 240px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar d-none d-md-block">
                <h4 class="fw-bold text-white mb-4 ps-2">eHELPDESK <span class="text-primary fs-6">PUO</span></h4>
                <a href="admin_dashboard.php" class="active"><i class="fa-solid fa-chart-line me-2"></i> Dashboard</a>
                <a href="admin_senarai.php"><i class="fa-solid fa-table-list me-2"></i> Senarai Aduan</a>
                <a href="admin_staf.php"><i class="fa-solid fa-users-gear me-2"></i> Pengurusan Staf</a>
                <a href="admin_kategori.php"><i class="fa-solid fa-list-check me-2"></i> Kategori Aduan</a>
                <a href="admin_cetak.php"><i class="fa-solid fa-print me-2"></i> Cetak Laporan</a>
                <a href="logout.php" class="text-danger mt-4"><i class="fa-solid fa-right-from-bracket me-2"></i> Log Keluar</a>
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">DASHBOARD</h3>
                        <p class="dashboard-subtitle mb-0">Ringkasan laporan dan aduan sistem eHELPDESK PUO.</p>
                    </div>
                    <div>
                        <a href="admin_senarai.php" class="btn btn-success btn-sm px-3 me-2"><i class="fa-solid fa-table-list me-1"></i> Lihat Senarai Aduan</a>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm px-3"><i class="fa-solid fa-right-from-bracket me-1"></i> Log Keluar</a>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card overview-card accent-orange">
                            <div class="card-body p-3">
                                <div class="metric-label mb-2">Jumlah Aduan</div>
                                <div class="metric-value text-dark"><?php echo number_format($statusCounts['total']); ?></div>
                                <small class="text-muted">Keseluruhan rekod</small>
                                <i class="fa-solid fa-chart-column metric-icon text-warning"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card overview-card accent-blue">
                            <div class="card-body p-3">
                                <div class="metric-label mb-2">Aduan Baru</div>
                                <div class="metric-value text-primary"><?php echo number_format($statusCounts['Baru']); ?></div>
                                <small class="text-muted">Menunggu tindakan</small>
                                <i class="fa-solid fa-file-circle-plus metric-icon text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card overview-card accent-pink">
                            <div class="card-body p-3">
                                <div class="metric-label mb-2">Dalam Tindakan</div>
                                <div class="metric-value text-danger"><?php echo number_format($statusCounts['Dalam Tindakan']); ?></div>
                                <small class="text-muted">Sedang diproses</small>
                                <i class="fa-solid fa-clipboard-list metric-icon text-danger"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card overview-card accent-green">
                            <div class="card-body p-3">
                                <div class="metric-label mb-2">Selesai</div>
                                <div class="metric-value text-success"><?php echo number_format($statusCounts['Selesai']); ?></div>
                                <small class="text-muted">Aduan ditutup</small>
                                <i class="fa-solid fa-circle-check metric-icon text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter Form with Global Chart Type Selector -->
                <div class="filter-section">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="tahun" class="form-label fw-semibold small">Tahun</label>
                            <select name="tahun" id="tahun" class="form-select form-select-sm">
                                <option value="">Sila Pilih Tahun</option>
                                <?php 
                                if ($yearsResult) {
                                    while($yRow = mysqli_fetch_assoc($yearsResult)) {
                                        $yr = $yRow['tahun'];
                                        $sel = ($selectedYear == $yr) ? 'selected' : '';
                                        echo "<option value=\"$yr\" $sel>$yr</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Bulan</label>
                            <div class="input-group input-group-sm">
                                <select name="bulan_mula" class="form-select">
                                    <option value="">Dari Bulan</option>
                                    <?php 
                                    $months = [1=>'Januari', 2=>'Februari', 3=>'Mac', 4=>'April', 5=>'Mei', 6=>'Jun', 7=>'Julai', 8=>'Ogos', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Disember'];
                                    foreach($months as $mNum => $mName) {
                                        $sel = ($selectedMonthStart == $mNum) ? 'selected' : '';
                                        echo "<option value=\"$mNum\" $sel>$mName</option>";
                                    }
                                    ?>
                                </select>
                                <span class="input-group-text bg-light text-muted">Sehingga</span>
                                <select name="bulan_tamat" class="form-select">
                                    <option value="">Hingga Bulan</option>
                                    <?php 
                                    foreach($months as $mNum => $mName) {
                                        $sel = ($selectedMonthEnd == $mNum) ? 'selected' : '';
                                        echo "<option value=\"$mNum\" $sel>$mName</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-5 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fa-solid fa-filter me-1"></i> Papar Statistik</button>
                            <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
                        </div>
                    </form>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-12 col-xl-8">
                        <div class="card h-100">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="section-heading">Trend Aduan</div>
                                        <div class="dashboard-subtitle">Jumlah laporan mengikut bulan</div>
                                    </div>
                                    <i class="fa-solid fa-chart-line text-primary"></i>
                                </div>
                                <div class="chart-container"><canvas id="trendChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="card h-100">
                            <div class="card-body p-4">
                                <div class="section-heading mb-1">Status Aduan</div>
                                <div class="dashboard-subtitle mb-3">Pecahan status semasa</div>
                                <div class="small-chart-container"><canvas id="statusChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-12 col-xl-6">
                        <div class="card h-100">
                            <div class="card-body p-4">
                                <div class="section-heading mb-1"><i class="fa-solid fa-screwdriver-wrench text-warning me-2"></i>Kategori Aduan</div>
                                <div class="dashboard-subtitle mb-3">Jumlah laporan mengikut kategori</div>
                                <div class="small-chart-container"><canvas id="kategoriChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="card h-100">
                            <div class="card-body p-4">
                                <div class="section-heading mb-1"><i class="fa-solid fa-location-dot text-danger me-2"></i>Lokasi Tempat</div>
                                <div class="dashboard-subtitle mb-3">Jumlah laporan mengikut lokasi</div>
                                <div class="small-chart-container"><canvas id="lokasiChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                        const palette = ['#2563eb', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#64748b', '#06b6d4', '#ec4899'];

                        const rawData = {
                            trend: {
                                labels: <?php echo json_encode($trendLabels); ?>,
                                data: <?php echo json_encode($trendData); ?>,
                                labelName: 'Jumlah Aduan'
                            },
                            status: {
                                labels: ['Baru', 'Dalam Tindakan', 'Selesai'],
                                data: [<?php echo (int) $statusCounts['Baru']; ?>, <?php echo (int) $statusCounts['Dalam Tindakan']; ?>, <?php echo (int) $statusCounts['Selesai']; ?>],
                                labelName: 'Status Aduan'
                            },
                            jabatan: {
                                labels: <?php echo json_encode($jabatanLabels); ?>,
                                data: <?php echo json_encode($jabatanData); ?>,
                                labelName: 'Jumlah Aduan Jabatan'
                            },
                            kategori: {
                                labels: <?php echo json_encode($kategoriLabels); ?>,
                                data: <?php echo json_encode($kategoriData); ?>,
                                labelName: 'Jumlah Aduan Kategori'
                            },
                            lokasi: {
                                labels: <?php echo json_encode($lokasiLabels); ?>,
                                data: <?php echo json_encode($lokasiData); ?>,
                                labelName: 'Jumlah Aduan Lokasi'
                            }
                        };

                        let charts = {};

                        function renderChart(chartKey, chartType) {
                            const ctx = document.getElementById(chartKey + 'Chart').getContext('2d');
                            
                            if (charts[chartKey]) {
                                charts[chartKey].destroy();
                            }

                            const isCircular = (chartType === 'pie' || chartType === 'doughnut');
                            const isLine = (chartType === 'line');

                            charts[chartKey] = new Chart(ctx, {
                                type: chartType,
                                data: {
                                    labels: rawData[chartKey].labels,
                                    datasets: [{
                                        label: rawData[chartKey].labelName,
                                        data: rawData[chartKey].data,
                                        backgroundColor: palette,
                                        borderColor: '#2563eb',
                                        borderWidth: isLine ? 2 : 1,
                                        tension: isLine ? 0.1 : 0,
                                        fill: false,
                                        borderRadius: isCircular ? 0 : 6
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    layout: {
                                        padding: {
                                            bottom: 15
                                        }
                                    },
                                    plugins: {
                                        legend: { 
                                            display: isCircular || chartKey === 'status', 
                                            position: 'bottom' 
                                        }
                                    },
                                    scales: isCircular ? {} : {
                                        x: {
                                            ticks: {
                                                maxRotation: 35,
                                                minRotation: 35,
                                                font: {
                                                    size: 11
                                                }
                                            },
                                            grid: {
                                                display: false
                                            }
                                        },
                                        y: { 
                                            beginAtZero: true, 
                                            ticks: { precision: 0 } 
                                        }
                                    }
                                }
                            });
                        }

                        function renderDashboardCharts() {
                            renderChart('trend', 'line');
                            renderChart('status', 'doughnut');
                            renderChart('kategori', 'pie');
                            renderChart('lokasi', 'bar');
                        }

                        renderDashboardCharts();
                </script>

            </div>
        </div>
    </div>
</body>
</html>