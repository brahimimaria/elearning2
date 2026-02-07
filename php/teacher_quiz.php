<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db.php';

// Accès enseignant uniquement
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$is_php_folder = true;
$user_id = (int)($_SESSION['user_id'] ?? 0);
$teacher = $conn->query("SELECT id FROM teachers WHERE user_id=$user_id")->fetch_assoc();
if (!$teacher) { header('Location: index.php'); exit(); }
$teacher_id = (int)$teacher['id'];

// Créer/étendre tables si besoin
$conn->query("CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_order INT NOT NULL,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE
)");
$conn->query("CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    answer_text VARCHAR(500) NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    answer_order INT NOT NULL,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
)");
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
$conn->query("CREATE TABLE IF NOT EXISTS quiz_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT NOT NULL,
    student_id INT NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_eval_student (evaluation_id, student_id),
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
)");

// Ajouter colonnes si manquantes (duration_minutes, question_type)
function ensureColumn($conn, $table, $column, $definition) {
    $res = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
    if (!$res || $res->num_rows === 0) {
        $conn->query("ALTER TABLE $table ADD $column $definition");
    }
}
ensureColumn($conn, 'evaluations', 'duration_minutes', 'INT DEFAULT 0');
ensureColumn($conn, 'quiz_questions', 'question_type', "ENUM('mcq','open') DEFAULT 'mcq'");

$message = '';
$message_type = '';

