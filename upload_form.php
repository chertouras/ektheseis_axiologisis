<?php
// upload_form.php - Φόρμα ανάρτησης αξιολογικής έκθεσης

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
    $query = "SELECT * FROM dieythinseis ORDER BY type, name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $allDieythinseis = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $query = "
        SELECT d.* FROM dieythinseis d
        JOIN users u ON u.dieythinsi_ekp_key = d.id
        WHERE u.username = :username";

    $stmt = $conn->prepare($query);
    $stmt->execute([':username' => $_SESSION['username']]);
    $dieythinsi = $stmt->fetch(PDO::FETCH_ASSOC);
}

$current_page = basename($_SERVER['PHP_SELF']);
$min_date = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ανάρτηση Έκθεσης Αξιολόγησης</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
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
            font-size: 16px;
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

        .progress-wrapper {
            display: none;
            margin-top: 1rem;
        }

        .is-invalid {
            border-color: var(--danger-color);
        }

        .invalid-feedback {
            display: block;
        }

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

        .invalid-feedback {
            display: none !important;
        }

        .is-invalid + .invalid-feedback,
        .form-control.is-invalid ~ .invalid-feedback,
        .file-input-wrapper .is-invalid ~ .invalid-feedback {
            display: block !important;
        }

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
                                        <i class="fas fa-check-circle text-success me-2"></i>
                                        <?= htmlspecialchars($dieythinsi['type']) . ' ' . htmlspecialchars($dieythinsi['name']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Δεν σας έχει ανατεθεί κάποια Διεύθυνση Εκπαίδευσης. Παρακαλώ επικοινωνήστε με τον διαχειριστή.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    <i class="fas fa-user me-1"></i>
                                    Ανάρτηση από:
                                </label>
                                <div class="form-control-plaintext bg-light p-3 rounded border">
                                    <i class="fas fa-user-circle text-primary me-2"></i>
                                    <?= htmlspecialchars($_SESSION['cn']) ?>
                                </div>
                                <input type="hidden" name="upload_from" value="<?= htmlspecialchars($_SESSION['username']) ?>">
                            </div>


                            <div class="mb-3 form-group">
                                <label for="ekpaideytikos" class="form-label">
                                    <i class="fas fa-user-tie me-1"></i>
                                    Ονοματεπώνυμο Εκπαιδευτικού <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       name="ekpaideytikos"
                                       id="ekpaideytikos"
                                       required
                                       placeholder="Π.χ. Νικόλαος Ιωάννου"
                                       aria-describedby="ekpaideytikos-help">
                                <div id="ekpaideytikos-help" class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Εισάγετε το πλήρες ονοματεπώνυμο του εκπαιδευτικού
                                </div>
                                <div class="invalid-feedback">
                                    Παρακαλώ εισάγετε το ονοματεπώνυμο του εκπαιδευτικού
                                </div>
                            </div>

                            <div class="mb-3 form-group">
                                <label for="sxoleio" class="form-label">
                                    <i class="fas fa-school me-1"></i>
                                    Σχολική Μονάδα <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       name="sxoleio"
                                       id="sxoleio"
                                       required
                                       placeholder="Π.χ. Γυμνάσιο Ιερισσού"
                                       aria-describedby="sxoleio-help">
                                <div id="sxoleio-help" class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Εισάγετε την σχολική μονάδα του εκπαιδευτικού
                                </div>
                                <div class="invalid-feedback">
                                    Παρακαλώ εισάγετε την σχολική μονάδα του εκπαιδευτικού
                                </div>
                            </div>

                            <div class="mb-3 form-group">
                                <label for="eidikotita" class="form-label">
                                    <i class="fas fa-chalkboard-teacher me-1"></i>
                                    Ειδικότητα <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       name="eidikotita"
                                       id="eidikotita"
                                       required
                                       placeholder="Π.χ. ΠΕ86 κλπ."
                                       aria-describedby="eidikotita-help">
                                <div id="eidikotita-help" class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Εισάγετε την ειδικότητα του εκπαιδευτικού
                                </div>
                                <div class="invalid-feedback">
                                    Παρακαλώ εισάγετε την ειδικότητα του εκπαιδευτικού
                                </div>
                            </div>


                            <div class="mb-3 form-group">
    <label class="form-label">
        <i class="fas fa-list-alt me-1"></i>
        Επιλέξτε Πεδίο Αξιολόγησης <span class="text-danger">*</span>
    </label>
    <div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="pedio_ax" id="pedio_a1" value="A1" required>
            <label class="form-check-label" for="pedio_a">A1</label>
        </div>
		<div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="pedio_ax" id="pedio_a2" value="A2" required>
            <label class="form-check-label" for="pedio_a">A2</label>
        </div>

        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="pedio_ax" id="pedio_b" value="B">
            <label class="form-check-label" for="pedio_b">B</label>
        </div>
    </div>
    <div class="invalid-feedback">
        Παρακαλώ επιλέξτε μια τιμή (A1 ή Α2 ή B)
    </div>
</div>

                            <div class="mb-3 form-group">
                                <label for="uploaded_file" class="form-label">
                                    <i class="fas fa-file-pdf me-1"></i>
                                    Αρχείο Έκθεσης <span class="text-danger">*</span>
                                </label>
                                <div class="file-input-wrapper">
                                    <input class="form-control"
                                           type="file"
                                           name="uploaded_file"
                                           id="uploaded_file"
                                           accept="application/pdf,.pdf"
                                           required
                                           aria-describedby="file-help">
                                    <label for="uploaded_file" class="file-input-label" id="file-label">
                                        <i class="fas fa-cloud-upload-alt me-2"></i>
                                        Κάντε κλικ για επιλογή αρχείου PDF
                                    </label>
                                </div>
                                <div id="file-help" class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Μόνο αρχεία PDF επιτρέπονται (μέγιστο μέγεθος: 10MB)
                                </div>
                                <div class="invalid-feedback">
                                    Παρακαλώ επιλέξτε ένα έγκυρο αρχείο PDF
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="is_final_checkbox">
                                <label class="form-check-label" for="is_final_checkbox">
                                    <i class="fas fa-lock me-1"></i>
                                    Οριστικοποίηση ανάρτησης (Δεν επιτρέπονται αλλαγές)
                                </label>
                            </div>

                            <input type="hidden" name="is_final" id="is_final_hidden" value="0">

                            <div class="d-grid">
                                <button type="submit"
                                        class="btn btn-primary btn-lg"
                                        id="submit-btn"
                                         <?php if (!$isAdmin && !($dieythinsi && $dieythinsi['id'])) echo 'disabled'; ?>>
                                    <i class="fas fa-upload me-2"></i>
                                    Ανάρτηση Έκθεσης Αξιολόγησης
                                </button>
                            </div>

                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Τα πεδία με <span class="text-danger">*</span> είναι υποχρεωτικά
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('upload-form');
            const spinner = document.getElementById('upload-spinner');
            const submitBtn = document.getElementById('submit-btn');
            const fileInput = document.getElementById('uploaded_file');
            const fileLabel = document.getElementById('file-label');
            const progressBar = document.getElementById('upload-progress');
            const progressWrapper = document.querySelector('.progress-wrapper');
            let formSubmitted = false;

            const inputs = form.querySelectorAll('input[required], select[required]');
            const radioButtons = form.querySelectorAll('input[name="pedio_ax"]');

            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.type !== 'application/pdf') {
                        showValidationError(fileInput, 'Μόνο αρχεία PDF επιτρέπονται');
                        fileInput.value = '';
                        return;
                    }

                    if (file.size > 10 * 1024 * 1024) {
                        showValidationError(fileInput, 'Το αρχείο είναι πολύ μεγάλο (μέγιστο 10MB)');
                        fileInput.value = '';
                        return;
                    }

                    fileLabel.innerHTML = `<i class="fas fa-file-pdf me-2 text-success"></i>${file.name}`;
                    fileLabel.classList.add('has-file');
                    clearValidationError(fileInput);
                } else {
                    fileLabel.innerHTML = '<i class="fas fa-cloud-upload-alt me-2"></i>Κάντε κλικ για επιλογή αρχείου PDF';
                    fileLabel.classList.remove('has-file');
                }
            });

            function resetFileLabel() {
                fileLabel.innerHTML = '<i class="fas fa-cloud-upload-alt me-2"></i>Κάντε κλικ για επιλογή αρχείου PDF';
                fileLabel.classList.remove('has-file');
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                formSubmitted = true;

                if (validateForm()) {
                    submitForm();
                }
            });

            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (formSubmitted) {
                        validateField(this);
                    }
                });

                radioButtons.forEach(radio => {
                    radio.addEventListener('change', function() {
                        if (formSubmitted) {
                            validateField(this);
                        }
                    });
                });

                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateField(this);
                    }
                });
            });

            function validateForm() {
                let isValid = true;

                inputs.forEach(input => {
                    if (!validateField(input)) {
                        isValid = false;
                    }
                });

                const radioGroup = document.querySelectorAll('input[name="pedio_ax"]');
                if (radioGroup.length > 0) {
                    if (!validateField(radioGroup[0])) {
                        isValid = false;
                    }
                }

                return isValid;
            }

            function validateField(field) {
                const value = field.value.trim();
                let isValid = true;
                let message = '';

                if (field.hasAttribute('required') && !value && field.type !== 'radio' && field.type !== 'file') {
                    isValid = false;
                    message = 'Αυτό το πεδίο είναι υποχρεωτικό';
                }

                if (field.name === 'pedio_ax') {
                    const radioGroup = document.querySelectorAll('input[name="pedio_ax"]');
                    const isAnySelected = Array.from(radioGroup).some(radio => radio.checked);

                    if (!isAnySelected) {
                        isValid = false;
                        message = 'Παρακαλώ επιλέξτε μια τιμή (A1, A2 ή B)';

                        const radioContainer = field.closest('.form-group');
                        if (radioContainer) {
                            let feedback = radioContainer.querySelector('.invalid-feedback');
                            if (!feedback) {
                                feedback = document.createElement('div');
                                feedback.className = 'invalid-feedback';
                                radioContainer.appendChild(feedback);
                            }
                            feedback.textContent = message;
                            feedback.style.setProperty('display', 'block', 'important');
                            feedback.style.setProperty('color', '#dc3545', 'important');
                            feedback.style.setProperty('font-size', '0.875em', 'important');
                            feedback.style.setProperty('margin-top', '0.25rem', 'important');
                        }

                        radioGroup.forEach(radio => radio.classList.add('is-invalid'));

                        return false;
                    } else {
                        const radioContainer = field.closest('.form-group');
                        if (radioContainer) {
                            const feedback = radioContainer.querySelector('.invalid-feedback');
                            if (feedback) {
                                feedback.style.display = 'none';
                            }
                        }
                        radioGroup.forEach(radio => radio.classList.remove('is-invalid'));
                        return true;
                    }
                }

                if (field.type === 'file') {
                    if (field.hasAttribute('required') && field.files.length === 0) {
                        isValid = false;
                        message = 'Παρακαλώ επιλέξτε ένα αρχείο PDF';

                        let feedback = field.closest('.form-group').querySelector('.invalid-feedback');
                        if (!feedback) {
                            feedback = document.createElement('div');
                            feedback.className = 'invalid-feedback';
                            field.closest('.form-group').appendChild(feedback);
                        }
                        feedback.textContent = message;
                        feedback.style.setProperty('display', 'block', 'important');
                        field.classList.add('is-invalid');
                        return false;
                    } else {
                        let feedback = field.closest('.form-group').querySelector('.invalid-feedback');
                        if (feedback) {
                            feedback.style.display = 'none';
                        }
                        field.classList.remove('is-invalid');
                        return true;
                    }
                }

                if (isValid) {
                    clearValidationError(field);
                } else {
                    showValidationError(field, message);
                }

                return isValid;
            }


            function showValidationError(field, message) {
                if (field.type === 'file') {
                    const label = field.closest('.file-input-wrapper').querySelector('.file-input-label');
                    if (label) {
                        label.classList.add('is-invalid');
                        const feedback = field.closest('.form-group').querySelector('.invalid-feedback');
                        if (feedback) {
                            feedback.textContent = message;
                            feedback.style.display = 'block';
                        }
                    }
                } else {
                    field.classList.add('is-invalid');
                    const feedback = field.closest('.form-group').querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.textContent = message;
                        feedback.style.display = 'block';
                    }
                }
            }

            function clearValidationError(field) {
                if (field.type === 'file') {
                    const label = field.closest('.file-input-wrapper').querySelector('.file-input-label');
                    if (label) {
                        label.classList.remove('is-invalid');
                    }
                    const feedback = field.closest('.form-group').querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.style.display = 'none';
                    }
                } else if (field.name === 'pedio_ax') {
                    const radioGroup = field.closest('.form-group').querySelectorAll('.form-check-input');
                    radioGroup.forEach(radio => radio.classList.remove('is-invalid'));
                    const feedback = field.closest('.form-group').querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.style.display = 'none';
                    }
                } else {
                    field.classList.remove('is-invalid');
                    const feedback = field.parentNode.querySelector('.invalid-feedback');
                    if (feedback) {
                        feedback.style.display = 'none';
                    }
                }
            }

            function submitForm() {
                spinner.style.display = 'flex';
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Ανάρτηση σε εξέλιξη...';

                let progress = 0;
                progressWrapper.style.display = 'block';

                const progressInterval = setInterval(() => {
                    progress += Math.random() * 10;
                    if (progress > 90) progress = 90;
                    progressBar.style.width = progress + '%';
                }, 200);

                const formData = new FormData(form);

                setTimeout(() => {
                    clearInterval(progressInterval);
                    progressBar.style.width = '100%';
                    form.submit();
                }, 1000);
            }

            window.addEventListener('beforeunload', function() {
                if (submitBtn.disabled) {
                    return 'Η ανάρτηση είναι σε εξέλιξη. Είστε σίγουροι ότι θέλετε να φύγετε;';
                }
            });

            const isFinalCheckbox = document.getElementById('is_final_checkbox');
            const isFinalHidden = document.getElementById('is_final_hidden');
            isFinalCheckbox.addEventListener('change', function() {
                isFinalHidden.value = this.checked ? '1' : '0';
            });
        });
    </script>
</body>
</html>
