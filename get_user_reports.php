<?php
// get_user_reports.php - Λήψη λεπτομερειών αναφορών χρήστη μέσω AJAX

header('Content-Type: application/json');

// Εκκίνηση συνεδρίας αν δεν έχει ήδη ξεκινήσει
if (session_status() === PHP_SESSION_NONE) {
    include 'session.php';
}
include 'db_conn.php';

// Αρχικοποίηση πίνακα απόκρισης
$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    // Έλεγχος αν ο χρήστης είναι συνδεδεμένος
    $username = $_SESSION['username'] ?? null;
    if (!$username) {
        $response['message'] = 'Μη εξουσιοδοτημένη πρόσβαση.';
        echo json_encode($response);
        exit;
    }

    // Έλεγχος αν ο χρήστης είναι επόπτης ή διαχειριστής
    $stmtUser = $pdo->prepare("SELECT role, dieythinsi_ekp_key FROM users WHERE username = ?");
    $stmtUser->execute([$username]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $response['message'] = 'Χρήστης δεν βρέθηκε.';
        echo json_encode($response);
        exit;
    }

    $userRole = trim($user['role']);
    $dieythinsi_ekp = $user['dieythinsi_ekp_key'];

    // Μόνο επόπτης και διαχειριστής μπορούν να έχουν πρόσβαση
    if (!in_array($userRole, ['epoptis', 'admin'])) {
        $response['message'] = 'Δεν έχετε δικαίωμα πρόσβασης.';
        echo json_encode($response);
        exit;
    }

    // Λήψη και επικύρωση παραμέτρων
    $requestedUsername = $_GET['username'] ?? '';
    $status = $_GET['status'] ?? '';

    // Επικύρωση παραμέτρων
    if (empty($requestedUsername)) {
        $response['message'] = 'Το όνομα χρήστη είναι υποχρεωτικό.';
        echo json_encode($response);
        exit;
    }

    if (!in_array($status, ['pending', 'accepted', 'rejected'])) {
        $response['message'] = 'Μη έγκυρη κατάσταση.';
        echo json_encode($response);
        exit;
    }

    // Κατασκευή ερωτήματος για λήψη αναλυτικών αναφορών
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
                       users.name_surname,
                       dieythinseis.name as dieythinsi_name,
                       dieythinseis.type as dieythinsi_type
                    FROM uploads
                    JOIN users ON uploads.username = users.username
                    JOIN dieythinseis ON uploads.dieythinsi_ekp_key = dieythinseis.id
                    WHERE uploads.is_final = 1
                      AND uploads.is_approved = ?
                      AND uploads.username = ?";

    $params = [$status, $requestedUsername];

    // Προσθήκη περιορισμού για χρήστες επόπτες
    if ($userRole === 'epoptis') {
        $detailQuery .= " AND uploads.dieythinsi_ekp_key = ?";
        $params[] = $dieythinsi_ekp;
    }

    $detailQuery .= " ORDER BY uploads.uploaded_at DESC";

    $stmt = $pdo->prepare($detailQuery);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Επαλήθευση ότι ο ζητούμενος χρήστης υπάρχει και έχει αναφορές
    if (empty($reports)) {
        // Έλεγχος αν ο χρήστης υπάρχει καθόλου
        $userCheckQuery = "SELECT COUNT(*) as count FROM users WHERE username = ?";
        $userCheckStmt = $pdo->prepare($userCheckQuery);
        $userCheckStmt->execute([$requestedUsername]);
        $userExists = $userCheckStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

        if (!$userExists) {
            $response['message'] = 'Ο χρήστης δεν βρέθηκε.';
        } else {
            $response['message'] = "Δεν βρέθηκαν εκθέσεις με κατάσταση '{$status}' για τον χρήστη.";
        }

        echo json_encode($response);
        exit;
    }

    // Μορφοποίηση δεδομένων για την απόκριση
    $formattedReports = [];
    foreach ($reports as $report) {
        $formattedReports[] = [
            'id' => $report['id'],
            'username' => $report['username'],
            'filename' => $report['filename'],
            'pedio_ax' => $report['pedio_ax'],
            'ekpaideytikos' => $report['ekpaideytikos'],
            'sxoleio' => $report['sxoleio'],
            'eidikotita' => $report['eidikotita'],
            'uploaded_at' => $report['uploaded_at'],
            'is_approved_comments' => $report['is_approved_comments'],
            'name_surname' => $report['name_surname'],
            'dieythinsi_name' => $report['dieythinsi_name'],
            'dieythinsi_type' => $report['dieythinsi_type']
        ];
    }

    // Ορισμός επιτυχούς απόκρισης
    $response['success'] = true;
    $response['message'] = 'Τα δεδομένα φορτώθηκαν επιτυχώς.';
    $response['data'] = $formattedReports;

} catch (PDOException $e) {
    // Καταγραφή σφάλματος βάσης δεδομένων
    error_log("Σφάλμα βάσης δεδομένων στο get_user_reports.php: " . $e->getMessage());
    $response['message'] = 'Σφάλμα βάσης δεδομένων. Παρακαλώ δοκιμάστε ξανά.';
} catch (Exception $e) {
    // Καταγραφή γενικού σφάλματος
    error_log("Γενικό σφάλμα στο get_user_reports.php: " . $e->getMessage());
    $response['message'] = 'Παρουσιάστηκε σφάλμα. Παρακαλώ δοκιμάστε ξανά.';
}

// Επιστροφή απόκρισης JSON
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>