<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db.php';

// Only instructors
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$is_php_folder = true;
$user_id = $_SESSION['user_id'] ?? null;

// Get teacher id
$teacher = $conn->query("SELECT id FROM teachers WHERE user_id = " . (int)$user_id)->fetch_assoc();
if (!$teacher) {
    header('Location: index.php');
    exit();
}
$teacher_id = $teacher['id'];

$message = '';
$message_type = '';

// Create submissions table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS evaluation_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    user_id INT NOT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    text_submission TEXT DEFAULT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Handle create evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_evaluation'])) {
    $course_id = (int)$_POST['course_id'];
    $title = $conn->real_escape_string(trim($_POST['title']));
    $type = $conn->real_escape_string($_POST['type']);
    $description = $conn->real_escape_string(trim($_POST['description']));
    $due_date = $conn->real_escape_string($_POST['due_date']);

    // verify teacher owns the course
    $check = $conn->query("SELECT id FROM courses WHERE id=$course_id AND teacher_id=$teacher_id");
    if ($check->num_rows === 0) {
        $message = 'Cours invalide.';
        $message_type = 'error';
    } elseif (!$title || !$type) {
        $message = 'Titre et type requis.';
        $message_type = 'error';
    } else {
        $sql = "INSERT INTO evaluations (course_id, title, type, description, due_date, created_at) VALUES ($course_id, '$title', '$type', '$description', '$due_date', NOW())";
        if ($conn->query($sql)) {
            $message = 'Évaluation créée.';
            $message_type = 'success';
        } else {
            $message = 'Erreur: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// Handle delete evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_evaluation'])) {
    $eval_id = (int)$_POST['evaluation_id'];
    // ensure evaluation belongs to one of teacher's courses
    $q = $conn->query("SELECT e.id FROM evaluations e JOIN courses c ON e.course_id=c.id WHERE e.id=$eval_id AND c.teacher_id=$teacher_id");
    if ($q->num_rows > 0) {
        $conn->query("DELETE FROM evaluation_submissions WHERE evaluation_id=$eval_id");
        $conn->query("DELETE FROM evaluations WHERE id=$eval_id");
        $message = 'Évaluation supprimée.';
        $message_type = 'success';
    } else {
        $message = 'Évaluation non trouvée.';
        $message_type = 'error';
    }
}

// Fetch teacher courses and evaluations
$courses = $conn->query("SELECT * FROM courses WHERE teacher_id=$teacher_id ORDER BY titre");
$courses_list = [];
while ($c = $courses->fetch_assoc()) {
    $courses_list[] = $c;
}

$evaluations = $conn->query("SELECT e.*, c.titre as course_title FROM evaluations e JOIN courses c ON e.course_id=c.id WHERE c.teacher_id=$teacher_id ORDER BY e.created_at DESC");
$evaluations_list = $evaluations->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gérer les évaluations</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .container{max-width:1100px;margin:2rem auto;padding:0 1rem}
        .card{background:#fff;padding:1rem;border-radius:8px;margin-bottom:1rem}
        .btn{padding:.6rem 1rem;border-radius:6px;border:none;cursor:pointer}
        .btn-primary{background:#3b82f6;color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
    </style>
</head>
<body>
<?php $is_php_folder=true; include 'includes/header.php'; ?>
<main class="container">
    <h1>Gérer les évaluations</h1>
    <?php if ($message): ?>
        <div class="<?php echo $message_type==='success' ? 'success-message' : 'error-message'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Créer une évaluation</h2>
        <form method="POST">
            <div>
                <label>Cours</label>
                <select name="course_id" required>
                    <option value="">-- Sélectionnez --</option>
                    <?php foreach ($courses_list as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['titre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Titre</label>
                <input type="text" name="title" required>
            </div>
            <div>
                <label>Type</label>
                <select name="type" required>
                    <option value="quiz">Quiz</option>
                    <option value="devoir">Devoir</option>
                    <option value="examen">Examen</option>
                </select>
            </div>
            <div>
                <label>Description</label>
                <textarea name="description"></textarea>
            </div>
            <div>
                <label>Date limite</label>
                <input type="date" name="due_date">
            </div>
            <div style="margin-top:.5rem">
                <button class="btn btn-primary" name="create_evaluation">Créer</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Évaluations existantes</h2>
        <?php if (empty($evaluations_list)): ?>
            <p>Aucune évaluation.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($evaluations_list as $ev): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($ev['title']); ?></strong> 
                        (<?php echo htmlspecialchars($ev['type']); ?>) — <?php echo htmlspecialchars($ev['course_title']); ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="evaluation_id" value="<?php echo $ev['id']; ?>">
                            <button class="btn btn-danger" name="delete_evaluation" onclick="return confirm('Supprimer?')">Supprimer</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</main>
</body>
</html>