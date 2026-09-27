<?php
session_start();
require_once 'db.php';
require_once 'staf_guard.php';

$staffAccount = requireRegisteredStaff($conn);
$staffNo = (string) $staffAccount['no_kp'];
$staffName = (string) $staffAccount['nama'];
$taskId = (int) ($_GET['id'] ?? 0);
$error = '';
$success = $_SESSION['staf_flash'] ?? '';
unset($_SESSION['staf_flash']);

if (empty($_SESSION['csrf_staf'])) {
    $_SESSION['csrf_staf'] = bin2hex(random_bytes(32));
}

$taskStatement = mysqli_prepare($conn, "SELECT id, tarikh_laporan, tarikh_diterima, tarikh_assign, tarikh_pemeriksaan,
    pelapor, emel, ext, lokasi, jenis_kerosakan, masalah, status, pemeriksa, catatan,
    tindakan_pembaikan, tarikh_pembaikan
    FROM tbllaporan WHERE id = ? AND (TRIM(pemeriksa) = ? OR TRIM(pemeriksa) = ?) LIMIT 1");
$task = null;
if ($taskStatement) {
    mysqli_stmt_bind_param($taskStatement, 'iss', $taskId, $staffNo, $staffName);
    mysqli_stmt_execute($taskStatement);
    $taskResult = mysqli_stmt_get_result($taskStatement);
    $task = $taskResult ? mysqli_fetch_assoc($taskResult) : null;
    mysqli_stmt_close($taskStatement);
}

if (!$task) {
    http_response_code(404);
    $error = 'Tugasan tidak dijumpai atau bukan ditugaskan kepada akaun anda.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $workNotes = trim($_POST['tindakan_pembaikan'] ?? '');
    $now = date('Y-m-d H:i:s');

    if (!hash_equals($_SESSION['csrf_staf'], $token)) {
        $error = 'Sesi borang tamat. Muat semula halaman dan cuba sekali lagi.';
    } elseif (($task['status'] ?? '') === 'Selesai') {
        $error = 'Tugasan ini telah ditandakan selesai.';
    } elseif ($action === 'receive') {
        $updateStatement = mysqli_prepare($conn, "UPDATE tbllaporan
            SET status = 'Dalam Tindakan',
                tarikh_pemeriksaan = CASE WHEN tarikh_pemeriksaan IS NULL OR TRIM(tarikh_pemeriksaan) = '' THEN ? ELSE tarikh_pemeriksaan END
            WHERE id = ? AND (TRIM(pemeriksa) = ? OR TRIM(pemeriksa) = ?)");
        if ($updateStatement) {
            mysqli_stmt_bind_param($updateStatement, 'siss', $now, $taskId, $staffNo, $staffName);
            if (mysqli_stmt_execute($updateStatement)) {
                $_SESSION['staf_flash'] = 'Tugasan berjaya diterima.';
                mysqli_stmt_close($updateStatement);
                header('Location: staf_tugas.php?id=' . $taskId);
                exit();
            }
            $error = 'Tugasan tidak dapat dikemas kini. Sila cuba lagi.';
            mysqli_stmt_close($updateStatement);
        } else {
            $error = 'Tugasan tidak dapat dikemas kini. Sila cuba lagi.';
        }
    } elseif ($action === 'progress' || $action === 'complete') {
        if ($workNotes === '') {
            $error = 'Sila isi catatan tindakan sebelum menyimpan.';
        } else {
            $isComplete = $action === 'complete';
            $newStatus = $isComplete ? 'Selesai' : 'Dalam Tindakan';
            $sql = $isComplete
                ? "UPDATE tbllaporan SET tindakan_pembaikan = ?, status = ?,
                    tarikh_pemeriksaan = CASE WHEN tarikh_pemeriksaan IS NULL OR TRIM(tarikh_pemeriksaan) = '' THEN ? ELSE tarikh_pemeriksaan END,
                    tarikh_pembaikan = ?
                    WHERE id = ? AND (TRIM(pemeriksa) = ? OR TRIM(pemeriksa) = ?)"
                : "UPDATE tbllaporan SET tindakan_pembaikan = ?, status = ?,
                    tarikh_pemeriksaan = CASE WHEN tarikh_pemeriksaan IS NULL OR TRIM(tarikh_pemeriksaan) = '' THEN ? ELSE tarikh_pemeriksaan END
                    WHERE id = ? AND (TRIM(pemeriksa) = ? OR TRIM(pemeriksa) = ?)";
            $updateStatement = mysqli_prepare($conn, $sql);
            if ($updateStatement) {
                if ($isComplete) {
                    mysqli_stmt_bind_param($updateStatement, 'ssssiss', $workNotes, $newStatus, $now, $now, $taskId, $staffNo, $staffName);
                } else {
                    mysqli_stmt_bind_param($updateStatement, 'sssiss', $workNotes, $newStatus, $now, $taskId, $staffNo, $staffName);
                }
                if (mysqli_stmt_execute($updateStatement)) {
                    $_SESSION['staf_flash'] = $isComplete ? 'Tugasan ditandakan selesai.' : 'Kemajuan tugasan berjaya disimpan.';
                    mysqli_stmt_close($updateStatement);
                    header('Location: staf_tugas.php?id=' . $taskId);
                    exit();
                }
                $error = 'Tindakan tidak dapat disimpan. Sila cuba lagi.';
                mysqli_stmt_close($updateStatement);
            } else {
                $error = 'Tindakan tidak dapat disimpan. Sila cuba lagi.';
            }
        }
    } else {
        $error = 'Tindakan yang dipilih tidak sah.';
    }
}

function staffTaskStatusLabel(array $task): string
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
    <title>Butiran Tugasan - eHELPDESK PUO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f2f5f6; color: #1b2b34; font-family: Arial, sans-serif; }
        .topbar { background: #173f3a; color: #fff; }
        .detail-table th { width: 190px; color: #58666d; font-weight: 600; }
        .task-card { border: 0; border-radius: 8px; box-shadow: 0 3px 12px rgba(27, 43, 52, .06); }
        .action-buttons { display: flex; flex-wrap: wrap; gap: 10px; }
    </style>
</head>
<body>
    <header class="topbar py-3">
        <div class="container-fluid px-3 px-lg-4 d-flex justify-content-between align-items-center gap-3">
            <div><div class="small text-white-50">PORTAL STAF</div><h1 class="h5 mb-0 mt-1">Butiran tugasan</h1></div>
            <div class="d-flex align-items-center gap-3"><span class="d-none d-sm-inline"><?php echo htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8'); ?></span><a href="staf_logout.php" class="btn btn-outline-light btn-sm">Log Keluar</a></div>
        </div>
    </header>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="staf_dashboard.php" class="link-success text-decoration-none"><i class="fa-solid fa-arrow-left me-1"></i>Kembali ke tugasan</a>
            <?php if ($task): ?><span class="badge <?php echo staffTaskStatusLabel($task) === 'Selesai' ? 'text-bg-success' : 'text-bg-primary'; ?>"><?php echo htmlspecialchars(staffTaskStatusLabel($task), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
        </div>

        <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

        <?php if ($task): ?>
            <section class="card task-card mb-4">
                <div class="card-header bg-white py-3"><h2 class="h6 fw-bold mb-0">Maklumat aduan #<?php echo (int) $task['id']; ?></h2></div>
                <div class="table-responsive">
                    <table class="table table-bordered detail-table align-middle mb-0">
                        <tbody>
                            <tr><th>Tarikh laporan</th><td><?php echo htmlspecialchars($task['tarikh_laporan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td><th>Tarikh diterima</th><td><?php echo htmlspecialchars($task['tarikh_diterima'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Pelapor</th><td><?php echo htmlspecialchars($task['pelapor'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td><th>Telefon / Emel</th><td><?php echo htmlspecialchars(trim(($task['ext'] ?? '') . ' / ' . ($task['emel'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Lokasi</th><td><?php echo htmlspecialchars($task['lokasi'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td><th>Kategori</th><td><?php echo htmlspecialchars($task['jenis_kerosakan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                            <tr><th>Masalah</th><td colspan="3"><?php echo nl2br(htmlspecialchars($task['masalah'] ?? '-', ENT_QUOTES, 'UTF-8')); ?></td></tr>
                            <?php if (trim((string) ($task['catatan'] ?? '')) !== ''): ?><tr><th>Catatan pentadbir</th><td colspan="3"><?php echo nl2br(htmlspecialchars($task['catatan'], ENT_QUOTES, 'UTF-8')); ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if (($task['status'] ?? '') !== 'Selesai'): ?>
                <?php if (trim((string) ($task['tarikh_pemeriksaan'] ?? '')) === ''): ?>
                    <section class="card task-card mb-4"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h2 class="h6 fw-bold mb-1">Terima tugasan</h2><p class="text-secondary small mb-0">Pengesahan penerimaan akan merekodkan masa tugas dimulakan.</p></div><form method="POST"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_staf'], ENT_QUOTES, 'UTF-8'); ?>"><button class="btn btn-success" type="submit" name="action" value="receive"><i class="fa-solid fa-check me-2"></i>Terima tugasan</button></form></div></section>
                <?php endif; ?>

                <section class="card task-card">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold mb-3">Catatan kerja</h2>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_staf'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3"><label for="tindakan_pembaikan" class="form-label">Tindakan / hasil pemeriksaan</label><textarea id="tindakan_pembaikan" name="tindakan_pembaikan" class="form-control" rows="5" required><?php echo htmlspecialchars($task['tindakan_pembaikan'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                            <div class="action-buttons">
                                <button class="btn btn-outline-success" type="submit" name="action" value="progress"><i class="fa-solid fa-floppy-disk me-2"></i>Simpan kemajuan</button>
                                <button class="btn btn-success" type="submit" name="action" value="complete" onclick="return confirm('Tandakan tugasan ini sebagai selesai?');"><i class="fa-solid fa-circle-check me-2"></i>Tandakan selesai</button>
                            </div>
                        </form>
                    </div>
                </section>
            <?php else: ?>
                <section class="card task-card"><div class="card-body p-4"><h2 class="h6 fw-bold">Tindakan pembaikan</h2><p class="mb-2"><?php echo nl2br(htmlspecialchars($task['tindakan_pembaikan'] ?? '-', ENT_QUOTES, 'UTF-8')); ?></p><div class="small text-secondary">Selesai pada: <?php echo htmlspecialchars($task['tarikh_pembaikan'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div></div></section>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
