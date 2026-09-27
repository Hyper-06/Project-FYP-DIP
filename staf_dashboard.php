<?php
session_start();
require_once 'db.php';
require_once 'staf_guard.php';

$staffAccount = requireRegisteredStaff($conn);
$staffNo = (string) $staffAccount['no_kp'];
$staffName = (string) $staffAccount['nama'];
$staffPosition = (string) $staffAccount['jawatan'];
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'waiting', 'active', 'completed'], true)) {
    $filter = 'all';
}
$assignmentCondition = '(TRIM(pemeriksa) = ? OR TRIM(pemeriksa) = ?)';

$statsStatement = mysqli_prepare($conn, "SELECT COUNT(*) AS total,
    SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) AS completed,
    SUM(CASE WHEN status <> 'Selesai' AND (tarikh_pemeriksaan IS NULL OR TRIM(tarikh_pemeriksaan) = '') THEN 1 ELSE 0 END) AS waiting
    FROM tbllaporan WHERE $assignmentCondition");
$stats = ['total' => 0, 'completed' => 0, 'waiting' => 0];
if ($statsStatement) {
    mysqli_stmt_bind_param($statsStatement, 'ss', $staffNo, $staffName);
    mysqli_stmt_execute($statsStatement);
    $statsResult = mysqli_stmt_get_result($statsStatement);
    if ($statsResult) {
        $stats = mysqli_fetch_assoc($statsResult) ?: $stats;
    }
    mysqli_stmt_close($statsStatement);
}
$totalTasks = (int) ($stats['total'] ?? 0);
$completedTasks = (int) ($stats['completed'] ?? 0);
$waitingTasks = (int) ($stats['waiting'] ?? 0);
$activeTasks = max(0, $totalTasks - $completedTasks - $waitingTasks);

$filterSql = '';
if ($filter === 'waiting') {
    $filterSql = " AND status <> 'Selesai' AND (tarikh_pemeriksaan IS NULL OR TRIM(tarikh_pemeriksaan) = '')";
} elseif ($filter === 'active') {
    $filterSql = " AND status <> 'Selesai' AND tarikh_pemeriksaan IS NOT NULL AND TRIM(tarikh_pemeriksaan) <> ''";
} elseif ($filter === 'completed') {
    $filterSql = " AND status = 'Selesai'";
}

$countStatement = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM tbllaporan WHERE $assignmentCondition $filterSql");
$filteredTotal = 0;
if ($countStatement) {
    mysqli_stmt_bind_param($countStatement, 'ss', $staffNo, $staffName);
    mysqli_stmt_execute($countStatement);
    $countResult = mysqli_stmt_get_result($countStatement);
    if ($countResult) {
        $filteredTotal = (int) (mysqli_fetch_assoc($countResult)['total'] ?? 0);
    }
    mysqli_stmt_close($countStatement);
}

