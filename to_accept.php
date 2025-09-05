<?php
// to_accept.php - Διαχείριση εκθέσεων προς έγκριση

if (session_status() === PHP_SESSION_NONE) {
    include 'session.php';
}
include 'db_conn.php';

// Χειρισμός μηνυμάτων flash
$flashMessage = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

$conn = $pdo;
$username = $_SESSION['username'] ?? null;
if (!$username) {
    header("Location: index.html");
    die();
}

// Δημιουργία CSRF token εάν δεν υπάρχει
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Έλεγχος δικαιωμάτων χρήστη (επόπτης ή admin)
$stmtUser = $pdo->prepare("SELECT role, dieythinsi_ekp_key FROM users WHERE username = ?");
$stmtUser->execute([$username]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: index.html");
    die("User not found.");
}

if ($user['role'] !== $_SESSION['role']) {
    session_unset();
    session_destroy();
    header("Location: phpcas.php");
    die("Η ταυτοποίηση απέτυχε!");
}

$userRole = trim($user['role']);
$dieythinsi_ekp = $user['dieythinsi_ekp_key'];

if (!in_array($userRole, ['epoptis', 'admin'])) {
    $_SESSION['message'] = "Δεν έχετε δικαίωμα πρόσβασης σε αυτή τη σελίδα.";
    header("Location: landing_page.php");
    exit();
}

/**
 * Λαμβάνει ομαδοποιημένες αναφορές με βάση την κατάσταση.
 * @param PDO $pdo
 * @param string $status
 * @param string $userRole
 * @param string $dieythinsi_ekp
 * @return array
 */
