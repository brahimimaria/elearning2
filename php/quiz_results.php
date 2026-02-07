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
if ($quiz_id <= 0) { header('Location: teacher_evaluations.php'); exit(); }

// S'assurer que le quiz appartient à l'enseignant
$teacher = $conn->query("SELECT id FROM teachers WHERE user_id=" . (int)$user_id)->fetch_assoc();
if (!$teacher) { header('Location: index.php'); exit(); }
$teacher_id = (int)$teacher['id'];
$quiz = $conn->query("SELECT e.*, c.titre AS course_title FROM evaluations e JOIN courses c ON e.course_id=c.id WHERE e.id=$quiz_id AND e.type='quiz' AND c.teacher_id=$teacher_id")->fetch_assoc();
if (!$quiz) { header('Location: teacher_evaluations.php'); exit(); }

// S'assurer que les tables existent
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
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (answer_id) REFERENCES quiz_answers(id) ON DELETE SET NULL
)");

// Récupérer les tentatives
$attempts = $conn->query("SELECT qa.*, u.username 
                          FROM quiz_attempts qa 
                          JOIN users u ON qa.user_id=u.id 
                          WHERE qa.evaluation_id=$quiz_id 
                          ORDER BY qa.attempted_at DESC");
$attempts_list = $attempts ? $attempts->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Résultats du quiz - <?php echo htmlspecialchars($quiz['title']); ?></title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .container{max-width:1000px;margin:2rem auto;padding:0 1rem}
        .header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:1.5rem;border-radius:12px;margin-bottom:1rem}
        .card{background:#fff;padding:1rem 1.5rem;border-radius:12px;margin-bottom:1rem}
        .btn{padding:.6rem 1rem;border-radius:6px;border:none;cursor:pointer}
        .btn-secondary{background:#64748b;color:#fff}
        table{width:100%;border-collapse:collapse}
        th,td{padding:.6rem;border-bottom:1px solid #e2e8f0;text-align:left}
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<main class="container">
    <div class="header">
        <h1>Résultats — <?php echo htmlspecialchars($quiz['title']); ?></h1>
        <div>📚 Cours: <strong><?php echo htmlspecialchars($quiz['course_title']); ?></strong></div>
    </div>
    <div class="card">
        <?php if (empty($attempts_list)): ?>
            <p>Aucune tentative enregistrée pour ce quiz.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Étudiant</th>
                        <th>Score</th>
                        <th>Max</th>
                        <th>%</th>
                        <th>Passé le</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts_list as $a): 
                        $max_score = (int)($quiz['max_score'] ?? 100);
                        if ($max_score <= 0) $max_score = 100;
                        $pct = $max_score > 0 ? round(($a['score'] / $max_score) * 100) : 0;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['username']); ?></td>
                            <td><?php echo (int)$a['score']; ?></td>
                            <td><?php echo (int)$max_score; ?></td>
                            <td><?php echo $pct; ?>%</td>
                            <td><?php echo date('d/m/Y H:i', strtotime($a['attempted_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <div style="text-align:right">
        <a class="btn btn-secondary" href="teacher_evaluations.php">Retour aux évaluations</a>
        <a class="btn btn-secondary" href="manage_quiz.php?quiz_id=<?php echo (int)$quiz_id; ?>">Gérer le quiz</a>
    </div>
<?php // footer intentionally minimal ?>
</main>
</body>
</html>

