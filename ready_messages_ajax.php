<?php
// ready_messages_ajax.php - Διαχείριση έτοιμων μηνυμάτων μέσω AJAX

if (session_status() === PHP_SESSION_NONE) {
    include 'session.php';
}
include 'db_conn.php';

header('Content-Type: application/json');

// Έλεγχος αν ο χρήστης είναι επόπτης
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'epoptis') {
    echo json_encode(['success' => false, 'message' => 'Δεν έχετε δικαίωμα πρόσβασης.']);
    exit();
}

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => 'Δεν υπάρχει συνεδρία χρήστη.']);
    exit();
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $title = trim($_POST['message_title'] ?? '');
            $content = trim($_POST['message_content'] ?? '');

            if (empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Όλα τα πεδία είναι υποχρεωτικά.']);
                exit();
            }

            if (strlen($title) > 100) {
                echo json_encode(['success' => false, 'message' => 'Ο τίτλος δεν μπορεί να υπερβαίνει τους 100 χαρακτήρες.']);
                exit();
            }

            $stmt = $pdo->prepare("INSERT INTO ready_messages (message_title, message_content, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$title, $content, $username]);

            echo json_encode(['success' => true, 'message' => 'Το μήνυμα προστέθηκε επιτυχώς.']);
            break;

        case 'edit':
            $id = intval($_POST['message_id'] ?? 0);
            $title = trim($_POST['message_title'] ?? '');
            $content = trim($_POST['message_content'] ?? '');

            if ($id <= 0 || empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Μη έγκυρα δεδομένα.']);
                exit();
            }

            if (strlen($title) > 100) {
                echo json_encode(['success' => false, 'message' => 'Ο τίτλος δεν μπορεί να υπερβαίνει τους 100 χαρακτήρες.']);
                exit();
            }

            // Έλεγχος αν υπάρχει το μήνυμα
            $checkStmt = $pdo->prepare("SELECT id FROM ready_messages WHERE id = ?");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Το μήνυμα δεν βρέθηκε.']);
                exit();
            }

            $stmt = $pdo->prepare("UPDATE ready_messages SET message_title = ?, message_content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$title, $content, $id]);

            echo json_encode(['success' => true, 'message' => 'Το μήνυμα ενημερώθηκε επιτυχώς.']);
            break;

        case 'delete':
            $id = intval($_POST['message_id'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Μη έγκυρο ID μηνύματος.']);
                exit();
            }

            // Έλεγχος αν υπάρχει το μήνυμα
            $checkStmt = $pdo->prepare("SELECT id FROM ready_messages WHERE id = ?");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Το μήνυμα δεν βρέθηκε.']);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM ready_messages WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Το μήνυμα διαγράφηκε επιτυχώς.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Μη έγκυρη ενέργεια.']);
            break;
    }

} catch (PDOException $e) {
    error_log("Σφάλμα βάσης δεδομένων στο ready_messages_ajax.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.']);
} catch (Exception $e) {
    error_log("Γενικό σφάλμα στο ready_messages_ajax.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Παρουσιάστηκε σφάλμα. Παρακαλώ δοκιμάστε ξανά.']);
}
?>