// Créer un nouveau quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    $course_id = (int)$_POST['course_id'];
    $title = $conn->real_escape_string(trim($_POST['title']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $due_date = $_POST['due_date'] ? $conn->real_escape_string($_POST['due_date']) : NULL;
    $max_score = (int)($_POST['max_score'] ?? 100);
    $duration = (int)($_POST['duration_minutes'] ?? 0);

    $check = $conn->query("SELECT id FROM courses WHERE id=$course_id AND teacher_id=$teacher_id");
    if ($check->num_rows === 0) {
        $message = 'Cours invalide ou non autorisé.';
        $message_type = 'error';
    } elseif (!$title) {
        $message = 'Le titre du quiz est obligatoire.';
        $message_type = 'error';
    } else {
        $due_date_sql = $due_date ? "'$due_date'" : 'NULL';
        $sql = "INSERT INTO evaluations (course_id, type, title, description, due_date, max_score, duration_minutes, created_at) 
                VALUES ($course_id, 'quiz', '$title', '$description', $due_date_sql, $max_score, $duration, NOW())";
        if ($conn->query($sql)) {
            $message = 'Quiz créé.';
            $message_type = 'success';
        } else {
            $message = 'Erreur: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// Ajouter une question (QCM ou ouverte)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $evaluation_id = (int)$_POST['evaluation_id'];
    // Vérifier que le quiz appartient au prof
    $check = $conn->query("SELECT e.id FROM evaluations e JOIN courses c ON e.course_id=c.id WHERE e.id=$evaluation_id AND e.type='quiz' AND c.teacher_id=$teacher_id");
    if ($check->num_rows === 0) {
        $message = 'Quiz non autorisé.';
        $message_type = 'error';
    } else {
        $question_text = $conn->real_escape_string(trim($_POST['question_text']));
        $question_type = $_POST['question_type'] === 'open' ? 'open' : 'mcq';
        if ($question_text === '') {
            $message = 'Énoncé requis.';
            $message_type = 'error';
        } else {
            $order_res = $conn->query("SELECT COALESCE(MAX(question_order),0)+1 AS next_order FROM quiz_questions WHERE evaluation_id=$evaluation_id");
            $next_order = (int)$order_res->fetch_assoc()['next_order'];
            if ($conn->query("INSERT INTO quiz_questions (evaluation_id, question_text, question_order, question_type) VALUES ($evaluation_id, '$question_text', $next_order, '$question_type')")) {
                $qid = $conn->insert_id;
                if ($question_type === 'mcq') {
                    $answers = $_POST['answers'] ?? [];
                    $correct_idx = isset($_POST['correct_answer']) ? (int)$_POST['correct_answer'] : -1;
                    $inserted = 0;
                    foreach ($answers as $idx => $ans_text) {
                        $ans_text = trim($ans_text);
                        if ($ans_text !== '') {
                            $esc = $conn->real_escape_string($ans_text);
                            $is_correct = ($idx === $correct_idx) ? 1 : 0;
                            $conn->query("INSERT INTO quiz_answers (question_id, answer_text, is_correct, answer_order) VALUES ($qid, '$esc', $is_correct, " . ($idx + 1) . ")");
                            $inserted++;
                        }
                    }
                    if ($inserted < 2) {
                        $message = 'Au moins 2 options de réponse sont requises.';
                        $message_type = 'error';
                        // Nettoyage si insuffisant
                        $conn->query("DELETE FROM quiz_answers WHERE question_id=$qid");
                        $conn->query("DELETE FROM quiz_questions WHERE id=$qid");
                    } else {
                        $message = 'Question QCM ajoutée.';
                        $message_type = 'success';
                    }
                } else {
                    $message = 'Question ouverte ajoutée.';
                    $message_type = 'success';
                }
            } else {
                $message = 'Erreur ajout question: ' . $conn->error;
                $message_type = 'error';
            }
        }
    }
}

// Assigner le quiz à des étudiants
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_quiz'])) {
    $evaluation_id = (int)$_POST['evaluation_id'];
    $students = $_POST['students'] ?? [];
    $check = $conn->query("SELECT e.id, e.course_id FROM evaluations e JOIN courses c ON e.course_id=c.id WHERE e.id=$evaluation_id AND e.type='quiz' AND c.teacher_id=$teacher_id");
    if ($check->num_rows === 0) {
        $message = 'Quiz non autorisé.';
        $message_type = 'error';
    } else {
        $course_id = (int)$check->fetch_assoc()['course_id'];
        foreach ($students as $sid) {
            $sid = (int)$sid;
            // Vérifier que l'étudiant est inscrit au cours
            $en = $conn->query("SELECT s.id FROM students s JOIN course_enrollments ce ON ce.student_id=s.id WHERE s.id=$sid AND ce.course_id=$course_id");
            if ($en && $en->num_rows > 0) {
                @$conn->query("INSERT INTO quiz_assignments (evaluation_id, student_id) VALUES ($evaluation_id, $sid)");
            }
        }
        $message = 'Affectations enregistrées.';
        $message_type = 'success';
    }
}

// Données pour affichage
$courses = $conn->query("SELECT id, titre FROM courses WHERE teacher_id=$teacher_id ORDER BY titre");
$courses_list = $courses ? $courses->fetch_all(MYSQLI_ASSOC) : [];
$evaluations = $conn->query("SELECT e.*, c.titre AS course_title FROM evaluations e JOIN courses c ON e.course_id=c.id WHERE c.teacher_id=$teacher_id AND e.type='quiz' ORDER BY e.created_at DESC");
$quiz_list = $evaluations ? $evaluations->fetch_all(MYSQLI_ASSOC) : [];

// Étudiants par cours (pour affectation)
$students_by_course = [];
foreach ($courses_list as $c) {
    $cid = (int)$c['id'];
    $res = $conn->query("SELECT s.id, s.nom, s.prenom FROM students s JOIN course_enrollments ce ON ce.student_id=s.id WHERE ce.course_id=$cid ORDER BY s.nom, s.prenom");
    $students_by_course[$cid] = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Quiz — Tableau de bord</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .container{max-width:1100px;margin:2rem auto;padding:0 1rem}
        .card{background:#fff;padding:1rem 1.5rem;border-radius:12px;margin-bottom:1rem}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .btn{padding:.6rem 1rem;border-radius:6px;border:none;cursor:pointer}
        .btn-primary{background:#3b82f6;color:#fff}
        .btn-secondary{background:#64748b;color:#fff}
        .btn-success{background:#10b981;color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
        .success-message{background:#c6f6d5;color:#2f855a;border:1px solid #9ae6b4;padding:1rem;border-radius:6px;margin-bottom:1rem}
        .error-message{background:#fed7d7;color:#c53030;border:1px solid #fc8181;padding:1rem;border-radius:6px;margin-bottom:1rem}
        label{display:block;margin:.4rem 0;font-weight:600}
        input,select,textarea{width:100%;padding:.5rem;border:1px solid #e2e8f0;border-radius:8px}
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<main class="container">
    <h1><i class="fas fa-circle-question"></i> Quiz — Enseignant</h1>
    <?php if ($message): ?>
        <div class="<?php echo $message_type==='success' ? 'success-message' : 'error-message'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <h2>Créer un nouveau quiz</h2>
            <form method="POST">
                <label>Cours</label>
                <select name="course_id" required>
                    <option value="">-- Sélectionnez --</option>
                    <?php foreach ($courses_list as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['titre']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Titre</label>
                <input type="text" name="title" required>
                <label>Description</label>
                <textarea name="description"></textarea>
                <label>Date limite</label>
                <input type="date" name="due_date">
                <div class="grid" style="grid-template-columns:1fr 1fr">
                    <div>
                        <label>Note maximale</label>
                        <input type="number" name="max_score" min="1" value="100">
                    </div>
                    <div>
                        <label>Durée (minutes)</label>
                        <input type="number" name="duration_minutes" min="0" value="0">
                    </div>
                </div>
                <div style="margin-top:.5rem">
                    <button class="btn btn-primary" name="create_quiz">Créer</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Mes quiz</h2>
            <?php if (empty($quiz_list)): ?>
                <p>Aucun quiz créé.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($quiz_list as $q): ?>
                        <li style="margin:.5rem 0">
                            <strong><?php echo htmlspecialchars($q['title']); ?></strong> — <?php echo htmlspecialchars($q['course_title']); ?>
                            <div style="display:flex;gap:.5rem;margin-top:.4rem;flex-wrap:wrap">
                                <a class="btn btn-secondary" href="preview_quiz.php?quiz_id=<?php echo (int)$q['id']; ?>">Aperçu</a>
                                <a class="btn btn-secondary" href="manage_quiz.php?quiz_id=<?php echo (int)$q['id']; ?>">Gérer questions</a>
                                <a class="btn btn-success" href="quiz_results.php?quiz_id=<?php echo (int)$q['id']; ?>">Résultats</a>
                            </div>
                            <details style="margin-top:.5rem">
                                <summary>Ajouter une question</summary>
                                <form method="POST" style="margin-top:.5rem">
                                    <input type="hidden" name="evaluation_id" value="<?php echo (int)$q['id']; ?>">
                                    <label>Type</label>
                                    <select name="question_type">
                                        <option value="mcq">QCM</option>
                                        <option value="open">Question ouverte</option>
                                    </select>
                                    <label>Énoncé</label>
                                    <textarea name="question_text" required></textarea>
                                    <div id="answers-<?php echo (int)$q['id']; ?>" class="answers-block">
                                        <small>Pour QCM: indiquez au moins 2 options et cochez la correcte</small>
                                        <div style="display:flex;gap:.5rem;align-items:center;margin:.25rem 0">
                                            <input type="radio" name="correct_answer" value="0" checked>
                                            <input type="text" name="answers[]" placeholder="Option 1">
                                        </div>
                                        <div style="display:flex;gap:.5rem;align-items:center;margin:.25rem 0">
                                            <input type="radio" name="correct_answer" value="1">
                                            <input type="text" name="answers[]" placeholder="Option 2">
                                        </div>
                                        <div style="display:flex;gap:.5rem;align-items:center;margin:.25rem 0">
                                            <input type="radio" name="correct_answer" value="2">
                                            <input type="text" name="answers[]" placeholder="Option 3">
                                        </div>
                                        <div style="display:flex;gap:.5rem;align-items:center;margin:.25rem 0">
                                            <input type="radio" name="correct_answer" value="3">
                                            <input type="text" name="answers[]" placeholder="Option 4">
                                        </div>
                                    </div>
                                    <div style="margin-top:.5rem">
                                        <button class="btn btn-primary" name="add_question">Ajouter</button>
                                    </div>
                                </form>
                            </details>
                            <details style="margin-top:.5rem">
                                <summary>Assigner aux étudiants</summary>
                                <form method="POST" style="margin-top:.5rem">
                                    <input type="hidden" name="evaluation_id" value="<?php echo (int)$q['id']; ?>">
                                    <?php $cid=(int)$q['course_id']; $studs=$students_by_course[$cid] ?? []; ?>
                                    <?php if (empty($studs)): ?>
                                        <p>Aucun étudiant inscrit au cours.</p>
                                    <?php else: ?>
                                        <div style="max-height:180px;overflow:auto;border:1px solid #e2e8f0;padding:.5rem;border-radius:8px">
                                            <?php foreach($studs as $s): ?>
                                                <label style="display:flex;gap:.5rem;align-items:center;margin:.15rem 0">
                                                    <input type="checkbox" name="students[]" value="<?php echo (int)$s['id']; ?>">
                                                    <?php echo htmlspecialchars($s['prenom'].' '.$s['nom']); ?>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div style="margin-top:.5rem">
                                            <button class="btn btn-primary" name="assign_quiz">Assigner</button>
                                        </div>
                                    <?php endif; ?>
                                </form>
                            </details>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>

