<?php
if (session_status() === PHP_SESSION_NONE) {
    include 'session.php';
}
include 'db_conn.php';

if (isset($_SESSION['message'])) {
    $flashMessage = $_SESSION['message'];
    unset($_SESSION['message']);
} else {
    $flashMessage = null;
}

$conn = $pdo;
$username = $_SESSION['username'] ?? null;
if (!$username) {
    header("Location: index.html");
    die();
}

$stmtUser = $pdo->prepare("SELECT role, dieythinsi_ekp_key FROM users WHERE username = ?");
$stmtUser->execute([$username]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header("Location: index.html");
    die("User not found.");
}
$userRole = trim($user['role']);
$dieythinsi_ekp = $user['dieythinsi_ekp_key'];

$baseQuery = "SELECT
                u.username,
                u.name_surname,
                d.name as dieythinsi_name,
                d.type as dieythinsi_type,
                COUNT(uploads.id) as total_files,
                SUM(CASE WHEN uploads.is_final = 1 THEN 1 ELSE 0 END) as final_files,
                SUM(CASE WHEN uploads.is_approved = 'accepted' THEN 1 ELSE 0 END) as approved_files,
                SUM(CASE WHEN uploads.is_approved = 'rejected' THEN 1 ELSE 0 END) as rejected_files,
                SUM(CASE WHEN uploads.is_approved = 'pending' THEN 1 ELSE 0 END) as pending_files,
                MAX(uploads.uploaded_at) as last_upload
              FROM users u
              JOIN dieythinseis d ON u.dieythinsi_ekp_key = d.id
              INNER JOIN uploads ON u.username = uploads.username";

