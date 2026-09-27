<?php
function requireRegisteredStaff(mysqli $conn): array
{
    if (($_SESSION['role'] ?? '') !== 'staff' || empty($_SESSION['staff_no_kp'])) {
        header('Location: staf_login.php');
        exit();
    }

    $staffNo = (string) $_SESSION['staff_no_kp'];
    $statement = mysqli_prepare($conn, "SELECT id, no_kp, nama, jawatan
        FROM tbladmin WHERE no_kp = ? AND COALESCE(jawatan, '') <> 'Pengguna' LIMIT 1");
    if ($statement) {
        mysqli_stmt_bind_param($statement, 's', $staffNo);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $staff = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($statement);
    } else {
        $staff = null;
    }

    if (!$staff) {
        unset($_SESSION['role'], $_SESSION['staff_user_id'], $_SESSION['staff_no_kp'], $_SESSION['staff_nama'], $_SESSION['staff_jawatan']);
        header('Location: staf_login.php');
        exit();
    }

    $_SESSION['staff_user_id'] = (int) $staff['id'];
    $_SESSION['staff_nama'] = (string) $staff['nama'];
    $_SESSION['staff_jawatan'] = (string) $staff['jawatan'];
    return $staff;
}
