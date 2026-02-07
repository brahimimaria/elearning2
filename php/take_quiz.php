<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db.php';

// Autoriser uniquement les étudiants inscrits au cours
$is_php_folder = true;
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
if ($quiz_id <= 0) {
    echo 'Quiz invalide.';
    exit();
}

// Charger le quiz et le cours
$quiz = $conn->query("SELECT e.*, c.titre AS course_title, c.id AS course_id 
                      FROM evaluations e 
                      JOIN courses c ON e.course_id = c.id 
                      WHERE e.id = $quiz_id AND e.type = 'quiz'")->fetch_assoc();
if (!$quiz) {
    echo 'Quiz introuvable.';
    exit();
}

// Vérifier inscription de l'étudiant au cours
$student = getStudentByUserId($user_id);
if (!$student) {
    echo 'Compte étudiant requis.';
    exit();
}
$enrolled = $conn->query("SELECT 1 FROM course_enrollments WHERE student_id=" . (int)$student['id'] . " AND course_id=" . (int)$quiz['course_id']);
if (!$enrolled || $enrolled->num_rows === 0) {
    echo 'Accès refusé: vous devez être inscrit à ce cours.';
    exit();
}

// Restreindre si affectations existent
$aff = $conn->query("SELECT COUNT(*) AS c FROM quiz_assignments WHERE evaluation_id=$quiz_id");
if ($aff && (int)$aff->fetch_assoc()['c'] > 0) {
    $allowed = $conn->query("SELECT 1 FROM quiz_assignments qa JOIN students s ON qa.student_id=s.id WHERE qa.evaluation_id=$quiz_id AND s.user_id=" . (int)$user_id);
    if (!$allowed || $allowed->num_rows === 0) {
        echo 'Quiz non assigné à votre profil.';
        exit();
    }
}

// Créer tables pour les tentatives et réponses si elles n'existent pas
$conn->query("CREATE TABLE IF NOT EXISTS quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    user_id INT NOT NULL,
    score INT NOT NULL,
    total INT NOT NULL,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_eval_user (evaluation_id, user_id),
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE
)");
$conn->query("CREATE TABLE IF NOT EXISTS quiz_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_id INT DEFAULT NULL,
    text_response TEXT DEFAULT NULL,
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (answer_id) REFERENCES quiz_answers(id) ON DELETE SET NULL
)");

$message = '';
$message_type = '';
$already_attempted = false;

// Empêcher nouvelle tentative si déjà fait
$prev = $conn->query("SELECT id, score, total, attempted_at FROM quiz_attempts WHERE evaluation_id=$quiz_id AND user_id=" . (int)$user_id);
if ($prev && $prev->num_rows > 0) {
    $already_attempted = true;
    $prev_attempt = $prev->fetch_assoc();
}

// Charger questions et réponses
$questions_result = $conn->query("SELECT * FROM quiz_questions WHERE evaluation_id = $quiz_id ORDER BY question_order");
$questions = $questions_result ? $questions_result->fetch_all(MYSQLI_ASSOC) : [];
foreach ($questions as &$q) {
    $answers_result = $conn->query("SELECT * FROM quiz_answers WHERE question_id = {$q['id']} ORDER BY answer_order");
    $q['answers'] = $answers_result ? $answers_result->fetch_all(MYSQLI_ASSOC) : [];
}
unset($q);

// Vérifier date limite
$expired = false;
if (!empty($quiz['due_date'])) {
    $expired = (strtotime(date('Y-m-d')) > strtotime($quiz['due_date']));
}

// Soumission du quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz']) && !$already_attempted && !$expired) {
    $total = count($questions);
    $correct = 0;
    $selected = [];
    $open_responses = [];
    foreach ($questions as $q) {
        $qid = (int)$q['id'];
        $qtype = $q['question_type'] ?? 'mcq';
        if ($qtype === 'open') {
            $txt = trim($_POST['open_' . $qid] ?? '');
            $open_responses[$qid] = $txt;
        } else {
            $selected_id = isset($_POST['answer_' . $qid]) ? (int)$_POST['answer_' . $qid] : 0;
            $selected[$qid] = $selected_id;
            if ($selected_id > 0) {
                $r = $conn->query("SELECT is_correct FROM quiz_answers WHERE id=$selected_id AND question_id=$qid");
                if ($r && $r->num_rows > 0) {
                    $is_correct = (int)$r->fetch_assoc()['is_correct'];
                    if ($is_correct === 1) $correct++;
                }
            }
        }
    }
    // Calcul du score (proportionnel à max_score si défini, sinon sur 100)
    $max_score = (int)($quiz['max_score'] ?? 100);
    if ($max_score <= 0) $max_score = 100;
    $score = $total > 0 ? (int)round(($correct / $total) * $max_score) : 0;

    // Enregistrer tentative
    if ($conn->query("INSERT INTO quiz_attempts (evaluation_id, user_id, score, total) VALUES ($quiz_id, " . (int)$user_id . ", $score, $total)")) {
        $attempt_id = $conn->insert_id;
        // Enregistrer réponses
        foreach ($selected as $qid => $aid) {
            $conn->query("INSERT INTO quiz_responses (attempt_id, question_id, answer_id) VALUES ($attempt_id, " . (int)$qid . ", " . ($aid > 0 ? (int)$aid : "NULL") . ")");
        }
        foreach ($open_responses as $qid => $txt) {
            $esc = $conn->real_escape_string($txt);
            $conn->query("INSERT INTO quiz_responses (attempt_id, question_id, text_response) VALUES ($attempt_id, " . (int)$qid . ", '$esc')");
        }
        $already_attempted = true;
        $prev_attempt = ['id' => $attempt_id, 'score' => $score, 'total' => $total, 'attempted_at' => date('Y-m-d H:i:s')];
        $message = "Quiz soumis. Score: $score / $max_score";
        $message_type = 'success';
    } else {
        $message = 'Erreur lors de la soumission: ' . $conn->error;
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passer le quiz - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .container{max-width:900px;margin:2rem auto;padding:0 1rem}
        .card{background:#fff;padding:1rem 1.5rem;border-radius:12px;margin-bottom:1rem}
        .question{margin-bottom:1rem}
        .answers{margin-top:.5rem}
        .answers label{display:block;margin:.25rem 0;padding:.4rem .5rem;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer}
        .header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:1.5rem;border-radius:12px;margin-bottom:1rem}
        .btn{padding:.6rem 1rem;border-radius:6px;border:none;cursor:pointer}
        .btn-primary{background:#3b82f6;color:#fff}
        .btn-secondary{background:#64748b;color:#fff}
    </style>
    <script>
        function confirmSubmit() {
            return confirm('Confirmer la soumission du quiz ?');
        }
    </script>
<?php // header below ?>
</head>
<body>
<?php include 'includes/header.php'; ?>
<main class="container">
    <div class="header">
        <h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
        <div>📚 Cours: <strong><?php echo htmlspecialchars($quiz['course_title']); ?></strong></div>
        <?php if ($quiz['due_date']): ?>
            <div>📅 Date limite: <strong><?php echo date('d/m/Y', strtotime($quiz['due_date'])); ?></strong></div>
        <?php endif; ?>
    </div>

    <?php if ($message): ?>
        <div class="<?php echo $message_type==='success' ? 'success-message' : 'error-message'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($expired): ?>
        <div class="error-message">La date limite est dépassée, le quiz n’est plus disponible.</div>
    <?php endif; ?>

    <?php if ($already_attempted): ?>
        <div class="card">
            <p><strong>Vous avez déjà passé ce quiz.</strong></p>
            <p>Score: <?php echo (int)$prev_attempt['score']; ?> / <?php echo (int)($quiz['max_score'] ?? 100); ?> — Tentative du <?php echo date('d/m/Y H:i', strtotime($prev_attempt['attempted_at'])); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$already_attempted && !$expired): ?>
        <?php if (empty($questions)): ?>
            <div class="card">Aucune question disponible pour ce quiz.</div>
        <?php else: ?>
            <form method="POST">
                <?php foreach ($questions as $idx => $q): ?>
                    <div class="card question">
                        <strong>Question <?php echo $idx + 1; ?></strong>
                        <p><?php echo htmlspecialchars($q['question_text']); ?></p>
                        <?php $qtype = $q['question_type'] ?? 'mcq'; ?>
                        <?php if ($qtype === 'open'): ?>
                            <div>
                                <label>Votre réponse</label>
                                <textarea name="open_<?php echo (int)$q['id']; ?>" rows="4" style="width:100%"></textarea>
                            </div>
                        <?php else: ?>
                            <div class="answers">
                                <?php foreach ($q['answers'] as $ans): ?>
                                    <label>
                                        <input type="radio" name="answer_<?php echo $q['id']; ?>" value="<?php echo $ans['id']; ?>">
                                        <?php echo htmlspecialchars($ans['answer_text']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div style="text-align:right">
                    <a class="btn btn-secondary" href="course_detail.php?id=<?php echo (int)$quiz['course_id']; ?>">Retour au cours</a>
                    <button class="btn btn-primary" name="submit_quiz" onclick="return confirmSubmit()">Soumettre le quiz</button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
