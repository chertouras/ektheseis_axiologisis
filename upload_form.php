<?php

if (session_status() === PHP_SESSION_NONE) {
    include 'session.php';
}

include 'db_conn.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

$username = $_SESSION['username'] ?? null;
$userRole = $_SESSION['role'] ?? null;
$cn = $_SESSION['cn'] ?? null;

if (!$username) {
    die("No user logged in.");
}

$conn = $pdo;

$dieythinsi = null;
$allDieythinseis = [];
$isAdmin = ($userRole === 'admin');

if ($isAdmin) {
    // Διαχειριστής: Λήψη όλων των διευθύνσεων
    $query = "SELECT * FROM dieythinseis ORDER BY type, name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $allDieythinseis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Απλός χρήστης: Λήψη της ανατεθειμένης διεύθυνσης
    $query = "
        SELECT d.* FROM dieythinseis d
        JOIN users u ON u.dieythinsi_ekp_key = d.id
        WHERE u.username = :username";

    $stmt = $conn->prepare($query);
    $stmt->execute([':username' => $_SESSION['username']]);
    $dieythinsi = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Λήψη τρέχουσας σελίδας για επισήμανση στο μενού
$current_page = basename($_SERVER['PHP_SELF']);

// Ορισμός ελάχιστης ημερομηνίας στην τρέχουσα ημέρα
$min_date = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ανάρτηση Έκθεσης Αξιολόγησης </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* Βελτιωμένη ανταπόκριση για κινητά */
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --border-radius: 8px;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        body {
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-container {
            max-width: 100%;
            margin: 0 auto;
        }

        .card {
            border: none;
            box-shadow: var(--shadow);
            border-radius: var(--border-radius);
        }

        .form-control, .form-control-plaintext {
            border-radius: var(--border-radius);
            font-size: 16px; /* Αποτρέπει το ζουμ σε iOS */
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .btn {
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        /* Στυλ για το πεδίο αρχείου */
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: block;
            padding: 12px 16px;
            border: 2px dashed #ccc;
            border-radius: var(--border-radius);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .file-input-label:hover {
            border-color: var(--primary-color);
            background: #e7f3ff;
        }

        .file-input-label.has-file {
            border-color: var(--success-color);
            background: #d4edda;
            color: var(--success-color);
        }

        /* Κατάσταση φόρτωσης */
        .upload-spinner {
            background: rgba(255, 255, 255, 0.95);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner-content {
            text-align: center;
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        /* Γραμμή προόδου */
        .progress-wrapper {
            display: none;
            margin-top: 1rem;
        }

        /* Στυλ σφαλμάτων */
        .is-invalid {
            border-color: var(--danger-color);
        }

        .invalid-feedback {
            display: block;
        }

        /* Ανταπόκριση για κινητά */
        @media (max-width: 768px) {
            .container {
                padding-left: 10px !important;
                padding-right: 10px !important;
                margin-top: 20px !important;
            }

            .card-body {
                padding: 20px 15px !important;
            }

            .card-title {
                font-size: 1.25rem !important;
                margin-bottom: 20px !important;
                line-height: 1.3;
            }

            .form-control, .form-control-plaintext {
                padding: 12px !important;
            }

            .btn {
                padding: 12px 20px !important;
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .container {
                padding-left: 5px !important;
                padding-right: 5px !important;
            }

            .card-body {
                padding: 15px 10px !important;
            }

            .card-title {
                font-size: 1.1rem !important;
                text-align: center;
            }
        }

        /* Βελτιωμένο στυλ επικύρωσης */
        .form-group {
            position: relative;
        }

        .validation-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
        }

        /* Απόκρυψη μηνυμάτων επικύρωσης αρχικά */
        .invalid-feedback {
            display: none !important;
        }

        /* Εμφάνιση μηνυμάτων επικύρωσης μόνο όταν το πεδίο είναι άκυρο */
        .is-invalid + .invalid-feedback,
        .form-control.is-invalid ~ .invalid-feedback,
        .file-input-wrapper .is-invalid ~ .invalid-feedback {
            display: block !important;
        }

        /* Βελτιώσεις προσβασιμότητας */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'menu.php'; ?>

    <div class="upload-spinner" id="upload-spinner">
        <div class="spinner-content">
            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="sr-only">Φόρτωση...</span>
            </div>
            <h4>Ανάρτηση σε εξέλιξη</h4>
            <p class="mb-0">Παρακαλώ περιμένετε...</p>
            <div class="progress-wrapper">
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated"
                         role="progressbar" style="width: 0%" id="upload-progress"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title mb-4 text-center text-md-start">
                            <i class="fas fa-bullhorn text-primary me-2"></i>
                            Ανάρτηση Αξιολογικής Έκθεσης
                        </h3>

                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <i class="fas fa-info-circle me-2"></i>
                                <?= htmlspecialchars($_SESSION['message']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['message']); ?>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($_SESSION['error']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>

                        <form action="upload.php" method="POST" enctype="multipart/form-data" id="upload-form" novalidate>

                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                            <div class="mb-3">
                                <label for="administration" class="form-label">
                                    <i class="fas fa-building me-1"></i>
                                    Διεύθυνση Εκπαίδευσης
                                    <?php if ($isAdmin): ?>
                                        <span class="admin-badge">
                                            <i class="fas fa-crown me-1"></i>Admin
                                        </span>
                                    <?php endif; ?>
                                </label>

                                <?php if ($isAdmin): ?>
                                    <select class="form-select" name="dieythinsi_ekp_key" id="dieythinsi_ekp_key" required>
                                        <option value="">Επιλέξτε Διεύθυνση Εκπαίδευσης...</option>
                                        <?php foreach ($allDieythinseis as $dept): ?>
                                            <option value="<?= htmlspecialchars($dept['id']) ?>">
                                                <?= htmlspecialchars($dept['type']) . ' ' . htmlspecialchars($dept['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Ως διαχειριστής, μπορείτε να επιλέξετε οποιαδήποτε Διεύθυνση Εκπαίδευσης
                                    </div>
                                    <div class="invalid-feedback">
                                        Παρακαλώ επιλέξτε μια Διεύθυνση Εκπαίδευσης
                                    </div>
                                <?php elseif ($dieythinsi && $dieythinsi['id']): ?>
                                    <input type="hidden" name="dieythinsi_ekp_key" value="<?= htmlspecialchars($dieythinsi['id']) ?>">
                                    <div class="form-control-plaintext bg-light p-3 rounded border">
                                        ...