function getGroupedReports($pdo, $status, $userRole, $dieythinsi_ekp) {
    $baseQuery = "SELECT 
                     uploads.username,
                     users.name_surname,
                     dieythinseis.name as dieythinsi_name,
                     dieythinseis.type as dieythinsi_type,
                     COUNT(*) as report_count,
                     MAX(uploads.uploaded_at) as latest_upload,
                     MIN(uploads.uploaded_at) as earliest_upload
                  FROM uploads
                  JOIN users ON uploads.username = users.username
                  JOIN dieythinseis ON uploads.dieythinsi_ekp_key = dieythinseis.id
                  WHERE uploads.is_final = 1 AND uploads.is_approved = ?";

    if ($userRole === 'epoptis') {
        $baseQuery .= " AND uploads.dieythinsi_ekp_key = ?";
        $stmt = $pdo->prepare($baseQuery . " GROUP BY uploads.username ORDER BY users.name_surname");
        $stmt->execute([$status, $dieythinsi_ekp]);
    } else {
        $stmt = $pdo->prepare($baseQuery . " GROUP BY uploads.username ORDER BY users.name_surname");
        $stmt->execute([$status]);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Λαμβάνει λεπτομερείς αναφορές για έναν συγκεκριμένο χρήστη.
 * @param PDO $pdo
 * @param string $status
 * @param string $username
 * @param string $userRole
 * @param string $dieythinsi_ekp
 * @return array
 */
function getDetailedReports($pdo, $status, $username, $userRole, $dieythinsi_ekp) {
    $detailQuery = "SELECT 
                       uploads.id AS id,
                       uploads.username,
                       uploads.filename,
                       uploads.pedio_ax,
                       uploads.ekpaideytikos,
                       uploads.sxoleio,
                       uploads.eidikotita,
                       uploads.uploaded_at,
                       uploads.is_approved_comments,
                       uploads.is_mail_send,
                       uploads.is_mail_send_timestamp,
                       users.name_surname,
                       dieythinseis.name as dieythinsi_name,
                       dieythinseis.type as dieythinsi_type
                    FROM uploads
                    JOIN users ON uploads.username = users.username
                    JOIN dieythinseis ON uploads.dieythinsi_ekp_key = dieythinseis.id
                    WHERE uploads.is_final = 1 AND uploads.is_approved = ? AND uploads.username = ?";

    if ($userRole === 'epoptis') {
        $detailQuery .= " AND uploads.dieythinsi_ekp_key = ?";
        $stmt = $pdo->prepare($detailQuery . " ORDER BY uploads.uploaded_at DESC");
        $stmt->execute([$status, $username, $dieythinsi_ekp]);
    } else {
        $stmt = $pdo->prepare($detailQuery . " ORDER BY uploads.uploaded_at DESC");
        $stmt->execute([$status, $username]);
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Λήψη ομαδοποιημένων δεδομένων
$pendingGrouped = getGroupedReports($pdo, 'pending', $userRole, $dieythinsi_ekp);
$acceptedGrouped = getGroupedReports($pdo, 'accepted', $userRole, $dieythinsi_ekp);
$rejectedGrouped = getGroupedReports($pdo, 'rejected', $userRole, $dieythinsi_ekp);

// Υπολογισμός συνόλων
$totalPending = array_sum(array_column($pendingGrouped, 'report_count'));
$totalAccepted = array_sum(array_column($acceptedGrouped, 'report_count'));
$totalRejected = array_sum(array_column($rejectedGrouped, 'report_count'));
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <title>Εκθέσεις προς Έγκριση</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
    <style>
        .priority-section {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            border: 2px solid #ff9800;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        .priority-header {
            color: #e65100;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .section-divider {
            border: none;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 2rem 0;
            border-radius: 2px;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .table-section {
            margin-bottom: 3rem;
        }
        .table-section h3 {
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .urgent-row {
            background-color: #fff3cd !important;
            border-left: 4px solid #ff9800;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .btn-group .btn {
            margin: 0 1px;
        }
        .filename-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .user-count-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(3px);
        }
        .loading-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            min-width: 200px;
        }
        .loading-content .spinner-border {
            margin-bottom: 1rem;
        }
        .details-control {
            cursor: pointer;
            text-align: center;
            color: #007bff;
        }
        .details-control:hover {
            color: #0056b3;
        }
        .child-table {
            width: 100% !important;
            margin: 0;
        }
        .child-table thead th {
            background-color: #f8f9fa;
            font-size: 0.875rem;
            padding: 0.5rem;
        }
        .child-table tbody td {
            font-size: 0.875rem;
            padding: 0.5rem;
            border-top: 1px solid #dee2e6;
        }
        .child-row-content {
            background-color: #f8f9fa;
            padding: 1rem;
            border-left: 4px solid #007bff;
        }
        .master-row {
            font-weight: 500;
        }
        .master-row:hover {
            background-color: #f8f9fa;
        }
        .priority-indicator {
            position: relative;
        }
        .priority-indicator::before {
            content: '';
            position: absolute;
            left: -10px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 20px;
            background-color: #ff9800;
            border-radius: 2px;
        }
        @media (max-width: 768px) {
            .container-fluid {
                padding: 0.5rem;
            }
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            .btn-group .btn {
                margin: 1px 0;
                width: 100%;
            }
            .filename-cell {
                max-width: 120px;
            }
        }
        .child-table .dataTables_wrapper {
            padding: 1rem 0;
        }
        .child-table .dataTables_length,
        .child-table .dataTables_filter {
            padding: 0.5rem;
        }
        .child-table .dataTables_info,
        .child-table .dataTables_paginate {
            padding: 1rem 0.5rem;
        }
        .child-table .dataTables_paginate .paginate_button {
            padding: 0.25rem 0.5rem;
        }
        .child-table thead th {
            position: relative;
        }
    </style>
</head>
<body>
    <?php if ($flashMessage): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert" id="flash-msg">
        <?= htmlspecialchars($flashMessage) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    <?php include 'menu.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">
                        <i class="fas fa-tasks text-primary"></i>
                        Εκθέσεις προς Έγκριση
                    </h2>
                    <div>
                        <span class="badge bg-warning me-2">
                            <i class="fas fa-hourglass-half"></i>
                            <?= $totalPending ?> Εκκρεμείς
                        </span>
                        <a href="landing_page.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Πίσω
                        </a>
                    </div>
                </div>
                <div class="priority-section">
                    <h3 class="priority-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        ΠΡΟΤΕΡΑΙΟΤΗΤΑ: Εκθέσεις σε Εκκρεμότητα (<?= $totalPending ?>)
                    </h3>
                    <?php if (!empty($pendingGrouped)): ?>
                    <p class="mb-3 text-muted">Κλικ στο + για να δείτε τις εκθέσεις κάθε χρήστη:</p>
                    <div class="table-responsive">
                        <table id="pendingTable" class="table table-striped table-hover" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th width="5%"></th>
                                    <th>Χρήστης</th>
                                    <th>Αριθμός Εκθέσεων</th>
                                    <th>Πρώτη Έκθεση</th>
                                    <th>Τελευταία Έκθεση</th>
                                    <th>Διεύθυνση</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingGrouped as $group): ?>
                                <tr>
                                    <td class="details-control"></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($group['name_surname']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($group['username']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-file-alt"></i>
                                            <?= $group['report_count'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($group['earliest_upload'])) ?></small>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($group['latest_upload'])) ?></small>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($group['dieythinsi_type'] . ' ' . $group['dieythinsi_name']) ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success text-center">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h4>Δεν υπάρχουν εκκρεμείς εκθέσεις</h4>
                        <p>Όλες οι εκθέσεις έχουν αξιολογηθεί.</p>
                    </div>
                    <div class="table-responsive" style="display: none;">
                        <table id="pendingTable" class="table table-striped table-hover" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th width="5%"></th>
                                    <th>Χρήστης</th>
                                    <th>Αριθμός Εκθέσεων</th>
                                    <th>Πρώτη Έκθεση</th>
                                    <th>Τελευταία Έκθεση</th>
                                    <th>Διεύθυνση</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                <hr class="section-divider">
                <div class="table-section">
                    <h3>
                        <i class="fas fa-check-circle text-success"></i>
                        Εγκεκριμένες Εκθέσεις (<?= $totalAccepted ?>)
                    </h3>
                    <?php if (!empty($acceptedGrouped)): ?>
                    <div class="table-responsive">
                        <table id="acceptedTable" class="table table-striped table-hover" style="width:100%">
                            <thead class="table-success">
                                <tr>
                                    <th width="5%"></th>
                                    <th>Χρήστης</th>
                                    <th>Αριθμός Εκθέσεων</th>
                                    <th>Πρώτη Έκθεση</th>
                                    <th>Τελευταία Έκθεση</th>
                                    <th>Διεύθυνση</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($acceptedGrouped as $group): ?>
                                <tr class="master-row">
                                    <td class="details-control"></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($group['name_surname']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($group['username']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">
                                            <i class="fas fa-file-alt"></i>
                                            <?= $group['report_count'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($group['earliest_upload'])) ?></small>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($group['latest_upload'])) ?></small>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($group['dieythinsi_type'] . ' ' . $group['dieythinsi_name']) ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i>
                        Δεν υπάρχουν εγκεκριμένες εκθέσεις ακόμα.
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($rejectedGrouped)): ?>
                <div class="table-section">
                    <h3>
                        <i class="fas fa-times-circle text-danger"></i>
                        Απορριφθείσες Εκθέσεις (<?= $totalRejected ?>)
                    </h3>
                    <div class="table-responsive">
                        <table id="rejectedTable" class="table table-striped table-hover" style="width:100%">
                            <thead class="table-danger">
                                <tr>
                                    <th width="5%"></th>
                                    <th>Χρήστης</th>
                                    <th>Αριθμός Εκθέσεων</th>
                                    <th>Πρώτη Έκθεση</th>
                                    <th>Τελευταία Έκθεση</th>
                                    <th>Διεύθυνση</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejectedGrouped as $group): ?>
                                <tr class="master-row">
                                    <td class="details-control"></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($group['name_surname']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($group['username']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-file-alt"></i>
                                            <?= $group['report_count'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($group['earliest_upload'])) ?></small>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y H:i', strtotime($group['latest_upload'])) ?></small>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($group['dieythinsi_type'] . ' ' . $group['dieythinsi_name']) ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="modal fade" id="viewCommentsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-comment"></i> Σχόλια Επόπτη
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" id="commentsField" rows="6" readonly></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Κλείσιμο
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="addCommentsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="update_comments_ajax.php" id="commentsForm">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-comment-medical"></i> Σχόλια για: <span id="commentFilename"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="upload_id" id="commentUploadId" />
                        <div class="mb-3">
                            <label for="readyMessages" class="form-label">
                                <i class="fas fa-list"></i> Έτοιμα Μηνύματα (προαιρετικό)
                            </label>
                            <select class="form-select" id="readyMessages">
                                <option value="">-- Επιλέξτε έτοιμο μήνυμα --</option>
                            </select>
                            <small class="text-muted">Επιλέξτε ένα έτοιμο μήνυμα για να το προσθέσετε στα σχόλια</small>
                        </div>
                        <div class="mb-3">
                            <label for="commentsTextarea" class="form-label">Σχόλια:</label>
                            <textarea class="form-control" name="comments" id="commentsTextarea" rows="6"
                                      placeholder="Εισάγετε τα σχόλιά σας εδώ..."></textarea>
                            <small class="text-muted">Μπορείτε να επεξεργαστείτε το κείμενο μετά την επιλογή έτοιμου μηνύματος</small>
                        </div>
                        <div class="mb-3">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-secondary" id="appendMessageBtn">
                                    <i class="fas fa-plus"></i> Προσθήκη
                                </button>
                                <button type="button" class="btn btn-outline-warning" id="replaceMessageBtn">
                                    <i class="fas fa-exchange-alt"></i> Αντικατάσταση
                                </button>
                                <button type="button" class="btn btn-outline-danger" id="clearCommentsBtn">
                                    <i class="fas fa-trash"></i> Καθαρισμός
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Αποθήκευση Σχολίων
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Άκυρο
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script>
        let pendingTable, acceptedTable, rejectedTable;
        const currentUsername = '<?= $username ?>';
        const tables = {};
        const badgeClasses = {
            pending: 'bg-warning',
            accepted: 'bg-success',
            rejected: 'bg-danger'
        };

        function showFlashMessage(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show m-3" role="alert" id="ajax-flash-msg" style="position: fixed; top: 70px; right: 20px; z-index: 1050;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('#ajax-flash-msg').remove();
            $('body').append(alertHtml);
            setTimeout(() => {
                $('#ajax-flash-msg').alert('close');
            }, 5000);
        }

        function showLoadingOverlay() {
            const overlay = `<div id="loadingOverlay" class="loading-overlay"><div class="loading-content"><div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"><span class="visually-hidden">Loading...</span></div><div class="mt-3 text-primary fw-bold">Ενημέρωση...</div></div></div>`;
            $('body').append(overlay);
        }

        function hideLoadingOverlay() {
            $('#loadingOverlay').remove();
        }

        function formatSingleReportRow(report, status) {
            let html = '<tr>';
            html += '<td></td>';
            html += `<td class="filename-cell" title="${escapeHtml(report.filename)}"><i class="fas fa-file-pdf text-danger"></i> ${escapeHtml(report.filename)}</td>`;
            html += `<td><span class="badge bg-primary">${escapeHtml(report.pedio_ax)}</span></td>`;
            html += `<td>${escapeHtml(report.ekpaideytikos)}</td>`;
            html += `<td>${escapeHtml(report.sxoleio)}</td>`;
            html += `<td>${escapeHtml(report.eidikotita)}</td>`;
            html += `<td><small>${formatDate(report.uploaded_at)}<br>${formatTime(report.uploaded_at)}</small></td>`;
            html += `<td>${(report.is_approved_comments && report.is_approved_comments.trim() !== '') ? '<i class="fas fa-comment text-info"></i>' : '<i class="fas fa-minus text-muted"></i>'}</td>`;
            html += '<td class="action-buttons"><div class="btn-group" role="group">';
            html += `<a href="download.php?id=${encodeURIComponent(report.id)}" target="_blank" class="btn btn-sm btn-outline-info" title="Κατέβασμα"><i class="fas fa-download"></i></a>`;
            html += `<button class="btn btn-sm btn-info sendEmailBtn" data-id="${report.id}" data-filename="${escapeHtml(report.filename)}" data-comments="${escapeHtml(report.is_approved_comments || '')}" data-mail-sent="${report.is_mail_send}" data-user-name="${escapeHtml(report.name_surname)}" data-username="${escapeHtml(report.username)}" title="Αποστολή Email"><i class="fas fa-envelope"></i> ${report.is_mail_send == 1 ? '<i class="fas fa-check-circle text-light" style="font-size: 0.7em;"></i>' : ''}</button>`;
            const form = (action, btnClass, title, icon) => `<form method="post" action="approve_upload_ajax.php" style="display:inline-block;" class="approvalForm"><input type="hidden" name="upload_id" value="${report.id}" /><input type="hidden" name="action" value="${action}" /><button type="submit" class="btn btn-sm ${btnClass}" title="${title}"><i class="fas ${icon}"></i></button></form>`;
            const commentsBtn = (title, icon) => `<button class="btn btn-sm btn-warning addCommentsBtn" data-id="${report.id}" data-filename="${escapeHtml(report.filename)}" data-comments="${escapeHtml(report.is_approved_comments || '')}" data-bs-toggle="modal" data-bs-target="#addCommentsModal" title="${title}"><i class="fas ${icon}"></i></button>`;
            if (status === 'pending') {
                html += form('approve', 'btn-success', 'Έγκριση', 'fa-check');
                html += form('reject', 'btn-danger', 'Απόρριψη', 'fa-times');
                html += commentsBtn('Προσθήκη σχολίων', 'fa-comment');
            } else if (status === 'accepted') {
                html += form('pending', 'btn-warning', 'Επαναφορά σε εκκρεμότητα', 'fa-undo');
                html += commentsBtn('Επεξεργασία σχολίων', 'fa-edit');
                if (report.is_approved_comments && report.is_approved_comments.trim() !== '') {
                    html += `<button class="btn btn-sm btn-secondary viewCommentsBtn" data-comments="${escapeHtml(report.is_approved_comments)}" data-bs-toggle="modal" data-bs-target="#viewCommentsModal" title="Προβολή σχολίων"><i class="fas fa-eye"></i></button>`;
                }
            } else if (status === 'rejected') {
                html += form('approve', 'btn-success', 'Έγκριση', 'fa-check');
                html += form('pending', 'btn-warning', 'Επαναφορά σε εκκρεμότητα', 'fa-undo');
                html += commentsBtn('Επεξεργασία σχολίων', 'fa-edit');
                if (report.is_approved_comments && report.is_approved_comments.trim() !== '') {
                    html += `<button class="btn btn-sm btn-secondary viewCommentsBtn" data-comments="${escapeHtml(report.is_approved_comments)}" data-bs-toggle="modal" data-bs-target="#viewCommentsModal" title="Προβολή σχολίων"><i class="fas fa-eye"></i></button>`;
                }
            }
            html += '</div></td></tr>';
            return html;
        }

        function toggleEmptyState(status, isEmpty) {
            const tableContainer = $(`#${status}Table`).closest('.table-responsive');
            const emptyAlert = tableContainer.siblings('.alert');
            if (isEmpty) {
                tableContainer.hide();
                if (emptyAlert.length === 0) {
                    tableContainer.before(`
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h4>Δεν υπάρχουν ${status === 'pending' ? 'εκκρεμείς' : (status === 'accepted' ? 'εγκεκριμένες' : 'απορριφθείσες')} εκθέσεις</h4>
                            <p>Όλες οι εκθέσεις έχουν αξιολογηθεί.</p>
                        </div>
                    `);
                } else {
                    emptyAlert.show();
                }
            } else {
                tableContainer.show();
                emptyAlert.hide();
            }
        }

        function updateUITable(reportRowElement, data) {
            const sourceTableAPI = tables[data.source_status];
            let sourceParentRow = null;
            sourceTableAPI.rows().every(function() {
                const rowNode = this.node();
                const usernameInRow = $(rowNode).find('td:eq(1) small').text().trim();
                if (usernameInRow === data.user_username) {
                    sourceParentRow = this;
                    return false;
                }
            });
            if (!sourceParentRow) {
                reportRowElement.remove();
                updateTargetTable(data);
                return;
            }
            const sourceRowData = sourceParentRow.data();
            reportRowElement.remove();
            const sourceUserCount = parseInt(data.updated_user_counts[data.source_status]);
            if (sourceUserCount === 0) {
                sourceParentRow.child.hide();
                sourceParentRow.remove().draw();
                if (tables[data.source_status].rows().count() === 0) {
                    toggleEmptyState(data.source_status, true);
                }
            } else {
                if (sourceRowData && Array.isArray(sourceRowData)) {
                    const sourceBadgeClass = badgeClasses[data.source_status];
                    const newBadgeHTML = `<span class="badge ${sourceBadgeClass}"><i class="fas fa-file-alt"></i> ${sourceUserCount}</span>`;
                    sourceRowData[2] = newBadgeHTML;
                    sourceParentRow.data(sourceRowData).draw(false);
                } else {
                    const sourceBadgeClass = badgeClasses[data.source_status];
                    const newBadgeHTML = `<span class="badge ${sourceBadgeClass}"><i class="fas fa-file-alt"></i> ${sourceUserCount}</span>`;
                    $(sourceParentRow.node()).find('td:eq(2)').html(newBadgeHTML);
                }
            }
            updateTargetTable(data);
        }

        function updateTargetTable(data) {
            const targetTableAPI = tables[data.target_status];
            if (!targetTableAPI) return;
            let targetParentRow = null;
            targetTableAPI.rows().every(function() {
                const rowNode = this.node();
                if ($(rowNode).find('td:eq(1) small').text().trim() === data.user_username) {
                    targetParentRow = this;
                    return false;
                }
            });
            const targetUserCount = parseInt(data.updated_user_counts[data.target_status]);
            if (targetParentRow) {
                const targetRowData = targetParentRow.data();
                const targetBadgeClass = badgeClasses[data.target_status];
                targetRowData[2] = `<span class="badge ${targetBadgeClass}"><i class="fas fa-file-alt"></i> ${targetUserCount}</span>`;
                targetParentRow.data(targetRowData).draw(false);
            } else {
                toggleEmptyState(data.target_status, false);
                const targetBadgeClass = badgeClasses[data.target_status];
                const newRowData = [
                    null,
                    `<div><strong>${escapeHtml(data.user_info.name_surname)}</strong><br><small class="text-muted">${escapeHtml(data.user_username)}</small></div>`,
                    `<span class="badge ${targetBadgeClass}"><i class="fas fa-file-alt"></i> ${targetUserCount}</span>`,
                    `<small>${formatDate(data.moved_report.uploaded_at)}</small>`,
                    `<small>${formatDate(data.moved_report.uploaded_at)}</small>`,
                    `<small>${escapeHtml(data.user_info.dieythinsi_type + ' ' + data.user_info.dieythinsi_name)}</small>`
                ];
                targetParentRow = targetTableAPI.row.add(newRowData).draw().row(':last');
            }
            if (targetParentRow && $(targetParentRow.node()).hasClass('shown')) {
                const newReportRowHTML = formatSingleReportRow(data.moved_report, data.target_status);
                $(targetParentRow.node()).next('tr.dt-hasChild').find('table.child-table tbody').append(newReportRowHTML);
            }
            const totals = data.total_counts;
            $('.priority-section h3.priority-header').html(`<i class="fas fa-exclamation-triangle"></i> ΠΡΟΤΕΡΑΙΟΤΗΤΑ: Εκθέσεις σε Εκκρεμότητα (${totals.totalPending || 0})`);
            $('h2 + div .bg-warning').html(`<i class="fas fa-hourglass-half"></i> ${totals.totalPending || 0} Εκκρεμείς`);
            $('.table-section h3:contains("Εγκεκριμένες")').html(`<i class="fas fa-check-circle text-success"></i> Εγκεκριμένες Εκθέσεις (${totals.totalAccepted || 0})`);
            $('.table-section h3:contains("Απορριφθείσες")').html(`<i class="fas fa-times-circle text-danger"></i> Απορριφθείσες Εκθέσεις (${totals.totalRejected || 0})`);
        }
        
        // Βοηθητικές συναρτήσεις
        function formatDate(d) { return new Date(d).toLocaleDateString('el-GR'); }
        function formatTime(d) { return new Date(d).toLocaleTimeString('el-GR', { hour: '2-digit', minute: '2-digit' }); }
        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // Έτοιμο έγγραφο - Αρχικοποίηση
        $(document).ready(function () {
            const commonOptions = {
                responsive: false,
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/el.json' },
                pageLength: 25,
                columnDefs: [{ targets: 0, className: 'details-control', orderable: false, data: null, defaultContent: '<i class="fas fa-plus-circle"></i>' }]
            };
            
            if ($('#pendingTable').length) tables.pending = $('#pendingTable').DataTable({...commonOptions, order: [[4, 'asc']]});
            if ($('#acceptedTable').length) tables.accepted = $('#acceptedTable').DataTable({...commonOptions, order: [[1, 'asc']]});
            if ($('#rejectedTable').length) tables.rejected = $('#rejectedTable').DataTable({...commonOptions, order: [[1, 'asc']]});
            
            // Listener για το modal Προβολής Σχολίων
            $('#viewCommentsModal').on('show.bs.modal', function (event) {
                const button = $(event.relatedTarget); 
                const comments = button.data('comments');
                $(this).find('#commentsField').val(comments);
            });

            // Listener για το modal Προσθήκης/Επεξεργασίας Σχολίων
            $('#addCommentsModal').on('show.bs.modal', function (event) {
                const button = $(event.relatedTarget);
                const uploadId = button.data('id');
                const filename = button.data('filename');
                const existingComments = button.data('comments');
                const modal = $(this);
                modal.find('#commentUploadId').val(uploadId);
                modal.find('#commentFilename').text(filename);
                modal.find('#commentsTextarea').val(existingComments);
            });
            
            // Listener για άνοιγμα/κλείσιμο child rows
            $('table.table').on('click', 'td.details-control', function () {
                const tr = $(this).closest('tr');
                const tableId = tr.closest('table').attr('id');
                const status = tableId.replace('Table', '');
                const tableAPI = tables[status];
                const row = tableAPI.row(tr);
                const username = tr.find('td:eq(1) small').text().trim();
                
                if (row.child.isShown()) {
                    const childTable = $(row.child()).find('.child-table').DataTable();
                    if (childTable) childTable.destroy();
                    row.child.hide();
                    tr.removeClass('shown');
                    $(this).find('i').removeClass('fa-minus-circle').addClass('fa-plus-circle');
                } else {
                    row.child('<div class="text-center p-3"><i class="fas fa-spinner fa-spin"></i> Φόρτωση...</div>').show();
                    tr.addClass('shown');
                    $(this).find('i').removeClass('fa-plus-circle').addClass('fa-minus-circle');
                    
                    $.ajax({
                        url: 'get_user_reports_sec.php',
                        type: 'GET',
                        data: { username: username, status: status },
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                row.child(formatChildRow(res.data, status)).show();
                                const childTableId = `child-${status}-${username}`;
                                $(`#${childTableId}`).DataTable({
                                    "pageLength": 5,
                                    "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "Όλα"]],
                                    "language": { "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/el.json" },
                                    "responsive": true,
                                    "ordering": true,
                                    "info": true,
                                    "searching": true,
                                    "columns": [
                                        { "orderable": false, width: "5%" },
                                        { "orderable": true },
                                        { "orderable": true },
                                        { "orderable": true },
                                        { "orderable": true },
                                        { "orderable": true },
                                        { "orderable": true },
                                        { "orderable": false },
                                        { "orderable": false }
                                    ],
                                    "order": [[6, "desc"]]
                                });
                            } else {
                                row.child('<div class="alert alert-danger">Σφάλμα φόρτωσης</div>').show();
                            }
                        },
                        error: function() {
                            row.child('<div class="alert alert-danger">Σφάλμα φόρτωσης</div>').show();
                        }
                    });
                }
            });
            
            // Χειριστής για τις φόρμες έγκρισης/απόρριψης
            $(document).on('submit', 'form.approvalForm', function (e) {
                e.preventDefault();
                const form = $(this);
                const uploadId = form.find('input[name="upload_id"]').val();
                const action = form.find('input[name="action"]').val();
                const reportRowElement = form.closest('tr');
                const submitBtn = form.find('button[type="submit"]');
                
                submitBtn.prop('disabled', true);
                showLoadingOverlay();

                $.ajax({
                    url: 'approve_upload_ajax.php', 
                    type: 'POST', 
                    data: { upload_id: uploadId, action: action }, 
                    dataType: 'json',
                    timeout: 30000,
                    success: function (response) {
                        if (response.success) {
                            updateUITable(reportRowElement, response.data);
                            showFlashMessage(response.message, 'success');
                        } else {
                            showFlashMessage(response.message || 'Προέκυψε άγνωστο σφάλμα.', 'danger');
                            submitBtn.prop('disabled', false);
                        }
                    },
                    error: function() {
                        showFlashMessage('Σφάλμα κατά την επικοινωνία με τον διακομιστή.', 'danger');
                        submitBtn.prop('disabled', false);
                    },
                    complete: function() {
                        hideLoadingOverlay();
                    }
                });
            });
            
            // Χειριστής για κουμπιά αποστολής email
            $(document).on('click', '.sendEmailBtn', function() {
                const button = $(this);
                const uploadId = button.data('id');
                const filename = button.data('filename');
                const comments = button.data('comments');
                const mailSent = button.data('mail-sent');
                const userName = button.data('user-name');
                const userUsername = button.data('username');
                const userEmail = userUsername + '@sch.gr';
                const epoptisEmail = currentUsername + '@sch.gr';
                let confirmMessage = `Θέλετε να στείλετε email με τα σχόλια για το αρχείο "${filename}"?\n\nΤο email θα σταλεί στις διευθύνσεις:\n• ${userEmail} (${userName})\n• ${epoptisEmail}`;
                if (mailSent == 1) {
                    confirmMessage += '\n\nΠροσοχή: Το email έχει ήδη σταλεί για αυτή την έκθεση.';
                }
                if (!confirm(confirmMessage)) return;
                const originalHtml = button.html();
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Αποστολή...');
                $.ajax({
                    url: 'send_email_ajax.php',
                    type: 'POST',
                    data: {
                        upload_id: uploadId,
                        comments: comments
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showFlashMessage(response.message, 'success');
                            updateEmailStatus(uploadId, true);
                            button.html('<i class="fas fa-envelope"></i> <i class="fas fa-check-circle text-light" style="font-size: 0.7em;"></i>');
                            button.data('mail-sent', 1);
                        } else {
                            showFlashMessage(response.message, 'danger');
                            button.prop('disabled', false).html(originalHtml);
                        }
                    },
                    error: function() {
                        showFlashMessage('Σφάλμα κατά την αποστολή email. Παρακαλώ δοκιμάστε ξανά.', 'danger');
                        button.prop('disabled', false).html(originalHtml);
                    }
                });
            });

            function updateEmailStatus(uploadId, success) {
                const now = new Date();
                const timestamp = now.toLocaleDateString('el-GR') + ' ' + now.toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit'});
                const reportRow = $(`tr:has(button[data-id="${uploadId}"])`);
                if (reportRow.length) {
                    if (success) {
                        const emailBtn = reportRow.find('.sendEmailBtn');
                        emailBtn.html('<i class="fas fa-envelope"></i> <i class="fas fa-check-circle text-light" style="font-size: 0.7em;"></i>');
                        emailBtn.data('mail-sent', 1);
                        emailBtn.attr('title', `Email στάλθηκε στις ${timestamp}`);
                    }
                }
            }
            
            // Φόρτωση έτοιμων μηνυμάτων
            $('#addCommentsModal').on('show.bs.modal', function() {
                loadReadyMessages();
            });

            function loadReadyMessages() {
                $.ajax({
                    url: 'fetch_ready_messages.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const select = $('#readyMessages');
                            select.empty().append('<option value="">-- Επιλέξτε έτοιμο μήνυμα --</option>');
                            response.messages.forEach(function(message) {
                                select.append(`<option value="${message.id}" data-content="${escapeHtml(message.message_content)}">${escapeHtml(message.message_title)}</option>`);
                            });
                        }
                    }
                });
            }

            // Χειριστής για υποβολή σχολίων
            $(document).on('submit', '#commentsForm', function (e) {
                e.preventDefault();
                const form = $(this);
                const uploadId = form.find('input[name="upload_id"]').val();
                const comments = form.find('textarea[name="comments"]').val();
                const submitBtn = form.find('button[type="submit"]');
                const originalHtml = submitBtn.html();
                
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Αποθήκευση...');
                
                $.ajax({
                    url: 'update_comments_ajax.php',
                    type: 'POST',
                    data: { upload_id: uploadId, comments: comments },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showFlashMessage(response.message, 'success');
                            $('#addCommentsModal').modal('hide');
                            const updatedRow = $(`tr:has(button[data-id="${uploadId}"])`);
                            if (updatedRow.length) {
                                const editBtn = updatedRow.find('.addCommentsBtn');
                                const viewBtn = updatedRow.find('.viewCommentsBtn');
                                editBtn.data('comments', response.comments);
                                viewBtn.data('comments', response.comments);
                                const commentsIconCell = updatedRow.find('td:nth-child(8)'); 
                                if (response.comments && response.comments.trim() !== '') {
                                    commentsIconCell.html('<i class="fas fa-comment text-info"></i>');
                                } else {
                                    commentsIconCell.html('<i class="fas fa-minus text-muted"></i>');
                                }
                            }
                        } else {
                            showFlashMessage(response.message, 'danger');
                        }
                    },
                    error: function() {
                        showFlashMessage('Σφάλμα κατά την ενημέρωση σχολίων.', 'danger');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html(originalHtml);
                    }
                });
            });

            // Χειριστές για τα κουμπιά προσθήκης/αντικατάστασης σχολίων
            $('#readyMessages').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                if (selectedOption.val()) {
                    $('#readyMessages').data('selected-message', selectedOption.data('content'));
                }
            });

            $('#appendMessageBtn').on('click', function() {
                const selectedMessage = $('#readyMessages').data('selected-message');
                if (selectedMessage) {
                    const currentContent = $('#commentsTextarea').val();
                    const newContent = currentContent ? currentContent + '\n\n' + selectedMessage : selectedMessage;
                    $('#commentsTextarea').val(newContent);
                    $('#readyMessages').val('').removeData('selected-message');
                    showFlashMessage('Το μήνυμα προστέθηκε στα σχόλια!', 'info');
                } else {
                    showFlashMessage('Παρακαλώ επιλέξτε πρώτα ένα έτοιμο μήνυμα.', 'warning');
                }
            });

            $('#replaceMessageBtn').on('click', function() {
                const selectedMessage = $('#readyMessages').data('selected-message');
                if (selectedMessage) {
                    $('#commentsTextarea').val(selectedMessage);
                    $('#readyMessages').val('').removeData('selected-message');
                    showFlashMessage('Το περιεχόμενο αντικαταστάθηκε με το επιλεγμένο μήνυμα!', 'info');
                } else {
                    showFlashMessage('Παρακαλώ επιλέξτε πρώτα ένα έτοιμο μήνυμα.', 'warning');
                }
            });

            $('#clearCommentsBtn').on('click', function() {
                if (confirm('Είστε σίγουροι ότι θέλετε να καθαρίσετε όλα τα σχόλια;')) {
                    $('#commentsTextarea').val('');
                    $('#readyMessages').val('').removeData('selected-message');
                    showFlashMessage('Τα σχόλια καθαρίστηκαν!', 'info');
                }
            });
        });
        
        // Συναρτήσεις για την μορφοποίηση των child tables
        function formatChildRow(data, status) {
            let tableClass = status === 'pending' ? 'table-warning' : (status === 'accepted' ? 'table-success' : 'table-danger');
            let headerClass = status === 'pending' ? 'bg-warning' : (status === 'accepted' ? 'bg-success text-white' : 'bg-danger text-white');
            let html = `
                <div class="child-row-content">
                    <div class="table-responsive">
                        <table class="table ${tableClass} child-table" id="child-${status}-${data[0]?.username || 'table'}">
                            <thead>
                                <tr class="${headerClass}">
                                    <th width="5%"></th>
                                    <th>Αρχείο</th>
                                    <th>Πεδίο</th>
                                    <th>Εκπαιδευτικός</th>
                                    <th>Σχολείο</th>
                                    <th>Ειδικότητα</th>
                                    <th>Ημερομηνία</th>
                                    <th>Σχόλια</th>
                                    <th>Ενέργειες</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.map(report => formatSingleReportRow(report, status)).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>`;
            return html;
        }
    </script>
</body>
</html>