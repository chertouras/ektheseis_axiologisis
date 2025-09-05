<?php
// ready_messages.php - Διαχείριση έτοιμων μηνυμάτων

if (session_status() === PHP_SESSION_NONE) {
    include 'session.php';
}
include 'db_conn.php';

// Έλεγχος αν ο χρήστης είναι επόπτης
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'epoptis') {
    header("Location: index.html");
    exit();
}

$username = $_SESSION['username'] ?? null;
if (!$username) {
    header("Location: index.html");
    exit();
}

// Χειρισμός μηνυμάτων ειδοποίησης
if (isset($_SESSION['message'])) {
    $flashMessage = $_SESSION['message'];
    unset($_SESSION['message']);
} else {
    $flashMessage = null;
}

// Λήψη έτοιμων μηνυμάτων
$stmt = $pdo->prepare("
    SELECT rm.id, rm.message_title, rm.message_content, rm.created_by, rm.created_at, u.name_surname
    FROM ready_messages rm
    LEFT JOIN users u ON rm.created_by = u.username
    ORDER BY rm.created_at DESC
");
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Διαχείριση Έτοιμων Μηνυμάτων</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>
        .readonly-field {
            background-color: #f8f9fa !important;
            color: #6c757d !important;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding: 0.5rem;
            }
            h2 {
                font-size: 1.5rem;
                margin-bottom: 1rem;
            }
            .btn-group .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
            .table-responsive {
                font-size: 0.875rem;
            }
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
        }

        .message-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
                <h2 class="mb-3">Διαχείριση Έτοιμων Μηνυμάτων</h2>

                <div class="mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMessageModal">
                        <i class="fas fa-plus"></i> Προσθήκη Μηνύματος
                    </button>
                </div>

                <div class="table-responsive">
                    <table id="messagesTable" class="table table-striped table-hover" style="width:100%">
                        <thead class="table-dark">
                            <tr>
                                <th>Τίτλος</th>
                                <th>Περιεχόμενο</th>
                                <th>Δημιουργήθηκε από</th>
                                <th>Ημερομηνία</th>
                                <th>Ενέργειες</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $message): ?>
                            <tr>
                                <td><?= htmlspecialchars($message['message_title']) ?></td>
                                <td>
                                    <div class="message-preview" title="<?= htmlspecialchars($message['message_content']) ?>">
                                        <?= htmlspecialchars($message['message_content']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($message['name_surname'] ?? $message['created_by']) ?></td>
                                <td><small><?= date('d/m/Y H:i', strtotime($message['created_at'])) ?></small></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-info viewBtn"
                                            data-id="<?= $message['id'] ?>"
                                            data-title="<?= htmlspecialchars($message['message_title']) ?>"
                                            data-content="<?= htmlspecialchars($message['message_content']) ?>"
                                            data-bs-toggle="modal" data-bs-target="#viewMessageModal">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary editBtn"
                                            data-id="<?= $message['id'] ?>"
                                            data-title="<?= htmlspecialchars($message['message_title']) ?>"
                                            data-content="<?= htmlspecialchars($message['message_content']) ?>"
                                            data-bs-toggle="modal" data-bs-target="#editMessageModal">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger deleteBtn"
                                            data-id="<?= $message['id'] ?>"
                                            data-title="<?= htmlspecialchars($message['message_title']) ?>"
                                            data-bs-toggle="modal" data-bs-target="#deleteMessageModal">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addMessageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="ready_messages_ajax.php" id="addMessageForm">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-plus"></i> Προσθήκη Νέου Μηνύματος
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="add-title" class="form-label">Τίτλος Μηνύματος</label>
                            <input type="text" class="form-control" name="message_title" id="add-title" required
                                   maxlength="100" placeholder="π.χ. Θετική Αξιολόγηση">
                        </div>
                        <div class="mb-3">
                            <label for="add-content" class="form-label">Περιεχόμενο Μηνύματος</label>
                            <textarea class="form-control" name="message_content" id="add-content" rows="6" required
                                      placeholder="Εισάγετε το περιεχόμενο του έτοιμου μηνύματος..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
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

    <div class="modal fade" id="editMessageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="ready_messages_ajax.php" id="editMessageForm">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title">
                            <i class="fas fa-edit"></i> Επεξεργασία Μηνύματος
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="message_id" id="edit-id">
                        <div class="mb-3">
                            <label for="edit-title" class="form-label">Τίτλος Μηνύματος</label>
                            <input type="text" class="form-control" name="message_title" id="edit-title" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="edit-content" class="form-label">Περιεχόμενο Μηνύματος</label>
                            <textarea class="form-control" name="message_content" id="edit-content" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save"></i> Ενημέρωση
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Άκυρο
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="viewMessageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-eye"></i> Προεπισκόπηση Μηνύματος
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="mb-3">Τίτλος:</h6>
                    <div class="alert alert-light" id="view-title"></div>
                    <h6 class="mb-3">Περιεχόμενο:</h6>
                    <div class="alert alert-light" id="view-content" style="white-space: pre-wrap;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Κλείσιμο
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteMessageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="ready_messages_ajax.php" id="deleteMessageForm">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle"></i> Επιβεβαίωση Διαγραφής
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="message_id" id="delete-id">
                        Είστε σίγουροι ότι θέλετε να διαγράψετε το μήνυμα "<strong id="delete-title"></strong>";
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-warning"></i> Αυτή η ενέργεια δεν μπορεί να αναιρεθεί!
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Διαγραφή
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
            }, 4000);
        }

        $(document).ready(function() {
            // Αρχικοποίηση DataTable
            $('#messagesTable').DataTable({
                responsive: true,
                order: [[0, 'desc']],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/el.json'
                }
            });

            // Κουμπί προβολής
            $(document).on('click', '.viewBtn', function() {
                const title = $(this).data('title');
                const content = $(this).data('content');

                $('#view-title').text(title);
                $('#view-content').text(content);
            });

            // Κουμπί επεξεργασίας
            $(document).on('click', '.editBtn', function() {
                const id = $(this).data('id');
                const title = $(this).data('title');
                const content = $(this).data('content');

                $('#edit-id').val(id);
                $('#edit-title').val(title);
                $('#edit-content').val(content);
            });

            // Κουμπί διαγραφής
            $(document).on('click', '.deleteBtn', function() {
                const id = $(this).data('id');
                const title = $(this).data('title');

                $('#delete-id').val(id);
                $('#delete-title').text(title);
            });

            // Υποβολή φόρμας με AJAX
            $('#addMessageForm, #editMessageForm, #deleteMessageForm').on('submit', function(e) {
                e.preventDefault();

                const form = $(this);
                const submitBtn = form.find('button[type="submit"]');
                const originalHtml = submitBtn.html();

                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Επεξεργασία...');

                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showFlashMessage(response.message, 'success');
                            form.closest('.modal').modal('hide');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            showFlashMessage(response.message, 'danger');
                        }
                    },
                    error: function() {
                        showFlashMessage('Σφάλμα κατά την επεξεργασία.', 'danger');
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html(originalHtml);
                    }
                });
            });

            // Αυτόματη απόκρυψη μηνύματος ειδοποίησης
            setTimeout(() => {
                $('#flash-msg').alert('close');
            }, 4000);
        });
    </script>
</body>
</html>