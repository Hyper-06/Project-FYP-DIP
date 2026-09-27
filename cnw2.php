<?php
include 'db.php';
$msg = "";
$specificLocation = trim($_GET['lokasi'] ?? 'CNW2');
$specificLocation = $specificLocation !== '' ? $specificLocation : 'CNW2';
$categoryOptions = [];
$categoryResult = mysqli_query($conn, "SELECT id, jenis FROM tblkat_laporan ORDER BY id ASC");
if ($categoryResult) {
    while ($categoryRow = mysqli_fetch_assoc($categoryResult)) {
        $categoryOptions[] = $categoryRow;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $department = mysqli_real_escape_string($conn, $specificLocation);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $selectedCategory = trim($_POST['category_id'] ?? '');
    $categoryId = 0;
    $category = '';
    foreach ($categoryOptions as $categoryOption) {
        if ($categoryOption['jenis'] === $selectedCategory) {
            $categoryId = (int) $categoryOption['id'];
            $category = mysqli_real_escape_string($conn, $categoryOption['jenis']);
            break;
        }
    }
    $issue = mysqli_real_escape_string($conn, $_POST['issue']);

    $idResult = mysqli_query($conn, "SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM tbllaporan");
    $nextId = (int) mysqli_fetch_assoc($idResult)['next_id'];
    $pelapor = $name;
    $noSiriPendaftaran = '';
    $noSiriAlat = '';
    $jabatan = 0;
    $ext = trim($_POST['phone'] ?? '');
    $tarikhLaporan = date('Y-m-d H:i:s');
    $tarikhDiterima = date('Y-m-d H:i:s');
    $status = 'Baru';

    $stmt = mysqli_prepare($conn, "INSERT INTO tbllaporan (status, id, no_siri_pendaftaran, no_siri_alat, lokasi, jabatan, kat_laporan, pelapor, ext, emel, tarikh_laporan, kategori_kerosakan, jenis_kerosakan, masalah, tarikh_diterima) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sisssiissssssss', $status, $nextId, $noSiriPendaftaran, $noSiriAlat, $department, $jabatan, $categoryId, $pelapor, $ext, $email, $tarikhLaporan, $category, $category, $issue, $tarikhDiterima);

    if ($category !== '' && mysqli_stmt_execute($stmt)) {
        $msg = "Your issue has been submitted successfully!";
    } else {
        $msg = "Maaf, aduan anda tidak dapat dihantar buat masa ini. Sila cuba semula.";
    }
    mysqli_stmt_close($stmt);
}

// Use the LAN address so QR codes work from other devices on the network.
$qrCodeUrl = "http://10.249.122.104" . $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUO eHelpdesk - <?php echo $specificLocation; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { background-color: #f4f6f9; }
        .form-container { max-width: 650px; margin: auto; }
        .card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); }
        .header-card { 
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); 
            color: white; 
            border-radius: 20px; 
        }
        .logo-box { 
            background: rgba(255, 255, 255, 0.98); 
            border-radius: 14px; 
            padding: 15px 20px; 
            display: inline-block; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); 
        }
        .form-control, .form-select { border-radius: 10px; padding: 12px 15px; background-color: #fcfcfc; border: 1px solid #e5e7eb; }
        .form-control:focus, .form-select:focus { box-shadow: none; border-color: #2563eb; background-color: #fff; }
        .btn-custom { background-color: #2563eb; border: none; border-radius: 10px; padding: 12px; font-weight: 600; }
        .btn-custom:hover { background-color: #1d4ed8; }
        .qr-card { border-radius: 14px; }
        .qr-card h5 { font-size: 0.9rem; margin-bottom: 5px; }
        .qr-card p { font-size: 0.65rem; margin-bottom: 8px; }
        .qr-card #qrcode img { width: 140px; height: 140px; }
        .admin-link { color: #6c757d; font-size: 0.7rem; }
    </style>
</head>
<body>
    <div class="container py-4 py-md-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-9 form-container">
                
                <div class="card header-card text-center mb-4 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <div class="logo-box mb-4 d-flex align-items-center justify-content-center gap-3">
                            <img src="ministry_logo.png" alt="Ministry Logo" height="45" class="img-fluid" onerror="this.style.display='none'">
                            <img src="puo_logo.png" alt="PUO Logo" height="45" class="img-fluid">
                        </div>
                        <h2 class="fw-bold text-white mb-2" style="font-size: 1.8rem; letter-spacing: -0.5px;">
                            eHELPDESK - <?php echo $specificLocation; ?>
                        </h2>
                        <p class="text-white-50 small mb-0" style="font-size: 0.95rem;">
                            Lokasi telah ditetapkan secara automatik melalui kod QR.
                        </p>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body p-4 p-md-5">
                        <h4 class="fw-bold text-dark mb-4">Borang Aduan Kerosakan</h4>
                        <?php if($msg): ?>
                            <div class="alert alert-success"><?php echo $msg; ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-secondary" style="font-size: 0.75rem;">FULL NAME / NAMA PENUH</label>
                                <input type="text" name="name" class="form-control" placeholder="Masukkan nama anda" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-secondary" style="font-size: 0.75rem;">LOKASI / TEMPAT (JABATAN / UNIT)</label>
                                <input type="text" name="department" class="form-control bg-light fw-semibold text-primary" value="<?php echo $specificLocation; ?>" readonly required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-secondary" style="font-size: 0.75rem;">EMAIL / STUDENT ID</label>
                                <input type="text" name="email" class="form-control" placeholder="01DIT24F...@puo.edu.my" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-secondary" style="font-size: 0.75rem;">NO. TEL</label>
                                <input type="text" name="phone" class="form-control" placeholder="Contoh: 0175701933" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-secondary" style="font-size: 0.75rem;">CATEGORY / KATEGORI</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="" disabled selected>Pilih kategori aduan</option>
                                    <?php foreach ($categoryOptions as $categoryOption): ?>
                                        <option value="<?php echo htmlspecialchars($categoryOption['jenis']); ?>"><?php echo htmlspecialchars($categoryOption['jenis']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary" style="font-size: 0.75rem;">ISSUE DESCRIPTION / BUTIRAN ADUAN</label>
                                <textarea name="issue" class="form-control" rows="4" placeholder="Nyatakan masalah anda..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-custom text-white w-100">Submit Issue</button>
                        </form>
                    </div>
                </div>

                <div class="card qr-card text-center mb-2">
                    <div class="card-body p-4">
                        <h5 class="fw-bold">Scan to Open on Mobile</h5>
                        <p class="text-secondary">Scan this QR code using your phone camera to access the form instantly.</p>
                        <div id="qrcode" class="d-flex justify-content-center overflow-hidden my-2"></div>
                    </div>
                </div>
                <div class="text-center mb-3">
                    <a href="login.php" class="admin-link text-decoration-none">&rarr; Staff / Admin Login</a>
                </div>
            </div>
        </div>
    </div>
    <script>
        const profileStorageKey = 'ehelpdesk_user_profile';
        const complaintForm = document.querySelector('form');

        try {
            const savedProfile = JSON.parse(localStorage.getItem(profileStorageKey) || 'null');
            if (savedProfile) {
                document.querySelector('[name="name"]').value = savedProfile.name || '';
                document.querySelector('[name="email"]').value = savedProfile.email || '';
                document.querySelector('[name="phone"]').value = savedProfile.phone || '';
            }

            complaintForm.addEventListener('submit', function () {
                localStorage.setItem(profileStorageKey, JSON.stringify({
                    name: document.querySelector('[name="name"]').value,
                    email: document.querySelector('[name="email"]').value,
                    phone: document.querySelector('[name="phone"]').value
                }));
            });
        } catch (error) {
            // Continue normally if browser storage is unavailable.
        }

        new QRCode(document.getElementById("qrcode"), {
            text: "<?php echo $qrCodeUrl; ?>",
            width: 140,
            height: 140
        });
    </script>
</body>
</html>
