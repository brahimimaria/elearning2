<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db.php';

// Réservé aux enseignants
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$is_php_folder = true;
$user_id = $_SESSION['user_id'] ?? null;
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
if ($quiz_id <= 0) {
    header('Location: teacher_evaluations.php');
    exit();
}

// S'assurer que le quiz appartient à l'enseignant
$teacher = $conn->query("SELECT id FROM teachers WHERE user_id=" . (int)$user_id)->fetch_assoc();
if (!$teacher) { header('Location: index.php'); exit(); }
$teacher_id = (int)$teacher['id'];

$quiz = $conn->query("SELECT e.*, c.titre AS course_title FROM evaluations e JOIN courses c ON e.course_id=c.id WHERE e.id=$quiz_id AND e.type='quiz' AND c.teacher_id=$teacher_id")->fetch_assoc();
if (!$quiz) { header('Location: teacher_evaluations.php'); exit(); }

$questions_result = $conn->query("SELECT * FROM quiz_questions WHERE evaluation_id = $quiz_id ORDER BY question_order");
$questions = $questions_result ? $questions_result->fetch_all(MYSQLI_ASSOC) : [];
foreach ($questions as &$q) {
    $answers_result = $conn->query("SELECT * FROM quiz_answers WHERE question_id = {$q['id']} ORDER BY answer_order");
    $q['answers'] = $answers_result ? $answers_result->fetch_all(MYSQLI_ASSOC) : [];
}
unset($q);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Aperçu du quiz - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .container{max-width:900px;margin:2rem auto;padding:0 1rem}
        .card{background:#fff;padding:1rem 1.5rem;border-radius:12px;margin-bottom:1rem}
        .header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:1.5rem;border-radius:12px;margin-bottom:1rem}
        .answer{padding:.3rem .5rem;border:1px solid #e2e8f0;border-radius:8px;margin:.25rem 0}
        .answer.correct{border-color:#10b981;background:#ecfdf5}
        .btn{padding:.6rem 1rem;border-radius:6px;border:none;cursor:pointer}
        .btn-secondary{background:#64748b;color:#fff}
    </style>
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
    <?php if (empty($questions)): ?>
        <div class="card">Aucune question pour ce quiz.</div>
    <?php else: ?>
        <?php foreach ($questions as $idx => $q): ?>
            <div class="card">
                <strong>Question <?php echo $idx + 1; ?></strong>
                <p><?php echo htmlspecialchars($q['question_text']); ?></p>
                <div>
                    <?php foreach ($q['answers'] as $a): ?>
                        <div class="answer <?php echo $a['is_correct'] ? 'correct' : ''; ?>">
                            <?php echo htmlspecialchars($a['answer_text']); ?>
                            <?php if ($a['is_correct']): ?> — correcte<?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <div style="text-align:right">
        <a class="btn btn-secondary" href="manage_quiz.php?quiz_id=<?php echo (int)$quiz_id; ?>">Retour à la gestion</a>
    </div>
</main>
</body>
</html>

