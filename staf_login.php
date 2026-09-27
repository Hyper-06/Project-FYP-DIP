<?php
session_start();
require_once 'db.php';

if (($_SESSION['role'] ?? '') === 'staff' && !empty($_SESSION['staff_no_kp'])) {
    $existingStaffNo = (string) $_SESSION['staff_no_kp'];
    $existingStatement = mysqli_prepare($conn, "SELECT id, no_kp, nama, jawatan
        FROM tbladmin WHERE no_kp = ? AND COALESCE(jawatan, '') <> 'Pengguna' LIMIT 1");
    if ($existingStatement) {
        mysqli_stmt_bind_param($existingStatement, 's', $existingStaffNo);
        mysqli_stmt_execute($existingStatement);
        $existingResult = mysqli_stmt_get_result($existingStatement);
        $existingStaff = $existingResult ? mysqli_fetch_assoc($existingResult) : null;
        mysqli_stmt_close($existingStatement);
    } else {
        $existingStaff = null;
    }

    if ($existingStaff) {
        $_SESSION['staff_user_id'] = (int) $existingStaff['id'];
        $_SESSION['staff_nama'] = (string) $existingStaff['nama'];
        $_SESSION['staff_jawatan'] = (string) $existingStaff['jawatan'];
        header('Location: staf_dashboard.php');
        exit();
    }

    unset($_SESSION['role'], $_SESSION['staff_user_id'], $_SESSION['staff_no_kp'], $_SESSION['staff_nama'], $_SESSION['staff_jawatan']);
}

if (empty($_SESSION['csrf_staf_login'])) {
    $_SESSION['csrf_staf_login'] = bin2hex(random_bytes(32));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $staffIdentifier = trim($_POST['staff_no_kp'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals($_SESSION['csrf_staf_login'], $token)) {
        $error = 'Sesi borang tamat. Sila cuba sekali lagi.';
    } elseif ($staffIdentifier === '' || $password === '') {
        $error = 'Sila isi nombor ID staf dan kata laluan.';
    } else {
        $statement = mysqli_prepare($conn, "SELECT id, no_kp, nama, jawatan, katalaluan
            FROM tbladmin
            WHERE (no_kp = ? OR CAST(id AS CHAR) = ?)
              AND COALESCE(jawatan, '') <> 'Pengguna'
            LIMIT 1");
        if ($statement) {
            mysqli_stmt_bind_param($statement, 'ss', $staffIdentifier, $staffIdentifier);
            mysqli_stmt_execute($statement);
            $result = mysqli_stmt_get_result($statement);
            $account = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($statement);

            $validPassword = $account && (
                password_verify($password, (string) $account['katalaluan']) ||
                hash_equals((string) $account['katalaluan'], $password)
            );

            if ($validPassword) {
                session_regenerate_id(true);
                $_SESSION = [];
                $_SESSION['role'] = 'staff';
                $_SESSION['staff_user_id'] = (int) $account['id'];
                $_SESSION['staff_no_kp'] = (string) $account['no_kp'];
                $_SESSION['staff_nama'] = (string) $account['nama'];
                $_SESSION['staff_jawatan'] = (string) $account['jawatan'];
                header('Location: staf_dashboard.php');
                exit();
            }

            $error = 'ID staf atau kata laluan tidak sah.';
        } else {
            $error = 'Log masuk tidak dapat diproses buat masa ini.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Masuk Staf - eHELPDESK PUO</title>
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

        .login-panel { display: flex; align-items: center; padding: 44px; }
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

        .error {
            display: flex;
            gap: 8px;
            align-items: flex-start;
            margin: 3px 0 20px;
            padding: 11px 12px;
            border: 1px solid #ffcaca;
            border-radius: 8px;
            background: #fff0f0;
            color: #a52929;
            font-size: .73rem;
            line-height: 1.45;
        }

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
                <p>Portal staf untuk menerima tugasan, merekod kemajuan dan mengemas kini status aduan.</p>
            </div>
        </section>

        <section class="login-panel">
            <div class="login-content">
                <h2>Log Masuk Staf</h2>
                <p class="login-subtitle">Gunakan akaun eHadir anda untuk melihat tugasan staf.</p>

                <?php if ($error !== ''): ?>
                    <div class="error" role="alert"><i class="bi bi-exclamation-circle-fill"></i><span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <?php endif; ?>

                <form action="staf_login.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_staf_login'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="field">
                        <label for="staff_no_kp">ID STAF ATAU NO. KP</label>
                        <div class="input-wrap">
                            <i class="bi bi-person-fill"></i>
                            <input id="staff_no_kp" type="text" name="staff_no_kp" maxlength="12" placeholder="Masukkan id atau no. KP staf" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    <div class="field">
                        <label for="password">KATA LALUAN</label>
                        <div class="input-wrap">
                            <i class="bi bi-lock-fill"></i>
                            <input id="password" type="password" name="password" maxlength="20" placeholder="Masukkan kata laluan" autocomplete="current-password" required>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="submit-button" type="submit"><i class="bi bi-box-arrow-in-right"></i> Log Masuk</button>
                        <button class="cancel-button" type="reset">Batal</button>
                    </div>
                </form>

                <div class="public-links">
                    <a href="login.php"><i class="bi bi-arrow-left me-1"></i>Kembali ke log masuk utama</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
