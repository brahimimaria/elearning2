<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db.php';

$is_php_folder = true;
$user_id = $_SESSION['user_id'] ?? null;

// Create submissions table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS evaluation_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    user_id INT NOT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    text_submission TEXT DEFAULT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$message = '';
$message_type = '';

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
if (!$course_id) {
    echo 'Course ID required (use ?course_id=)';
    exit();
}

// Check enrollment or teacher ownership
$enrolled = false;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'instructor') {
    // allow if teacher owns it
    $q = $conn->query("SELECT id FROM courses WHERE id=$course_id AND teacher_id=(SELECT id FROM teachers WHERE user_id=" . (int)$user_id . ")");
    if ($q && $q->num_rows > 0) $enrolled = true;
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    $q = $conn->query("SELECT id FROM course_enrollments WHERE course_id=$course_id AND user_id=" . (int)$user_id);
    if ($q && $q->num_rows > 0) $enrolled = true;
}

if (!$enrolled) {
    echo 'Accès refusé: vous devez être inscrit à ce cours.';
    exit();
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_evaluation'])) {
    $eval_id = (int)$_POST['evaluation_id'];
    $text = $conn->real_escape_string(trim($_POST['text_submission'] ?? ''));
    $file_path = null;

    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['size'] > 0) {
        $uploads_dir = '../uploads/evaluations/';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir,0755,true);
        $file = $_FILES['submission_file'];
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/','',basename($file['name']));
        $target = $uploads_dir . $filename;
        if (move_uploaded_file($file['tmp_name'],$target)) {
            $file_path = 'uploads/evaluations/' . $filename;
        } else {
            $message = 'Erreur upload fichier.';
            $message_type = 'error';
        }
    }

    $sql = "INSERT INTO evaluation_submissions (evaluation_id, user_id, file_path, text_submission) VALUES ($eval_id, " . (int)$user_id . ", '" . $conn->real_escape_string($file_path) . "', '" . $text . "')";
    if ($conn->query($sql)) {
        $message = 'Soumission enregistrée.';
        $message_type = 'success';
    } else {
        $message = 'Erreur: ' . $conn->error;
        $message_type = 'error';
    }
}

// Fetch evaluations for course
$evals = $conn->query("SELECT * FROM evaluations WHERE course_id=$course_id ORDER BY created_at DESC");
$eval_list = $evals->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Évaluations du cours</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main style="max-width:900px;margin:2rem auto;padding:0 1rem;">
    <h1>Évaluations</h1>
    <?php if ($message): ?>
        <div class="<?php echo $message_type==='success' ? 'success-message' : 'error-message'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (empty($eval_list)): ?>
        <p>Aucune évaluation pour ce cours.</p>
    <?php else: ?>
        <?php foreach ($eval_list as $ev): ?>
            <div style="background:#fff;padding:1rem;margin-bottom:1rem;border-radius:8px">
                <h3><?php echo htmlspecialchars($ev['title']); ?> <small style="color:#666">(<?php echo htmlspecialchars($ev['type']); ?>)</small></h3>
                <p><?php echo nl2br(htmlspecialchars($ev['description'])); ?></p>
                <p><strong>Date limite:</strong> <?php echo htmlspecialchars($ev['due_date']); ?></p>

                <h4>Soumettre</h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="evaluation_id" value="<?php echo $ev['id']; ?>">
                    <div>
                        <label>Texte / Réponses (pour quiz)</label>
                        <textarea name="text_submission" style="width:100%;min-height:120px"></textarea>
                    </div>
                    <div style="margin-top:.5rem">
                        <label>Fichier (devoir / examen)</label>
                        <input type="file" name="submission_file">
                    </div>
                    <div style="margin-top:.5rem">
                        <button name="submit_evaluation" class="btn btn-primary">Soumettre</button>
                    </div>
                </form>

            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>