if ($userRole === 'admin') {
    $stmt = $pdo->query($baseQuery . " GROUP BY u.username, u.name_surname, d.name, d.type ORDER BY u.name_surname");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($userRole === 'user') {
    $stmt = $pdo->prepare($baseQuery . " WHERE u.username = ? AND uploads.upload_from = ? GROUP BY u.username, u.name_surname, d.name, d.type ORDER BY u.name_surname");
    $stmt->execute([$username, $username]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif (in_array($userRole, ['epoptis', 'grammateia'])) {
    $stmt = $pdo->prepare($baseQuery . " WHERE u.dieythinsi_ekp_key = ? GROUP BY u.username, u.name_surname, d.name, d.type ORDER BY u.name_surname");
    $stmt->execute([$dieythinsi_ekp]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $_SESSION['message'] = "Δεν υπάρχει τέτοιος χρήστης. Παρακαλώ επικοινωνήστε με τον διαχειριστή!";
    header("Location: logout.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Διαχείριση Εκθέσεων</title>

    <link href="https:
    <link href="https:
    <link href="https:
    <link href="https:
<link href="css/styles.css" rel="stylesheet" />
    
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
                <h2 class="mb-3">Διαχείριση Εκθέσεων</h2>

                <div class="table-responsive">
                    <table id="usersTable" class="table table-striped table-hover" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th></th> <!-- For expand button -->
                                <th>Όνομα Χρήστη</th>
                                <th>Ονοματεπώνυμο</th>
                                <th>Διεύθυνση</th>
                                <th>Σύνολο Αρχείων</th>
                                <th>Τελικά</th>
                                <th>Εγκριθέντα</th>
                                <th>Απορριφθέντα</th>
                                <th>Εκκρεμή</th>
                                <th>Τελευταία Ανάρτηση</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($users as $user_row): ?>
                        <tr>
                            <td class="details-control" title="Κλικ για προβολή αρχείων">
                                <i class="fas fa-plus-circle"></i>
                            </td>
                            <td><?= htmlspecialchars($user_row['username']) ?></td>
                            <td><?= htmlspecialchars($user_row['name_surname']) ?></td>
                            <td><?= htmlspecialchars($user_row['dieythinsi_type']) ?> <?= htmlspecialchars($user_row['dieythinsi_name']) ?></td>
                            <td><span class="badge bg-primary"><?= $user_row['total_files'] ?></span></td>
                            <td><span class="badge bg-success"><?= $user_row['final_files'] ?></span></td>
                            <td><span class="badge bg-success"><?= $user_row['approved_files'] ?></span></td>
                            <td><span class="badge bg-danger"><?= $user_row['rejected_files'] ?></span></td>
                            <td><span class="badge bg-warning"><?= $user_row['pending_files'] ?></span></td>
                            <td>
                                <?php if ($user_row['last_upload']): ?>
                                    <small><?= date('d/m/Y H:i', strtotime($user_row['last_upload'])) ?></small>
                                <?php else: ?>
                                    <small>-</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-12">
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <?php if ($userRole === 'user'): ?>
                    <a href="upload_form.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Νέα Ανάρτηση Έκθεσης
                    </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Keep all existing modals -->
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="edit_upload_ajax.php" id="editForm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Επεξεργασία Έκθεσης</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="modal-id" />
                        <div class="mb-3">
                            <label for="modal-name" class="form-label">Διεύθυνση</label>
                            <select class="form-select readonly-field" name="dieythinsi_ekp_key" id="modal-name" readonly required>
                                <?php
                                $stmtAdmin = $conn->prepare("SELECT * FROM dieythinseis WHERE dieythinseis.id = ? ORDER BY type, name");
                                $stmtAdmin->execute([$dieythinsi_ekp]);
                                $administrations_result = $stmtAdmin->fetchAll();
                                foreach ($administrations_result as $rowA) {
                                    echo "<option value='" . htmlspecialchars($rowA['id']) . "'>" .
                                        htmlspecialchars($rowA['type']) . ' ' . htmlspecialchars($rowA['name']) .
                                        "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="modal-upload_from" class="form-label">Ανάρτηση από:</label>
                            <input type="text" class="form-control readonly-field" name="upload_from" id="modal-upload_from" readonly />
                        </div>
                        <div class="mb-3">
                            <label for="modal-ekpaideytikos" class="form-label">Εκπαιδευτικός</label>
                            <input type="text" class="form-control" name="ekpaideytikos" id="modal-ekpaideytikos" required />
                        </div>
                        <div class="mb-3">
                            <label for="modal-sxoleio" class="form-label">Σχολείο</label>
                            <input type="text" class="form-control" name="sxoleio" id="modal-sxoleio" required />
                        </div>
                        <div class="mb-3">
                            <label for="modal-eidikotita" class="form-label">Ειδικότητα</label>
                            <input type="text" class="form-control" name="eidikotita" id="modal-eidikotita" required />
                        </div>
                        <div class="mb-3">
                            <label for="modal-pedio_ax" class="form-label">Πεδίο Αξιολόγησης</label>
                            <select class="form-select" name="pedio_ax" id="modal-pedio_ax">
                                <option value="A1">Πεδίο Α1</option>
                                <option value="A2">Πεδίο Α2</option>
                                <option value="B">Πεδίο Β</option>
                            </select>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="modal-is_final_checkbox" />
                            <label class="form-check-label" for="modal-is_final_checkbox">Οριστική Ανάρτηση</label>
                            <input type="hidden" name="is_final" id="modal-is_final" value="0" />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Αποθήκευση
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Άκυρο
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="delete_upload_ajax.php" id="deleteForm">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteModalLabel">
                            <i class="fas fa-exclamation-triangle"></i> Επιβεβαίωση Διαγραφής
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
                    </div>
                    <div class="modal-body">
                        Είστε σίγουροι ότι θέλετε να διαγράψετε το αρχείο <strong id="deleteFilename"></strong>;
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-warning"></i> Αυτή η ενέργεια δεν μπορεί να αναιρεθεί!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="delete_id" id="deleteId" />
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Ακύρωση
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Διαγραφή
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- View Comments Modal -->
    <div class="modal fade" id="viewCommentsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-comment"></i> Σχόλια Εποπτη
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

    <!-- Add/Edit Comments Modal -->
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

    <script src="https:
    <script src="https:
    <script src="https:
    <script src="https:
    <script src="https:
    <script src="https:

    <script>
        let usersTable;
        const userRole = '<?= $userRole ?>';
        const currentUsername = '<?= $username ?>';

        function showFlashMessage(message, type) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show m-3" role="alert" id="ajax-flash-msg">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;

            $('#ajax-flash-msg').remove();
            $('body').prepend(alertHtml);
            setTimeout(() => {
                $('#ajax-flash-msg').alert('close');
            }, 5000);
        }

        function format(username) {
            return `
                <div class="child-table">
                    <div class="user-summary">
                        <div class="row">
                            <div class="col-md-8">
                                <h5><i class="fas fa-user"></i> ${username}</h5>
                                <p class="mb-0">Λεπτομέρειες αρχείων</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <button class="btn btn-light btn-sm" onclick="refreshUserFiles('${username}')">
                                    <i class="fas fa-sync-alt"></i> Ανανέωση
                                </button>
                            </div>
                        </div>
                    </div>
                    <div id="files-${username}" class="mt-3">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Φόρτωση...</span>
                            </div>
                            <p class="mt-2">Φόρτωση αρχείων...</p>
                        </div>
                    </div>
                </div>
            `;
        }

function loadUserFiles(username) {
    $.ajax({
        url: 'get_user_files_ajax.php',
        type: 'POST',
        data: { username: username },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const filesHtml = generateFilesTable(response.files);
                $(`#files-${username}`).html(filesHtml);

                const table = $(`#files-${username} .child-files-table`).DataTable({
                    "pageLength": 5,
                    "lengthMenu": [[5, 10, 25, -1], [5, 10, 25, "Όλα"]],
                    "language": {
                        "url": "
                    },
                    "order": [[8, "desc"]], 
                    "responsive": true,
                    "columns": [
                        { "width": "15%" }, 
                        { "width": "5%" },  
                        { "width": "7%" },  
                        { "width": "8%" },  
                        { "width": "10%" }, 
                        { "width": "10%" }, 
                        { "width": "10%" }, 
                        { "width": "7%" },  
                        { "width": "8%" },  
                        { "width": "5%" },  
                        { "width": "5%" },  
                        { "width": "10%" }  
                    ],
                                        initComplete: function () {
                        
                        this.api().columns().every(function (index) {
                            
                            if (index >= 9) return;
                            
                            const column = this;
                            let title = $(column.header()).text();

                            if (title === 'Πεδίο') {
                                const select = $(`
                                    <select class="form-select form-select-sm">
                                        <option value="">Όλα</option>
                                        <option value="A1">A1</option>
                                        <option value="A2">A2</option>
                                        <option value="B">B</option>
                                    </select>
                                `).appendTo($(column.header()))
                                .on('change', function() {
                                    const val = $.fn.dataTable.util.escapeRegex($(this).val());
                                    column.search(val ? '^'+val+'$' : '', true, false).draw();
                                });
                                return; 
                            }
                            else if (title === 'Εγκρίθηκε') {
                                const select = $(`
                                    <select class="form-select form-select-sm">
                                        <option value="">Όλα</option>
                                        <option value="Εγκρίθηκε">Εγκρίθηκε</option>
                                        <option value="Απορρίφθηκε">Απορρίφθηκε</option>
                                        <option value="Εκκρεμεί">Εκκρεμεί</option>
                                    </select>
                                `).appendTo($(column.header()))
                                .on('change', function() {
                                    const val = $.fn.dataTable.util.escapeRegex($(this).val());
                                    column.search(val ? val : '', true, false).draw();
                                });
                                return; 
                            }

                            const input = $(`<input type="text" class="form-control form-control-sm" placeholder="Φίλτρο ${title}"/>`)
                                .appendTo($(column.header()))
                                .on('keyup change clear', function () {
                                    if (column.search() !== this.value) {
                                        column.search(this.value).draw();
                                    }
                                });
                        });
                    }

                });

                const style = `
                    <style>
                        .child-files-table thead input,
                        .child-files-table thead select {
                            width: 100%;
                            margin-top: 5px;
                            font-size: 0.8rem;
                        }
                        .child-files-table thead th {
                            vertical-align: top;
                        }
                    </style>
                `;
                if (!$('head').find('style:contains("child-files-table")').length) {
                    $('head').append(style);
                }
            } else {
                $(`#files-${username}`).html(`<div class="alert alert-warning">Σφάλμα φόρτωσης αρχείων: ${response.message}</div>`);
            }
        },
        error: function() {
            $(`#files-${username}`).html(`<div class="alert alert-danger">Σφάλμα σύνδεσης με τον διακομιστή</div>`);
        }
    });
}

function refreshUserFiles(username) {
    
    const existingTable = $(`#files-${username} .child-files-table`).DataTable();
    if (existingTable) {
        existingTable.destroy();
    }
    
    $(`#files-${username}`).html(`
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Φόρτωση...</span>
            </div>
            <p class="mt-2">Ανανέωση αρχείων...</p>
        </div>
    `);
    loadUserFiles(username);
}

 function generateFilesTable(files) {
    if (!files || files.length === 0) {
        return '<div class="alert alert-info"><i class="fas fa-info-circle"></i> Δεν βρέθηκαν αρχεία για αυτόν τον χρήστη.</div>';
    }

    let html = `
        <div class="table-responsive">
            <table class="table table-sm table-bordered child-files-table">
                <thead class="table-secondary">
                    <tr>
                        <th>Αρχείο</th>
                        <th>Πεδίο</th>
                        <th>Τελικό</th>
                        <th>Εγκρίθηκε</th>
                        <th>Ημ.Έγκρισης</th>
                        <th>Εκπαιδευτικός</th>
                        <th>Σχολείο</th>
                        <th>Ειδικότητα</th>
                        <th>Timestamp</th>
                        <th>PDF</th>
                        <th>Email</th>
                        <th>Ενέργειες</th>
                    </tr>
                </thead>
                <tbody>
    `;

    files.forEach(file => {
        html += generateFileRow(file);
    });

    html += '</tbody></table></div>';
    return html;
}

        function escapeHtml(text) {
            if (typeof text !== 'string') return text;
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, function(m) { return map[m]; });
        }

        function generateFileRow(file) {
            let finalStatusHtml = '';
            if (userRole === 'epoptis') {
                finalStatusHtml = `
                    <div class="form-check form-switch">
                        <input class="form-check-input is-final-toggle" 
                               type="checkbox" 
                               id="final_toggle_${file.id}"
                               data-upload-id="${file.id}"
                               data-current-user="${file.upload_from}"
                               ${file.is_final == 1 ? 'checked' : ''}>
                        <label class="form-check-label" for="final_toggle_${file.id}">
                            <span class="final-status-text">
                                ${file.is_final == 1 ? 'Τελικό' : 'Προσχέδιο'}
                            </span>
                        </label>
                    </div>
                `;
            } else {
                finalStatusHtml = file.is_final == 1 ? 
                    '<i class="fas fa-check-circle text-success"></i> Ναι' : 
                    '<i class="fas fa-times-circle text-danger"></i> Όχι';
            }

            let approvalStatusHtml = '';
            if (file.is_approved === 'accepted') {
                approvalStatusHtml = '<i class="fas fa-check-circle text-success"></i> Εγκρίθηκε';
            } else if (file.is_approved === 'rejected') {
                approvalStatusHtml = '<i class="fas fa-times-circle text-danger"></i> Απορρίφθηκε';
            } else if (file.is_approved === 'pending') {
                approvalStatusHtml = '<i class="fas fa-hourglass-half text-warning"></i> Εκκρεμεί';
            } else {
                approvalStatusHtml = '<i class="fas fa-question-circle text-muted"></i> Άγνωστο';
            }

            let approvalTimestamp = '';
            if (file.is_approved_timestamp) {
                const date = new Date(file.is_approved_timestamp);
                approvalTimestamp = `<small>${date.toLocaleDateString('el-GR')} ${date.toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit'})}</small>`;
            } else {
                approvalTimestamp = '<small>Εκκρεμεί</small>';
            }

            let emailStatusHtml = '';
            if (file.is_mail_send == 1) {
                const mailDate = new Date(file.is_mail_send_timestamp);
                emailStatusHtml = `<i class="fas fa-check-circle text-success" title="Email στάλθηκε στις ${mailDate.toLocaleDateString('el-GR')} ${mailDate.toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit'})}"></i><br><small>Στάλθηκε</small>`;
            } else {
                emailStatusHtml = '<i class="fas fa-times-circle text-muted"></i><br><small>Δεν στάλθηκε</small>';
            }

            let actionsHtml = generateActionsButtons(file);

            const uploadDate = new Date(file.uploaded_at);
            const uploadTimestamp = `<small>${uploadDate.toLocaleDateString('el-GR')} ${uploadDate.toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit'})}</small>`;

            return `
                <tr data-file-id="${file.id}">
                    <td>
                        <span class="d-inline-block text-truncate" style="max-width: 200px; font-size: 0.85rem;"
                              title="${escapeHtml(file.filename)}">
                            ${escapeHtml(file.filename)}
                        </span>
                    </td>
                    <td>${escapeHtml(file.pedio_ax) || '-'}</td>
                    <td>${finalStatusHtml}</td>
                    <td>${approvalStatusHtml}</td>
                    <td>${approvalTimestamp}</td>
                    <td>${escapeHtml(file.ekpaideytikos)}</td>
                    <td>${escapeHtml(file.sxoleio)}</td>
                    <td>${escapeHtml(file.eidikotita)}</td>
                    <td>${uploadTimestamp}</td>
                    <td>
                        <a href="download.php?id=${file.id}" target="_blank" class="btn btn-sm btn-info">
                            <i class="fas fa-download"></i> <span class="d-none d-md-inline">Download</span>
                        </a>
                    </td>
                    <td>${emailStatusHtml}</td>
                    <td class="action-buttons">${actionsHtml}</td>
                </tr>
            `;
        }

        function generateActionsButtons(file) {
            if (userRole === 'user') {
                if (!file.is_final) {
                    return `
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary editBtn"
                                data-id="${file.id}"
                                data-dieythinsi="${file.dieythinsi_ekp_key}"
                                data-upload_from="${escapeHtml(file.upload_from)}"
                                data-pedio_ax="${escapeHtml(file.pedio_ax)}"
                                data-is_final="${file.is_final}"
                                data-ekpaideytikos="${escapeHtml(file.ekpaideytikos)}"
                                data-sxoleio="${escapeHtml(file.sxoleio)}"
                                data-eidikotita="${escapeHtml(file.eidikotita)}"
                                data-bs-toggle="modal" data-bs-target="#editModal" title="Επεξεργασία">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger deleteBtn"
                                data-id="${file.id}"
                                data-filename="${file.filename}"
                                data-bs-toggle="modal" data-bs-target="#deleteModal" title="Διαγραφή">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                } else {
                    return file.is_approved_comments ? `
                        <button class="btn btn-sm btn-secondary viewCommentsBtn"
                            data-comments="${escapeHtml(file.is_approved_comments)}"
                            data-bs-toggle="modal" data-bs-target="#viewCommentsModal" title="Προβολή σχολίων">
                            <i class="fas fa-comment"></i> Σχόλια
                        </button>
                    ` : '-';
                }
            } else if (userRole === 'epoptis') {
                
                if (file.username === currentUsername && !file.is_final) {
                    return `
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary editBtn"
                                data-id="${file.id}"
                                data-dieythinsi="${file.dieythinsi_ekp_key}"
                                data-upload_from="${escapeHtml(file.upload_from)}"
                                data-pedio_ax="${escapeHtml(file.pedio_ax)}"
                                data-is_final="${file.is_final}"
                                data-ekpaideytikos="${escapeHtml(file.ekpaideytikos)}"
                                data-sxoleio="${escapeHtml(file.sxoleio)}"
                                data-eidikotita="${escapeHtml(file.eidikotita)}"
                                data-bs-toggle="modal" data-bs-target="#editModal" title="Επεξεργασία">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger deleteBtn"
                                data-id="${file.id}"
                                data-filename="${file.filename}"
                                data-bs-toggle="modal" data-bs-target="#deleteModal" title="Διαγραφή">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                } else if (file.is_final) {
                    
                    return `
                        <div class="btn-group" role="group">
                            <form method="post" action="approve_upload_ajax.php" style="display:inline-block;" class="approvalForm">
                                <input type="hidden" name="upload_id" value="${file.id}" />
                                <input type="hidden" name="action" value="approve" />
                                <button type="submit" class="btn btn-sm btn-success" title="Έγκριση">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <form method="post" action="approve_upload_ajax.php" style="display:inline-block;" class="approvalForm">
                                <input type="hidden" name="upload_id" value="${file.id}" />
                                <input type="hidden" name="action" value="reject" />
                                <button type="submit" class="btn btn-sm btn-danger" title="Απόρριψη">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                            <form method="post" action="approve_upload_ajax.php" style="display:inline-block;" class="approvalForm">
                                <input type="hidden" name="upload_id" value="${file.id}" />
                                <input type="hidden" name="action" value="pending" />
                                <button type="submit" class="btn btn-sm btn-warning" title="Εκκρεμεί">
                                    <i class="fas fa-hourglass-half"></i>
                                </button>
                            </form>
                            <button class="btn btn-sm btn-info sendEmailBtn"
                                data-id="${file.id}"
                                data-filename="${escapeHtml(file.filename)}"
                                data-comments="${escapeHtml(file.is_approved_comments)}"
                                data-mail-sent="${file.is_mail_send}"
                                data-user-name="${escapeHtml(file.name_surname)}"
                                data-username="${escapeHtml(file.username)}"                              
                                title="Αποστολή Email">
                                <i class="fas fa-envelope"></i>
                                ${file.is_mail_send ? '<i class="fas fa-check-circle text-success" style="font-size: 0.7em;"></i>' : ''}
                            </button>
                            ${file.is_approved_comments ? `
                                <button class="btn btn-sm btn-secondary addCommentsBtn"
                                    data-id="${file.id}"
                                    data-filename="${escapeHtml(file.filename)}"
                                    data-comments="${escapeHtml(file.is_approved_comments)}"
                                    data-bs-toggle="modal" data-bs-target="#addCommentsModal" title="Επεξεργασία σχολίων">
                                    <i class="fas fa-edit"></i>
                                </button>
                            ` : `
                                <button class="btn btn-info addCommentsBtn"
                                    data-id="${file.id}"
                                    data-filename="${escapeHtml(file.filename)}"
                                    data-comments=""
                                    data-bs-toggle="modal" data-bs-target="#addCommentsModal" title="Προσθήκη σχολίων">
                                    <i class="fas fa-comment-medical"></i>
                                </button>
                            `}
                        </div>
                    `;
                } else {
                    
                    return file.is_approved_comments ? `
                        <button class="btn btn-sm btn-secondary viewCommentsBtn"
                            data-comments="${escapeHtml(file.is_approved_comments)}"
                            data-bs-toggle="modal" data-bs-target="#viewCommentsModal" title="Προβολή σχολίων">
                            <i class="fas fa-comment"></i> Σχόλια
                        </button>
                        <button class="btn btn-sm btn-secondary addCommentsBtn"
                            data-id="${file.id}"
                            data-filename="${escapeHtml(file.filename)}"
                            data-comments="${escapeHtml(file.is_approved_comments)}"
                            data-bs-toggle="modal" data-bs-target="#addCommentsModal" title="Επεξεργασία σχολίων">
                            <i class="fas fa-edit"></i>
                        </button>
                    ` : `
                        <button class="btn btn-info addCommentsBtn"
                            data-id="${file.id}"
                            data-filename="${escapeHtml(file.filename)}"
                            data-comments=""
                            data-bs-toggle="modal" data-bs-target="#addCommentsModal" title="Προσθήκη Σχολίων">
                            <i class="fas fa-comment-medical"></i> Προσθήκη Σχολίων
                        </button>
                    `;
                }
            } else {
                
                if (!file.is_final) {
                    return `
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-primary editBtn"
                                data-id="${file.id}"
                                data-dieythinsi="${escapeHtml(file.dieythinsi_ekp_key)}"
                                data-upload_from="${escapeHtml(file.upload_from)}"
                                data-pedio_ax="${escapeHtml(file.pedio_ax)}"
                                data-is_final="${file.is_final}"
                                data-ekpaideytikos="${escapeHtml(file.ekpaideytikos)}"
                                data-sxoleio="${escapeHtml(file.sxoleio)}"
                                data-eidikotita="${escapeHtml(file.eidikotita)}"
                                data-bs-toggle="modal" data-bs-target="#editModal" title="Επεξεργασία">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger deleteBtn"
                                data-id="${file.id}"
                                data-filename="${file.filename}"
                                data-bs-toggle="modal" data-bs-target="#deleteModal" title="Διαγραφή">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                } else {
                    return file.is_approved_comments ? `
                        <button class="btn btn-sm btn-secondary viewCommentsBtn"
                            data-comments="${escapeHtml(file.is_approved_comments)}"
                            data-bs-toggle="modal" data-bs-target="#viewCommentsModal" title="Προβολή σχολίων">
                            <i class="fas fa-comment"></i> Σχόλια
                        </button>
                    ` : '-';
                }
            }
        }

function updateApprovalStatus(uploadId, action, button) {
    const originalHtml = button.html();
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.ajax({
        url: 'approve_upload_ajax.php',
        type: 'POST',
        data: {
            upload_id: uploadId,
            action: action
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                
                showFlashMessage(response.message, 'success');

                updateFileRowAfterApproval(uploadId, action, button, response.data);

                updateUserSummaryStats(button);
                
            } else {
                showFlashMessage(response.message, 'danger');
                button.prop('disabled', false).html(originalHtml);
            }
        },
        error: function () {
            showFlashMessage('Σφάλμα κατά την επεξεργασία. Παρακαλώ δοκιμάστε ξανά.', 'danger');
            button.prop('disabled', false).html(originalHtml);
        }
    });
}

function updateFileRowAfterApproval(uploadId, action, button, responseData) {
    const fileRow = $(`tr[data-file-id="${uploadId}"]`);
    if (!fileRow.length) return;

    const approvalCell = fileRow.find('td').eq(3); 
    const timestampCell = fileRow.find('td').eq(4); 

    let statusHtml = '';
    if (action === 'approve') {
        statusHtml = '<i class="fas fa-check-circle text-success"></i> Εγκρίθηκε';
    } else if (action === 'reject') {
        statusHtml = '<i class="fas fa-times-circle text-danger"></i> Απορρίφθηκε';
    } else if (action === 'pending') {
        statusHtml = '<i class="fas fa-hourglass-half text-warning"></i> Εκκρεμεί';
    }
    
    approvalCell.html(statusHtml);

    let timestampHtml = '';
    if (action === 'pending') {
        
        timestampHtml = '<small>Εκκρεμεί</small>';
    } else {
        
        if (responseData && responseData.moved_report && responseData.moved_report.is_approved_timestamp) {
            const serverDate = new Date(responseData.moved_report.is_approved_timestamp);
            timestampHtml = `<small>${serverDate.toLocaleDateString('el-GR')} ${serverDate.toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit'})}</small>`;
        } else {
            
            const now = new Date();
            timestampHtml = `<small>${now.toLocaleDateString('el-GR')} ${now.toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit'})}</small>`;
        }
    }
    
    timestampCell.html(timestampHtml);

    const actionsCell = fileRow.find('.action-buttons');
    actionsCell.find('button').prop('disabled', false);

    actionsCell.find('button').each(function() {
        const btn = $(this);
        if (btn.find('.fa-spinner').length) {
            
            if (btn.closest('form').find('input[name="action"]').val() === 'approve') {
                btn.html('<i class="fas fa-check"></i>');
            } else if (btn.closest('form').find('input[name="action"]').val() === 'reject') {
                btn.html('<i class="fas fa-times"></i>');
            } else if (btn.closest('form').find('input[name="action"]').val() === 'pending') {
                btn.html('<i class="fas fa-hourglass-half"></i>');
            }
        }
    });
}

function updateUserSummaryStats(button) {

    const childTable = button.closest('.child-table');
    const userSummary = childTable.find('.user-summary h5');

    let username = userSummary.text().trim().replace(/^.*?\s/, ''); 

    if (!username) {
        const parentRow = $('#usersTable tbody tr.shown');
        if (parentRow.length) {
            username = parentRow.find('td').eq(1).text().trim();
        }
    }
    
    console.log('Updating stats for username:', username); 
    
    if (!username) {
        console.error('Could not determine username for statistics update');
        return;
    }

    $.ajax({
        url: 'get_user_stats_ajax.php',
        type: 'POST',
        data: { username: username },
        dataType: 'json',
        success: function(response) {
            console.log('Stats response:', response); 
            
            if (response.success) {
                
                const mainTableRows = $('#usersTable tbody tr:not(.child)');
                mainTableRows.each(function() {
                    const row = $(this);
                    const rowUsername = row.find('td').eq(1).text().trim();
                    
                    console.log('Comparing:', rowUsername, 'with', username); 
                    
                    if (rowUsername === username) {
                        console.log('Found matching row, updating badges'); 

                        row.find('td').eq(4).find('.badge').text(response.stats.total_files);
                        row.find('td').eq(5).find('.badge').text(response.stats.final_files);
                        row.find('td').eq(6).find('.badge').text(response.stats.approved_files);
                        row.find('td').eq(7).find('.badge').text(response.stats.rejected_files);
                        row.find('td').eq(8).find('.badge').text(response.stats.pending_files);
                        
                        return false; 
                    }
                });
            } else {
                console.error('Stats update failed:', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX error updating statistics:', error);
        }
    });
}		

		function updateComments(uploadId, comments) {
            return $.ajax({
                url: 'update_comments_ajax.php',
                type: 'POST',
                data: {
                    upload_id: uploadId,
                    comments: comments
                },
                dataType: 'json'
            });
        }

        function updateUpload(formData) {
            return $.ajax({
                url: 'edit_upload_ajax.php',
                type: 'POST',
                data: formData,
                dataType: 'json'
            });
        }

        function deleteUpload(uploadId) {
            return $.ajax({
                url: 'delete_upload_ajax.php',
                type: 'POST',
                data: {
                    delete_id: uploadId
                },
                dataType: 'json'
            });
        }

        function showLoadingOverlay() {
            const overlay = `
                <div id="loadingOverlay" class="loading-overlay">
                    <div class="loading-content">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-3 text-primary fw-bold">Ενημέρωση κατάστασης...</div>
                    </div>
                </div>
            `;
            $('body').append(overlay);
        }

        function hideLoadingOverlay() {
            $('#loadingOverlay').remove();
        }

        $(document).ready(function () {

usersTable = $('#usersTable').DataTable({
    responsive: false, 
    orderCellsTop: true,
    fixedHeader: true,
    language: {
        url: 'https:
    },
    order: [[2, 'asc']], 
    pageLength: 25, 
    columnDefs: [
        {
            "targets": 0,
            "orderable": false,
            "className": "details-control text-center"
        }
    ]
});

            $('#usersTable tbody').on('click', 'td.details-control', function () {
                const tr = $(this).closest('tr');
                const row = usersTable.row(tr);
                const username = tr.find('td').eq(1).text(); 

                if (row.child.isShown()) {
                    
                    row.child.hide();
                    tr.removeClass('shown');
                    $(this).find('i').removeClass('fa-minus-circle').addClass('fa-plus-circle');
                } else {
                    
                    row.child(format(username)).show();
                    tr.addClass('shown');
                    $(this).find('i').removeClass('fa-plus-circle').addClass('fa-minus-circle');

                    loadUserFiles(username);
                }
            });

            const flashMessage = sessionStorage.getItem('flashMessage');
            const flashType = sessionStorage.getItem('flashType');
            
            if (flashMessage && flashType) {
                showFlashMessage(flashMessage, flashType);
                sessionStorage.removeItem('flashMessage');
                sessionStorage.removeItem('flashType');
            }

            $(document).on('change', '.is-final-toggle', function() {
                const checkbox = $(this);
                const uploadId = checkbox.data('upload-id');
                const currentUser = checkbox.data('current-user');
                const newStatus = checkbox.is(':checked') ? 1 : 0;
                
                const action = newStatus ? 'Θα κάνετε τελική' : 'επαναφέρετε σε προσχέδιο';
                const confirmMessage = `Είστε σίγουροι ότι θέλετε να ${action} την ανάρτηση του/της ${currentUser}?\n\n` +
                                      (newStatus ? 'Μετά την θέση σε τελική έκθεση, ο σύμβουλος δεν θα μπορεί να την επεξεργαστεί.' : 
                                       'Μετά την επαναφορά, ο σύμβουλος θα μπορεί να επεξεργαστεί την ανάρτησή του.');
                
                if (!confirm(confirmMessage)) {
                    checkbox.prop('checked', !newStatus);
                    return;
                }
                
                showLoadingOverlay();
                
                $.ajax({
                    url: 'toggle_final_ajax.php',
                    type: 'POST',
                    data: {
                        upload_id: uploadId,
                        is_final: newStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            sessionStorage.setItem('flashMessage', response.message);
                            sessionStorage.setItem('flashType', 'success');
                            location.reload();
                        } else {
                            hideLoadingOverlay();
                            showFlashMessage(response.message, 'danger');
                            checkbox.prop('checked', !newStatus);
                        }
                    },
                    error: function() {
                        hideLoadingOverlay();
                        showFlashMessage('Σφάλμα κατά την ενημέρωση κατάστασης.', 'danger');
                        checkbox.prop('checked', !newStatus);
                    }
                });
            });

            $(document).on('submit', 'form.approvalForm', function (e) {
                e.preventDefault();
                const form = $(this);
                const uploadId = form.find('input[name="upload_id"]').val();
                const action = form.find('input[name="action"]').val();
                const button = form.find('button');
                updateApprovalStatus(uploadId, action, button);
            });

            $(document).on('submit', '#editForm', function (e) {
                e.preventDefault();
                const formData = $(this).serialize();
                const submitBtn = $(this).find('button[type="submit"]');
                const originalHtml = submitBtn.html();

                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Αποθήκευση...');

                updateUpload(formData)
                    .done(function (response) {
                        if (response.success) {
                            showFlashMessage(response.message, 'success');
                           
							  sessionStorage.setItem('flashMessage', response.message);
                              sessionStorage.setItem('flashType', 'success');

							$('#editModal').modal('hide');
                            location.reload();
                        } else {
                            showFlashMessage(response.message, 'danger');
                        }
                    })
                    .fail(function () {
                        showFlashMessage('Σφάλμα κατά την ενημέρωση.', 'danger');
                    })
                    .always(function () {
                        submitBtn.prop('disabled', false).html(originalHtml);
                    });
            });

            $(document).on('submit', '#deleteForm', function (e) {
                e.preventDefault();
                const uploadId = $(this).find('input[name="delete_id"]').val();
                const submitBtn = $(this).find('button[type="submit"]');
                const originalHtml = submitBtn.html();

                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Διαγραφή...');

                deleteUpload(uploadId)
                    .done(function (response) {
                        if (response.success) {
                            showFlashMessage(response.message, 'success');
                            $('#deleteModal').modal('hide');
                            
                            $(`tr[data-file-id="${uploadId}"]`).remove();
                        } else {
                            showFlashMessage(response.message, 'danger');
                        }
                    })
                    .fail(function () {
                        showFlashMessage('Σφάλμα κατά την διαγραφή.', 'danger');
                    })
                    .always(function () {
                        submitBtn.prop('disabled', false).html(originalHtml);
                    });
            });

$(document).on('submit', '#commentsForm', function (e) {
    e.preventDefault();
    const form = $(this);
    const uploadId = form.find('input[name="upload_id"]').val();
    const comments = form.find('textarea[name="comments"]').val();
    const submitBtn = form.find('button[type="submit"]');
    const originalHtml = submitBtn.html();

    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Αποθήκευση...');

    updateComments(uploadId, comments)
        .done(function (response) {
            if (response.success) {
                showFlashMessage(response.message, 'success');
                $('#addCommentsModal').modal('hide');

                updateCommentsStatus(uploadId, comments);

                updateCommentsButton(uploadId, comments);
            } else {
                showFlashMessage(response.message, 'danger');
            }
        })
        .fail(function () {
            showFlashMessage('Σφάλμα κατά την ενημέρωση σχολίων.', 'danger');
        })
        .always(function () {
            submitBtn.prop('disabled', false).html(originalHtml);
        });
});

function updateCommentsStatus(uploadId, comments) {
    const row = $(`tr[data-file-id="${uploadId}"]`);
    
}

function updateCommentsButton(uploadId, comments) {
    const row = $(`tr[data-file-id="${uploadId}"]`);
    const actionsCell = row.find('.action-buttons');
    const existingButton = actionsCell.find('.addCommentsBtn, .viewCommentsBtn');
    
    if (comments) {
        
        existingButton.replaceWith(`
            <button class="btn btn-sm btn-secondary addCommentsBtn"
                data-id="${uploadId}"
                data-filename="${existingButton.data('filename')}"
                data-comments="${escapeHtml(comments)}"
                data-bs-toggle="modal" 
                data-bs-target="#addCommentsModal" 
                title="Επεξεργασία σχολίων">
                <i class="fas fa-edit"></i> 
            </button>
        `);
    }
}

            $(document).on('click', '.viewCommentsBtn', function () {
                const comments = $(this).data('comments');
                $('#commentsField').val(comments);
            });

            $(document).on('click', '.addCommentsBtn', function () {
                const uploadId = $(this).data('id');
                const filename = $(this).data('filename');
                const existingComments = $(this).data('comments');

                $('#commentUploadId').val(uploadId);
                $('#commentFilename').text(filename);
                $('#commentsTextarea').val(existingComments || '');
            });

            $(document).on('click', '.editBtn', function () {
                const button = $(this);
                $('#modal-id').val(button.data('id'));
                $('#modal-name').val(button.data('dieythinsi'));
                $('#modal-upload_from').val(button.data('upload_from'));
                $('#modal-pedio_ax').val(button.data('pedio_ax'));
                $('#modal-ekpaideytikos').val(button.data('ekpaideytikos'));
                $('#modal-sxoleio').val(button.data('sxoleio'));
                $('#modal-eidikotita').val(button.data('eidikotita'));

                const isFinalValue = button.data('is_final');
                const checkbox = $('#modal-is_final_checkbox');
                const hiddenField = $('#modal-is_final');
                const nameField = $('#modal-ekpaideytikos');
                const sxoleioField = $('#modal-sxoleio');
                const eidikotitaField = $('#modal-eidikotita');
                const pedioField = $('#modal-pedio_ax');

                if (isFinalValue == 1) {
                    checkbox.prop('checked', true).prop('disabled', true);
                    hiddenField.val(1);
                    nameField.addClass('readonly-field').prop('readonly', true);
                    sxoleioField.addClass('readonly-field').prop('readonly', true);
                    eidikotitaField.addClass('readonly-field').prop('readonly', true);
                    pedioField.addClass('readonly-field').prop('disabled', true);
                } else {
                    checkbox.prop('checked', false).prop('disabled', false);
                    hiddenField.val(0);
                    nameField.removeClass('readonly-field').prop('readonly', false);
                    sxoleioField.removeClass('readonly-field').prop('readonly', false);
                    eidikotitaField.removeClass('readonly-field').prop('readonly', false);
                    pedioField.removeClass('readonly-field').prop('disabled', false);
                }

                checkbox.off('change').on('change', function () {
                    hiddenField.val($(this).is(':checked') ? 1 : 0);
                });
            });

            $(document).on('click', '.deleteBtn', function () {
                const id = $(this).data('id');
                const filename = $(this).data('filename');
                $('#deleteId').val(id);
                $('#deleteFilename').text(filename);
            });

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
    
    let confirmMessage = `Θέλετε να στείλετε email με τα σχόλια για το αρχείο "${filename}"?\n\n`;
    confirmMessage += `Το email θα σταλεί στις διευθύνσεις:\n`;
    confirmMessage += `• ${userEmail} (${userName})\n`;
    confirmMessage += `• ${epoptisEmail}`;
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
                button.html('<i class="fas fa-envelope"></i> <i class="fas fa-check-circle text-success" style="font-size: 0.7em;"></i>');
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
    const row = $(`tr[data-file-id="${uploadId}"]`);
    const emailCell = row.find('td').eq(10); 
    
    const now = new Date();
    const timestamp = now.toLocaleDateString('el-GR') + ' ' + 
                     now.toLocaleTimeString('el-GR', {hour: '2-digit', minute: '2-digit'});
    
    if (success) {
        emailCell.html(`
            <i class="fas fa-check-circle text-success" title="Email στάλθηκε στις ${timestamp}"></i>
            <br><small>Στάλθηκε</small>
        `);
    }
}
            
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
                    },
                    error: function() {
                        console.log('Error loading ready messages');
                    }
                });
            }

            $('#readyMessages').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                if (selectedOption.val()) {
                    const messageContent = selectedOption.data('content');
                    $('#readyMessages').data('selected-message', messageContent);
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

            function escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }
        });

		$(document).ready(function() {
    
    const flashMessage = sessionStorage.getItem('flashMessage');
    const flashType = sessionStorage.getItem('flashType');
    
    if (flashMessage && flashType) {
        showFlashMessage(flashMessage, flashType);
        sessionStorage.removeItem('flashMessage');
        sessionStorage.removeItem('flashType');
    }
});
    </script>
</body>
</html>