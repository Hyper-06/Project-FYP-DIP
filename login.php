<?php
session_start();
require_once 'db.php'; // Adjust to your database connection file

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Sila isi ID pengguna dan kata laluan.";
    } elseif (strlen($username) > 12) {
        $error = "ID pengguna maksimum 12 aksara.";
    } elseif (strlen($password) < 5 || strlen($password) > 20) {
        $error = "Kata laluan mesti antara 5 hingga 20 aksara.";
    } else {
        $stmt = $conn->prepare("SELECT id, no_kp, nama, katalaluan FROM tbladmin WHERE no_kp = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Support both existing plain-text records and password hashes.
            $passwordIsValid = password_verify($password, $row['katalaluan']) || hash_equals((string) $row['katalaluan'], $password);
            if ($passwordIsValid) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['no_kp'];
                $_SESSION['nama'] = $row['nama'];
                $_SESSION['role'] = 'admin';

                header("Location: admin_dashboard.php");
                exit();
            } else {
                $error = "Invalid username or password.";
            }
        } else {
            $idResult = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM tbladmin");
            $nextId = (int) $idResult->fetch_assoc()['next_id'];
            $nama = $username;
            $jawatan = 'Pengguna';
            $idJabatan = 0;
            $lastLogin = '';
            $level = '1';
            $akses = 0;
            $unit = '';
            $ext = 0;
            $status = 'aktif';
            $insertStmt = $conn->prepare("INSERT INTO tbladmin (id, no_kp, nama, jawatan, katalaluan, id_jabatan, last_login, level, akses, unit, ext, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("issssissisis", $nextId, $username, $nama, $jawatan, $password, $idJabatan, $lastLogin, $level, $akses, $unit, $ext, $status);

            if ($insertStmt->execute()) {
                $_SESSION['user_id'] = $nextId;
                $_SESSION['username'] = $username;
                $_SESSION['nama'] = $nama;
                $_SESSION['role'] = 'admin';
                header("Location: admin_dashboard.php");
                exit();
            }

            $error = "Akaun baharu gagal dicipta. Sila cuba lagi.";
            $insertStmt->close();
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk - eHELPDESK PUO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --blue: #2448d8;
            --blue-dark: #263291;
            --ink: #192d43;
            --muted: #79818c;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 32px;
            display: grid;
            place-items: center;
            background: #f1f4f9;
            color: var(--ink);
            font-family: "Nunito Sans", sans-serif;
        }

        .login-shell {
            width: min(100%, 850px);
            min-height: 462px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 18px 32px rgba(25, 45, 67, 0.16);
        }

        .welcome-panel {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 44px 44px 42px;
            color: #fff;
            background: linear-gradient(145deg, #2936ad 0%, #205be9 100%);
        }

        .logo-frame {
            width: 100%;
            min-height: 84px;
            display: grid;
            place-items: center;
            padding: 12px 22px;
            border: 1px solid rgba(255, 255, 255, .22);
            border-radius: 15px;
            background: rgba(255, 255, 255, .08);
        }

        .logo-frame img {
            max-width: 190px;
            max-height: 62px;
            object-fit: contain;
            background: #fff;
        }

        .welcome-copy { max-width: 320px; }
        .welcome-copy h1 {
            margin: 0 0 24px;
            font-size: clamp(2rem, 4vw, 2.65rem);
            line-height: 1.06;
            font-weight: 800;
        }

        .welcome-copy p {
            margin: 0;
            color: rgba(255, 255, 255, .84);
            font-size: .88rem;
            line-height: 1.65;
        }

        .login-panel {
            display: flex;
            align-items: center;
            padding: 44px;
        }

        .login-content { width: 100%; max-width: 328px; margin: auto; }
        .login-content h2 { margin: 0 0 2px; font-size: 1.55rem; font-weight: 800; }
        .login-subtitle { margin: 0 0 30px; color: var(--muted); font-size: .84rem; }
        .field { margin-bottom: 18px; }
        .field label {
            display: block;
            margin-bottom: 7px;
            color: #5d6874;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .input-wrap { position: relative; }
        .input-wrap i {
            position: absolute;
            top: 50%;
            left: 13px;
            transform: translateY(-50%);
            color: #9ba5b1;
        }

        .input-wrap input {
            width: 100%;
            height: 43px;
            padding: 0 14px 0 38px;
            border: 1px solid #e3e7ec;
            border-radius: 11px;
            outline: 0;
            background: #f8fafc;
            color: var(--ink);
            font: inherit;
            font-size: .84rem;
        }

        .input-wrap input:focus {
            border-color: #5080f0;
            box-shadow: 0 0 0 3px rgba(80, 128, 240, .12);
        }

        .notice, .error {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            margin: 3px 0 20px;
            padding: 11px 12px;
            border-radius: 8px;
            font-size: .73rem;
            line-height: 1.45;
        }

        .notice { color: #9a5a00; background: #fff9df; border: 1px solid #ffedaa; }
        .error { color: #a52929; background: #fff0f0; border: 1px solid #ffcaca; }
        .notice i, .error i { flex: 0 0 auto; }

        .actions { display: grid; grid-template-columns: 1.55fr .8fr; gap: 12px; }
        .actions button {
            height: 42px;
            border: 0;
            border-radius: 10px;
            font: inherit;
            font-size: .84rem;
            font-weight: 700;
            cursor: pointer;
        }

        .submit-button { color: #fff; background: #1765ed; box-shadow: 0 6px 12px rgba(23, 101, 237, .2); }
        .submit-button:hover { background: #0d55d0; }
        .cancel-button { color: #717b86; background: #f1f3f5; }

        .public-links {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-top: 22px;
            color: #7b8490;
            font-size: .72rem;
        }

        .public-links a { color: #1765ed; text-decoration: none; font-weight: 700; }
        .public-links a:hover { text-decoration: underline; }

        @media (max-width: 680px) {
            body { padding: 18px; }
            .login-shell { grid-template-columns: 1fr; }
            .welcome-panel { min-height: 270px; padding: 28px; }
            .welcome-copy h1 { margin-bottom: 12px; font-size: 2rem; }
            .welcome-copy p { font-size: .8rem; }
            .login-panel { padding: 32px 28px; }
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <section class="welcome-panel">
            <div class="logo-frame">
                <img src="puo_logo.png" alt="Logo Politeknik Ungku Omar">
            </div>
            <div class="welcome-copy">
                <h1>Sistem<br>eHELPDESK PUO</h1>
                <p>Selamat datang ke Sistem eHelpdesk Politeknik Ungku Omar. Sila log masuk menggunakan akaun eHadir anda untuk mengakses sistem.</p>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-content">
                <h2>Log Masuk</h2>
                <p class="login-subtitle">Sila masukkan ID dan kata laluan anda</p>

                <?php if (!empty($error)): ?>
                    <div class="error"><i class="bi bi-exclamation-circle-fill"></i><span><?php echo htmlspecialchars($error); ?></span></div>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="field">
                        <label for="username">ID PENGGUNA</label>
                        <div class="input-wrap">
                            <i class="bi bi-person-fill"></i>
                            <input id="username" type="text" name="username" maxlength="12" placeholder="Contoh: 10729" autocomplete="username" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="password">KATA LALUAN</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock-fill"></i>
                            <input id="password" type="password" name="password" minlength="5" maxlength="20" placeholder="Minimum 5 aksara" autocomplete="current-password" required>
                        </div>
                    </div>

                    <div class="notice"><i class="bi bi-info-circle-fill"></i><span>ID baharu akan didaftarkan secara automatik dan terus mendapat akses admin.</span></div>

                    <div class="actions">
                        <button class="submit-button" type="submit"><i class="bi bi-box-arrow-in-right"></i> Log Masuk</button>
                        <button class="cancel-button" type="reset">Batal</button>
                    </div>
                </form>

                <div class="public-links">
                    <span>Borang aduan:</span>
                    <a href="index.php">Umum</a>
                    <a href="lab1.php">Lab</a>
                    <a href="library.php">Perpustakaan</a>
                    <a href="tandas.php">Tandas</a>
                </div>

            </div>
        </section>
    </main>
</body>
</html>