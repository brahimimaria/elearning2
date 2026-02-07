<?php
session_start();
include 'db.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: courses.php'); exit; }
$course = $conn->query("SELECT c.*, t.nom AS t_nom, t.prenom AS t_prenom, t.email AS t_email FROM courses c JOIN teachers t ON c.teacher_id = t.id WHERE c.id = $id")->fetch_assoc();
if (!$course) { header('Location: courses.php'); exit; }
$can_access = false;
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'instructor') $can_access = true;
    elseif ($_SESSION['role'] === 'student') {
        $student = getStudentByUserId($_SESSION['user_id']);
        if ($student) {
            $e = $conn->query("SELECT 1 FROM course_enrollments WHERE student_id = " . (int)$student['id'] . " AND course_id = $id");
            $can_access = $e && $e->num_rows > 0;
        }
    }
}
// Supports (vidéo, documents)
$resources = $conn->query("SELECT id, title, type, url_or_path, upload_date FROM course_resources WHERE course_id = $id ORDER BY type, title");
$resources_list = $resources ? $resources->fetch_all(MYSQLI_ASSOC) : [];
// Forum: add post
if ($can_access && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forum_message'])) {
    $msg = $conn->real_escape_string(trim($_POST['forum_message']));
    if ($msg !== '') {
        $uid = (int)$_SESSION['user_id'];
        $conn->query("INSERT INTO forum_posts (course_id, user_id, message) VALUES ($id, $uid, '$msg')");
    }
}
$forum = $conn->query("SELECT fp.id, fp.message, fp.created_at, u.username FROM forum_posts fp JOIN users u ON fp.user_id = u.id WHERE fp.course_id = $id ORDER BY fp.created_at DESC LIMIT 50");
$forum_list = $forum ? $forum->fetch_all(MYSQLI_ASSOC) : [];
// Evaluations (quiz, devoirs, examens)
$evals = $conn->query("SELECT id, type, title, description, due_date, max_score FROM evaluations WHERE course_id = $id ORDER BY due_date");
$evals_list = $evals ? $evals->fetch_all(MYSQLI_ASSOC) : [];
$is_php_folder = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['titre']); ?> - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .course-detail h2 { text-align: left; }
        .section-block { margin: 2rem 0; padding: 1.5rem; background: #f8fafc; border-radius: 12px; }
        .section-block h3 { margin-bottom: 1rem; color: #1a365d; }
        .resource-item, .forum-post, .eval-item { padding: 0.75rem; margin-bottom: 0.5rem; background: white; border-radius: 8px; border: 1px solid #e2e8f0; }
        .forum-form textarea { width: 100%; min-height: 80px; padding: 0.5rem; border-radius: 8px; }
        .type-badge { font-size: 0.8rem; padding: 0.2rem 0.5rem; border-radius: 4px; background: #e2e8f0; }
        .type-video { background: #bee3f8; color: #2c5282; }
        .type-document { background: #c6f6d5; color: #2f855a; }
        .locked { opacity: 0.7; pointer-events: none; }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<main class="course-detail">
    <h2><?php echo htmlspecialchars($course['titre']); ?></h2>
    <p><strong>Public ciblé :</strong> <?php echo htmlspecialchars($course['public_cible']); ?></p>
    <p><strong>Enseignant :</strong> <?php echo htmlspecialchars($course['t_prenom'] . ' ' . $course['t_nom']); ?> — <?php echo htmlspecialchars($course['t_email']); ?></p>
    <?php if ($course['description']): ?><p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p><?php endif; ?>
    <?php if (!$can_access): ?>
        <p class="error-message">Vous devez être inscrit à ce cours (avec la clé d'inscription) pour accéder aux supports, au forum et aux évaluations. <a href="my_courses.php">Mes cours</a></p>
    <?php endif; ?>

    <div class="section-block <?php echo !$can_access ? 'locked' : ''; ?>">
        <h3>Supports (vidéo, documents)</h3>
        <?php if (empty($resources_list)): ?>
            <p>Aucun support pour le moment.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($resources_list as $r): ?>
                <li class="resource-item">
                    <span class="type-badge type-<?php echo $r['type']; ?>"><?php echo $r['type']; ?></span>
                    <strong><?php echo htmlspecialchars($r['title']); ?></strong>
                    <?php if (!empty($r['url_or_path'])): ?>
                        <a href="<?php echo htmlspecialchars($r['url_or_path']); ?>" target="_blank" rel="noopener">Ouvrir</a>
                    <?php endif; ?>
                    <?php if ($r['upload_date']): ?> — <?php echo date('d/m/Y', strtotime($r['upload_date'])); endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="section-block <?php echo !$can_access ? 'locked' : ''; ?>">
        <h3>Forum de discussion</h3>
        <?php if ($can_access): ?>
        <form method="POST" class="forum-form" style="margin-bottom: 1rem;">
            <textarea name="forum_message" placeholder="Écrire un message..." required></textarea>
            <button type="submit" class="button">Envoyer</button>
        </form>
        <?php endif; ?>
        <?php if (empty($forum_list)): ?>
            <p>Aucun message.</p>
        <?php else: ?>
            <?php foreach ($forum_list as $f): ?>
            <div class="forum-post">
                <strong><?php echo htmlspecialchars($f['username']); ?></strong> — <?php echo date('d/m/Y H:i', strtotime($f['created_at'])); ?>
                <p><?php echo nl2br(htmlspecialchars($f['message'])); ?></p>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="section-block <?php echo !$can_access ? 'locked' : ''; ?>">
        <h3>Évaluations (Quiz, devoirs, examens)</h3>
        <?php if (empty($evals_list)): ?>
            <p>Aucune évaluation pour le moment.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($evals_list as $e): ?>
                <li class="eval-item">
                    <span class="type-badge"><?php echo htmlspecialchars($e['type']); ?></span>
                    <strong><?php echo htmlspecialchars($e['title']); ?></strong>
                    <?php if ($e['due_date']): ?> — Date limite : <?php echo date('d/m/Y', strtotime($e['due_date'])); endif; ?>
                    <?php if ($e['description']): ?><p><?php echo htmlspecialchars($e['description']); ?></p><?php endif; ?>
                    <?php if ($can_access && $e['type'] === 'quiz'): ?>
                        <p><a class="button" href="take_quiz.php?quiz_id=<?php echo (int)$e['id']; ?>"><i class="fas fa-circle-question"></i> Passer le quiz</a></p>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <p><a href="courses.php" class="button">← Retour aux cours</a></p>
</main>
<footer><div class="footer-container"><p>&copy; <?php echo date('Y'); ?> E-Learning.</p></div></footer>
</body>
</html>
