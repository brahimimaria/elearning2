<?php
session_start();
include 'db.php';
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
$enroll_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cle_inscription'])) {
    $r = enrollByKey($_SESSION['user_id'], trim($_POST['cle_inscription']));
    $enroll_msg = $r['ok'] ? 'Inscription au cours réussie.' : $r['msg'];
}
$student = getStudentByUserId($_SESSION['user_id']);
$enrolled = [];
if ($student) {
    $sid = (int)$student['id'];
    $res = $conn->query("SELECT c.id, c.titre, c.public_cible, t.nom AS t_nom, t.prenom AS t_prenom FROM course_enrollments e JOIN courses c ON e.course_id = c.id JOIN teachers t ON c.teacher_id = t.id WHERE e.student_id = $sid ORDER BY c.titre");
    if ($res) while ($row = $res->fetch_assoc()) $enrolled[] = $row;
}
$is_php_folder = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes cours - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main>
    <h2>Mes cours</h2>
    <?php if ($enroll_msg): ?>
        <div class="<?php echo strpos($enroll_msg, 'réussie') !== false ? 'success-message' : 'error-message'; ?>"><?php echo htmlspecialchars($enroll_msg); ?></div>
    <?php endif; ?>
    <?php if ($_SESSION['role'] === 'student' && $student): ?>
    <section class="add-enrollment" style="margin-bottom: 2rem;">
        <h3>Rejoindre un cours avec la clé d'inscription</h3>
        <form method="POST" style="display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin: 0;">
                <label for="cle_inscription">Clé d'inscription</label>
                <input type="text" name="cle_inscription" id="cle_inscription" required placeholder="Ex: DB2024">
            </div>
            <button type="submit" class="button">S'inscrire au cours</button>
        </form>
    </section>
    <?php endif; ?>
    <section>
        <?php if (empty($enrolled)): ?>
            <p>Vous n'êtes inscrit à aucun cours. Utilisez la clé d'inscription fournie par l'enseignant pour vous inscrire.</p>
        <?php else: ?>
            <ul style="list-style: none; padding: 0;">
                <?php foreach ($enrolled as $c): ?>
                <li style="margin-bottom: 1rem; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                    <a href="course_detail.php?id=<?php echo (int)$c['id']; ?>"><strong><?php echo htmlspecialchars($c['titre']); ?></strong></a>
                    — <?php echo htmlspecialchars($c['public_cible']); ?> — Enseignant: <?php echo htmlspecialchars($c['t_prenom'] . ' ' . $c['t_nom']); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>
<footer><div class="footer-container"><p>Connecté : <?php echo htmlspecialchars($_SESSION['username']); ?></p></div></footer>
</body>
</html>
