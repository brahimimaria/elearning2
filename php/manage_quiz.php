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

// Récupérer l'ID du quiz
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;

// Vérifier que le quiz appartient à l'enseignant
$quiz_result = $conn->query("SELECT e.*, c.titre as course_title FROM evaluations e 
                             JOIN courses c ON e.course_id = c.id 
                             WHERE e.id = $quiz_id AND e.type = 'quiz' AND c.teacher_id = $teacher_id");
if ($quiz_result->num_rows === 0) {
    header('Location: create_quiz.php');
    exit();
}
$quiz = $quiz_result->fetch_assoc();

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

// Traitement : Ajouter une question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_text = $conn->real_escape_string(trim($_POST['question_text']));
    
    if (!$question_text) {
        $message = 'La question ne peut pas être vide.';
        $message_type = 'error';
    } else {
        // Récupérer le prochain numéro d'ordre
        $order_result = $conn->query("SELECT MAX(question_order) as max_order FROM quiz_questions WHERE evaluation_id = $quiz_id");
        $order = ($order_result->fetch_assoc()['max_order'] ?? 0) + 1;
        
        $sql = "INSERT INTO quiz_questions (evaluation_id, question_text, question_order) 
                VALUES ($quiz_id, '$question_text', $order)";
        
        if ($conn->query($sql)) {
            $message = 'Question ajoutée.';
            $message_type = 'success';
            // Ajouter les réponses
            $question_id = $conn->insert_id;
            
            if (isset($_POST['answers']) && is_array($_POST['answers'])) {
                $correct_answer = isset($_POST['correct_answer']) ? (int)$_POST['correct_answer'] : 0;
                foreach ($_POST['answers'] as $idx => $answer_text) {
                    $answer_text = trim($answer_text);
                    if ($answer_text) {
                        $is_correct = ($idx == $correct_answer) ? 1 : 0;
                        $answer_text_esc = $conn->real_escape_string($answer_text);
                        $conn->query("INSERT INTO quiz_answers (question_id, answer_text, is_correct, answer_order) 
                                    VALUES ($question_id, '$answer_text_esc', $is_correct, " . ($idx + 1) . ")");
                    }
                }
            }
        } else {
            $message = 'Erreur: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

// Traitement : Modifier une question et ses réponses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_question'])) {
    $question_id = (int)$_POST['question_id'];
    $new_text = $conn->real_escape_string(trim($_POST['question_text'] ?? ''));
    if ($new_text === '') {
        $message = 'La question ne peut pas être vide.';
        $message_type = 'error';
    } else {
        $conn->query("UPDATE quiz_questions SET question_text='$new_text' WHERE id=$question_id AND evaluation_id=$quiz_id");
        $conn->query("DELETE FROM quiz_answers WHERE question_id=$question_id");
        if (isset($_POST['answers']) && is_array($_POST['answers'])) {
            $correct_answer = isset($_POST['correct_answer']) ? (int)$_POST['correct_answer'] : -1;
            $order = 1;
            foreach ($_POST['answers'] as $idx => $answer_text) {
                $answer_text = trim($answer_text);
                if ($answer_text !== '') {
                    $esc = $conn->real_escape_string($answer_text);
                    $is_correct = ($idx === $correct_answer) ? 1 : 0;
                    $conn->query("INSERT INTO quiz_answers (question_id, answer_text, is_correct, answer_order) VALUES ($question_id, '$esc', $is_correct, $order)");
                    $order++;
                }
            }
        }
        $message = 'Question mise à jour.';
        $message_type = 'success';
    }
}

// Traitement : Supprimer une question
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {
    $question_id = (int)$_POST['question_id'];
    $conn->query("DELETE FROM quiz_answers WHERE question_id = $question_id");
    $conn->query("DELETE FROM quiz_questions WHERE id = $question_id AND evaluation_id = $quiz_id");
    $message = 'Question supprimée.';
    $message_type = 'success';
}

// Récupérer toutes les questions du quiz
$questions_result = $conn->query("SELECT * FROM quiz_questions WHERE evaluation_id = $quiz_id ORDER BY question_order");
$questions = $questions_result->fetch_all(MYSQLI_ASSOC);

// Récupérer les réponses pour chaque question
foreach ($questions as &$q) {
    $answers_result = $conn->query("SELECT * FROM quiz_answers WHERE question_id = {$q['id']} ORDER BY answer_order");
    $q['answers'] = $answers_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer Quiz - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, sans-serif; background: #f8f9fa; }
        main { padding: 2rem 1rem; }
        .container { max-width: 1000px; margin: 0 auto; }
        
        .quiz-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            border-radius: 12px;
            color: white;
            margin-bottom: 2rem;
        }
        
        .quiz-header h1 {
            font-size: 2rem;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .quiz-info {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .card h2 {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #cbd5e0;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .answers-section {
            background: #f7fafc;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1.5rem;
        }
        
        .answer-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: center;
        }
        
        .answer-item input[type="text"] {
            flex: 1;
        }
        
        .answer-item input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        
        .answer-item label {
            font-weight: 400;
            margin: 0;
            cursor: pointer;
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
        
        .btn-danger {
            background: #fc5c65;
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn-danger:hover {
            background: #eb3b3b;
        }
        
        .btn-secondary {
            background: #cbd5e0;
            color: #2d3748;
        }
        
        .question-card {
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: #f7fafc;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .question-number {
            background: #667eea;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .question-text {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .answer-list {
            margin-left: 2rem;
            margin-bottom: 1rem;
        }
        
        .answer-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .answer-badge.correct {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }
        
        .answer-badge.incorrect {
            background: #edf2f7;
            color: #718096;
            border: 1px solid #cbd5e0;
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
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #718096;
        }
        
        @media (max-width: 768px) {
            .quiz-header h1 {
                font-size: 1.5rem;
            }
            
            .answer-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .question-header {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<main>
    <div class="container">
        
        <!-- Header -->
        <div class="quiz-header">
            <h1><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($quiz['title']); ?></h1>
            <div class="quiz-info">
                📚 Cours: <strong><?php echo htmlspecialchars($quiz['course_title']); ?></strong>
                <?php if ($quiz['due_date']): ?>
                    | 📅 Avant le: <strong><?php echo date('d/m/Y', strtotime($quiz['due_date'])); ?></strong>
                <?php endif; ?>
                | <?php echo count($questions); ?> question<?php echo count($questions) > 1 ? 's' : ''; ?>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="message <?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Add Question Form -->
        <div class="card">
            <h2><i class="fas fa-plus-circle"></i> Ajouter une Question</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="question_text">Énoncé de la question *</label>
                    <textarea id="question_text" name="question_text" placeholder="Écrivez la question..." required></textarea>
                </div>
                
                <div class="answers-section">
                    <h3 style="margin-bottom: 1rem; color: #2d3748;">Options de réponse (Au moins 2)</h3>
                    <div id="answers-container">
                        <div class="answer-item">
                            <input type="radio" name="correct_answer" value="0" checked required>
                            <input type="text" name="answers[]" placeholder="Option 1" required>
                            <small style="color: #667eea; font-weight: 600;">Correcte</small>
                        </div>
                        <div class="answer-item">
                            <input type="radio" name="correct_answer" value="1" required>
                            <input type="text" name="answers[]" placeholder="Option 2" required>
                        </div>
                        <div class="answer-item">
                            <input type="radio" name="correct_answer" value="2">
                            <input type="text" name="answers[]" placeholder="Option 3">
                        </div>
                        <div class="answer-item">
                            <input type="radio" name="correct_answer" value="3">
                            <input type="text" name="answers[]" placeholder="Option 4">
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary" onclick="addAnswerField()" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Ajouter une option
                    </button>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="submit" name="add_question" class="btn btn-primary">
                        <i class="fas fa-save"></i> Ajouter la Question
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Questions List -->
        <?php if (!empty($questions)): ?>
            <div class="card">
                <h2><i class="fas fa-list"></i> Questions du Quiz (<?php echo count($questions); ?>)</h2>
                
                <?php foreach ($questions as $idx => $question): ?>
                    <div class="question-card">
                        <div class="question-header">
                            <div style="flex: 1;">
                                <div class="question-number"><?php echo $idx + 1; ?></div>
                            </div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                <button type="submit" name="delete_question" class="btn btn-danger" 
                                        onclick="return confirm('Supprimer cette question?')">
                                    <i class="fas fa-trash-alt"></i> Supprimer
                                </button>
                            </form>
                        </div>
                        
                        <div class="question-text">
                            <?php echo htmlspecialchars($question['question_text']); ?>
                        </div>
                        
                        <div class="answer-list">
                            <?php foreach ($question['answers'] as $answer): ?>
                                <div class="answer-badge <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                                    <?php if ($answer['is_correct']): ?>
                                        <i class="fas fa-check-circle"></i> <strong>Correcte:</strong>
                                    <?php else: ?>
                                        <i class="far fa-circle"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($answer['answer_text']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <details>
                            <summary style="cursor:pointer;margin-bottom:.5rem"><strong>Modifier la question</strong></summary>
                            <form method="POST" style="margin-top:.5rem">
                                <input type="hidden" name="question_id" value="<?php echo (int)$question['id']; ?>">
                                <div class="form-group">
                                    <label>Énoncé</label>
                                    <textarea name="question_text" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                                </div>
                                <div class="answers-section">
                                    <h3 style="margin-bottom: 1rem; color: #2d3748;">Options de réponse</h3>
                                    <div id="edit-answers-<?php echo (int)$question['id']; ?>">
                                        <?php foreach ($question['answers'] as $i => $answer): ?>
                                            <div class="answer-item">
                                                <input type="radio" name="correct_answer" value="<?php echo $i; ?>" <?php echo $answer['is_correct'] ? 'checked' : ''; ?>>
                                                <input type="text" name="answers[]" value="<?php echo htmlspecialchars($answer['answer_text']); ?>" placeholder="Option <?php echo $i+1; ?>">
                                                <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">Supprimer</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-secondary" onclick="addEditAnswerField(<?php echo (int)$question['id']; ?>)">Ajouter une option</button>
                                </div>
                                <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1rem">
                                    <button class="btn btn-primary" name="update_question"><i class="fas fa-save"></i> Enregistrer</button>
                                </div>
                            </form>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Aucune question encore</p>
                    <p>Ajoutez votre première question pour commencer</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Actions -->
        <div style="display: flex; gap: 1rem; justify-content: flex-end; margin: 2rem 0;">
            <a href="teacher_courses.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour à Mes Cours
            </a>
            <?php if (count($questions) > 0): ?>
                <a href="preview_quiz.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-primary">
                    <i class="fas fa-eye"></i> Aperçu du Quiz
                </a>
            <?php endif; ?>
        </div>
        
    </div>
</main>

<footer>
    <div class="footer-container">
        <p>&copy; <?php echo date('Y'); ?> E-Learning. Connecté : <?php echo htmlspecialchars($_SESSION['username']); ?></p>
    </div>
</footer>

<script>
function addAnswerField() {
    const container = document.getElementById('answers-container');
    const count = container.querySelectorAll('.answer-item').length;
    
    const newItem = document.createElement('div');
    newItem.className = 'answer-item';
    newItem.innerHTML = `
        <input type="radio" name="correct_answer" value="${count}">
        <input type="text" name="answers[]" placeholder="Option ${count + 1}">
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()" style="margin-left: auto;">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(newItem);
}

function addEditAnswerField(qid) {
    const container = document.getElementById('edit-answers-' + qid);
    const count = container.querySelectorAll('.answer-item').length;
    const div = document.createElement('div');
    div.className = 'answer-item';
    div.innerHTML = `
        <input type="radio" name="correct_answer" value="\${count}">
        <input type="text" name="answers[]" placeholder="Option \${count + 1}">
        <button type="button" class="btn btn-danger" onclick="this.parentElement.remove()">Supprimer</button>
    `;
    container.appendChild(div);
}
</script>

</body>
</html>