$perPage = 25;
$totalPages = max(1, (int) ceil($filteredTotal / $perPage));
$currentPage = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
$offset = ($currentPage - 1) * $perPage;
$listStatement = mysqli_prepare($conn, "SELECT id, tarikh_laporan, pelapor, lokasi, jenis_kerosakan, masalah, status, tarikh_pemeriksaan
    FROM tbllaporan WHERE $assignmentCondition $filterSql
    ORDER BY STR_TO_DATE(tarikh_laporan, '%Y-%m-%d %H:%i:%s') DESC LIMIT ? OFFSET ?");
$tasks = [];
if ($listStatement) {
    mysqli_stmt_bind_param($listStatement, 'ssii', $staffNo, $staffName, $perPage, $offset);
    mysqli_stmt_execute($listStatement);
    $taskResult = mysqli_stmt_get_result($listStatement);
    if ($taskResult) {
        while ($task = mysqli_fetch_assoc($taskResult)) {
            $tasks[] = $task;
        }
    }
    mysqli_stmt_close($listStatement);
}

function staffTaskStatus(array $task): string
{
    if (($task['status'] ?? '') === 'Selesai') {
        return 'Selesai';
    }
    if (trim((string) ($task['tarikh_pemeriksaan'] ?? '')) === '') {
        return 'Menunggu diterima';
    }
    return 'Dalam tindakan';
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugasan Staf - eHELPDESK PUO</title>
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
        .overview-card { min-height: 126px; overflow: hidden; position: relative; }
        .overview-card .card-body { position: relative; z-index: 1; }
        .metric-value { font-size: 1.8rem; line-height: 1; font-weight: 700; }
        .metric-label { color: #64748b; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .metric-icon { font-size: 2.15rem; opacity: 0.18; position: absolute; right: 18px; top: 22px; }
        .accent-orange { border-top: 4px solid #f59e0b; }
        .accent-blue { border-top: 4px solid #2563eb; }
        .accent-pink { border-top: 4px solid #ef4444; }
        .accent-green { border-top: 4px solid #10b981; }
        .task-table th { white-space: nowrap; }
        .task-issue { min-width: 220px; max-width: 360px; }
        .filter-section { background: #ffffff; border-radius: 12px; padding: 16px 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px; }
        .dashboard-subtitle { color: #64748b; font-size: 0.8rem; }
        @media (max-width: 767.98px) {
            .sidebar { min-height: auto; }
            .task-issue { min-width: 180px; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <aside class="col-md-3 col-lg-2 sidebar d-none d-md-block">
                <img src="puo_logo.png" alt="Logo PUO" class="admin-logo">
                <h4 class="fw-bold text-white mb-4 ps-2">eHELPDESK <span class="text-primary fs-6">PUO</span></h4>
                <a href="staf_dashboard.php" class="active"><i class="fa-solid fa-clipboard-list me-2"></i> Peti Tugasan</a>
                <a href="staf_logout.php" class="text-danger mt-4"><i class="fa-solid fa-right-from-bracket me-2"></i> Log Keluar</a>
            </aside>

            <main class="col-md-9 col-lg-10 ms-sm-auto px-4 py-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">PETI TUGASAN</h3>
                        <p class="dashboard-subtitle mb-0">Selamat kembali, <?php echo htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8'); ?> &middot; <?php echo htmlspecialchars($staffPosition, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <a href="staf_logout.php" class="btn btn-outline-danger btn-sm px-3"><i class="fa-solid fa-right-from-bracket me-1"></i> Log Keluar</a>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card overview-card accent-orange"><div class="card-body p-3">
                            <div class="metric-label mb-2">Semua Tugasan</div><div class="metric-value text-dark"><?php echo number_format($totalTasks); ?></div>
                            <small class="text-muted">Aduan ditugaskan kepada anda</small><i class="fa-solid fa-inbox metric-icon text-warning"></i>
                        </div></div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card overview-card accent-blue"><div class="card-body p-3">
                            <div class="metric-label mb-2">Belum Diterima</div><div class="metric-value text-primary"><?php echo number_format($waitingTasks); ?></div>
                            <small class="text-muted">Menunggu pengesahan</small><i class="fa-solid fa-hourglass-half metric-icon text-primary"></i>
                        </div></div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card overview-card accent-pink"><div class="card-body p-3">
                            <div class="metric-label mb-2">Dalam Tindakan</div><div class="metric-value text-danger"><?php echo number_format($activeTasks); ?></div>
                            <small class="text-muted">Sedang dikerjakan</small><i class="fa-solid fa-screwdriver-wrench metric-icon text-danger"></i>
                        </div></div>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="card overview-card accent-green"><div class="card-body p-3">
                            <div class="metric-label mb-2">Selesai</div><div class="metric-value text-success"><?php echo number_format($completedTasks); ?></div>
                            <small class="text-muted">Aduan ditutup</small><i class="fa-solid fa-circle-check metric-icon text-success"></i>
                        </div></div>
                    </div>
                </div>

                <div class="filter-section">
                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <span class="fw-semibold small text-secondary">Tapis tugasan</span>
                        <nav class="d-flex flex-wrap gap-2" aria-label="Tapis tugasan">
                            <a class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="staf_dashboard.php?filter=all">Semua</a>
                            <a class="btn btn-sm <?php echo $filter === 'waiting' ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="staf_dashboard.php?filter=waiting">Belum diterima</a>
                            <a class="btn btn-sm <?php echo $filter === 'active' ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="staf_dashboard.php?filter=active">Dalam tindakan</a>
                            <a class="btn btn-sm <?php echo $filter === 'completed' ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="staf_dashboard.php?filter=completed">Selesai</a>
                        </nav>
                    </div>
                </div>

                <section class="card">
                    <div class="card-body p-0">
                        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between p-3 border-bottom">
                            <div><div class="fw-bold">Senarai Tugasan</div><div class="dashboard-subtitle">Paparan <?php echo number_format(count($tasks)); ?> daripada <?php echo number_format($filteredTotal); ?> aduan</div></div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle task-table mb-0">
                                <thead class="table-light"><tr><th>ID</th><th>Tarikh</th><th>Pelapor</th><th>Lokasi</th><th>Masalah</th><th>Status</th><th class="text-end">Tindakan</th></tr></thead>
                                <tbody>
                                    <?php if (empty($tasks)): ?>
                                        <tr><td colspan="7" class="text-center text-muted py-5">Tiada tugasan dalam senarai ini.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($tasks as $task): ?>
                                            <?php $taskStatus = staffTaskStatus($task); ?>
                                            <tr>
                                                <td class="fw-semibold">#<?php echo htmlspecialchars((string) $task['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-nowrap"><?php echo htmlspecialchars($task['tarikh_laporan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($task['pelapor'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><?php echo htmlspecialchars($task['lokasi'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="task-issue"><?php echo htmlspecialchars($task['masalah'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td><span class="badge <?php echo $taskStatus === 'Selesai' ? 'bg-success' : ($taskStatus === 'Menunggu diterima' ? 'bg-warning text-dark' : 'bg-primary'); ?>"><?php echo htmlspecialchars($taskStatus, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                <td class="text-end"><a class="btn btn-sm btn-primary text-nowrap" href="staf_tugas.php?id=<?php echo (int) $task['id']; ?>"><i class="fa-solid fa-eye me-1"></i> Lihat Tugasan</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <nav class="d-flex justify-content-between align-items-center p-3 border-top" aria-label="Halaman tugasan">
                                <span class="small text-secondary">Halaman <?php echo $currentPage; ?> daripada <?php echo $totalPages; ?></span>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($currentPage > 1): ?><a class="btn btn-outline-secondary" href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo $currentPage - 1; ?>">Sebelumnya</a><?php endif; ?>
                                    <?php if ($currentPage < $totalPages): ?><a class="btn btn-outline-secondary" href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo $currentPage + 1; ?>">Seterusnya</a><?php endif; ?>
                                </div>
                            </nav>
                        <?php endif; ?>
                    </div>
                </section>
            </main>
        </div>
    </div>
</body>
</html>
