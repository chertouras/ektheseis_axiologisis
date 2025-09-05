<?php
// session.php - Ρυθμίσεις και έλεγχοι ασφαλείας συνεδρίας

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Ασφαλής ρύθμιση συνεδρίας
ini_set('session.cookie_lifetime', '0');
ini_set('session.use_only_cookies', '1');
ini_set('session.hash_function', 'sha256');

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
    'gc_maxlifetime' => 3600, // 1 ώρα
    'sid_length' => 48,
    'sid_bits_per_character' => 6
]);

// Περιοδική αναγέννηση session ID
if (!isset($_SESSION['last_regeneration']) ||
    time() - $_SESSION['last_regeneration'] > 3600) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Έλεγχος λήξης αδράνειας
if (isset($_SESSION['last_activity']) &&
    time() - $_SESSION['last_activity'] > 1800) { // 30 λεπτά
    session_unset();
    session_destroy();
    header("Location: phpcas.php");
    exit;
}
$_SESSION['last_activity'] = time();

// Έλεγχος ύπαρξης χρήστη και ρόλου
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: phpcas.php");
    exit;
}

// Έλεγχος δακτυλικού αποτυπώματος περιηγητή
$current_fingerprint = hash('sha256', $_SERVER['HTTP_USER_AGENT']);
if (!isset($_SESSION['fingerprint']) || $_SESSION['fingerprint'] !== $current_fingerprint) {
    session_unset();
    session_destroy();
    header("Location: phpcas.php");
    exit;
}

include 'db_conn.php';

// Επαλήθευση δεδομένων συνεδρίας με τη βάση δεδομένων
if (isset($_SESSION['username']) && isset($_SESSION['role'])) {
    $stmt = $pdo->prepare("SELECT role, status FROM users WHERE username = ? AND status = 'approved'");
    $stmt->execute([$_SESSION['username']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_data || $user_data['role'] !== $_SESSION['role']) {
        session_unset();
        session_destroy();
        header("Location: phpcas.php");
        exit;
    }
}