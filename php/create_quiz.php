<?php
session_start();
include 'db.php';

// Vérification enseignant
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

$is_php_folder = true;
$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Récupérer les infos de l'enseignant
$teacher = $conn->query("SELECT id FROM teachers WHERE user_id = $user_id")->fetch_assoc();
if (!$teacher) {
    header('Location: index.php');
    exit();
}
$teacher_id = $teacher['id'];

// Créer les tables si elles n'existent pas
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

// Traitement : Créer un nouveau quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    $course_id = (int)$_POST['course_id'];
    $title = $conn->real_escape_string(trim($_POST['title']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $due_date = $_POST['due_date'] ? $conn->real_escape_string($_POST['due_date']) : NULL;

    // Vérifier que le cours appartient à l'enseignant
    $check = $conn->query("SELECT id FROM courses WHERE id=$course_id AND teacher_id=$teacher_id");
    if ($check->num_rows === 0) {
        $message = 'Cours invalide ou non autorisé.';
        $message_type = 'error';
    } elseif (!$title) {
        $message = 'Le titre du quiz est obligatoire.';
        $message_type = 'error';
    } else {
        $due_date_sql = $due_date ? "'$due_date'" : 'NULL';
        $sql = "INSERT INTO evaluations (course_id, type, title, description, due_date, created_at) 
                VALUES ($course_id, 'quiz', '$title', '$description', $due_date_sql, NOW())";
        
        if ($conn->query($sql)) {
            $new_quiz_id = $conn->insert_id;
            $message = 'Quiz créé! Vous pouvez maintenant ajouter des questions.';
            $message_type = 'success';
            header("Location: manage_quiz.php?quiz_id=$new_quiz_id");
            exit();
        } else {
            $message = 'Erreur: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// Récupérer les cours de l'enseignant
$courses_result = $conn->query("SELECT id, titre FROM courses WHERE teacher_id=$teacher_id ORDER BY titre");
$courses = $courses_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Quiz - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, sans-serif; background: #f8f9fa; }
        main { padding: 2rem 1rem; }
        .container { max-width: 900px; margin: 0 auto; }
        
        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            border-radius: 12px;
            color: white;
            margin-bottom: 2rem;
        }
        
        .form-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .form-header p {
            opacity: 0.95;
        }
        
        .form-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #cbd5e0;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
        }
        
        .btn-secondary:hover {
            background: #a0aec0;
        }
        
        .message {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .success-message {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }
        
        .error-message {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #fc8181;
        }
        
        .info-box {
            background: #edf2f7;
            border-left: 4px solid #667eea;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .info-box strong {
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .form-header h1 {
                font-size: 1.5rem;
            }
            
            .form-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<main>
    <div class="container">
        
        <!-- Header -->
        <div class="form-header">
            <h1><i class="fas fa-clipboard-list"></i> Créer un Quiz</h1>
            <p>Commencez par créer votre quiz, puis ajoutez les questions</p>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="message <?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Info Box -->
        <div class="info-box">
            <strong><i class="fas fa-info-circle"></i> Comment ça marche :</strong>
            <ol style="margin-left: 2rem; margin-top: 0.5rem; line-height: 1.8;">
                <li>Remplissez les informations du quiz ci-dessous</li>
                <li>Cliquez sur "Créer le Quiz"</li>
                <li>Vous serez redirigé pour ajouter des questions</li>
                <li>Pour chaque question, définissez les options et la réponse correcte</li>
            </ol>
        </div>
        
        <!-- Form -->
        <div class="form-card">
            <form method="POST">
                <div class="form-group">
                    <label for="course_id"><i class="fas fa-book"></i> Cours *</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">-- Sélectionnez un cours --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['titre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title"><i class="fas fa-heading"></i> Titre du Quiz *</label>
                    <input type="text" id="title" name="title" placeholder="Ex: Quiz 1 - Les Bases de Données" required>
                </div>
                
                <div class="form-group">
                    <label for="description"><i class="fas fa-align-left"></i> Description</label>
                    <textarea id="description" name="description" placeholder="Décrivez le contenu et les objectifs du quiz..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="due_date"><i class="fas fa-calendar"></i> Date limite (optionnel)</label>
                    <input type="date" id="due_date" name="due_date">
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <a href="teacher_courses.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                    <button type="submit" name="create_quiz" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Créer le Quiz
                    </button>
                </div>
            </form>
        </div>
        
    </div>
</main>

<footer>
    <div class="footer-container">
        <p>&copy; <?php echo date('Y'); ?> E-Learning. Connecté : <?php echo htmlspecialchars($_SESSION['username']); ?></p>
    </div>
</footer>

</body>
</html>
