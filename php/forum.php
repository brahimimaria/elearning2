<?php
session_start();
include 'db.php';

// Vérification de la session
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$is_php_folder = true;
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';
$message = '';
$error = '';

// Traiter l'ajout d'un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id']) && isset($_POST['message'])) {
    $course_id = (int)$_POST['course_id'];
    $message_text = trim($_POST['message']);
    
    if (empty($message_text)) {
        $error = 'Le message ne peut pas être vide.';
    } else {
        $msg_esc = $conn->real_escape_string($message_text);
        if ($conn->query("INSERT INTO forum_posts (course_id, user_id, message, created_at) VALUES ($course_id, $user_id, '$msg_esc', NOW())")) {
            $message = 'Message posté avec succès.';
        } else {
            $error = 'Erreur lors de l\'envoi du message.';
        }
    }
}

// Récupérer les cours accessibles à l'utilisateur
$available_courses = [];
if ($user_role === 'student') {
    $student = getStudentByUserId($user_id);
    if ($student) {
        $sid = (int)$student['id'];
        $res = $conn->query("SELECT DISTINCT c.id, c.titre, c.public_cible FROM course_enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = $sid ORDER BY c.titre");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $available_courses[] = $row;
            }
        }
    }
} elseif ($user_role === 'instructor') {
    $teacher = getTeacherByUserId($user_id);
    if ($teacher) {
        $tid = (int)$teacher['id'];
        $res = $conn->query("SELECT DISTINCT c.id, c.titre, c.public_cible FROM courses c WHERE c.teacher_id = $tid ORDER BY c.titre");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $available_courses[] = $row;
            }
        }
    }
}

// Récupérer le cours sélectionné
$selected_course_id = isset($_GET['course']) ? (int)$_GET['course'] : (count($available_courses) > 0 ? $available_courses[0]['id'] : null);
$forum_posts = [];
$selected_course = null;

if ($selected_course_id) {
    // Vérifier que l'utilisateur a accès à ce cours
    $has_access = false;
    foreach ($available_courses as $course) {
        if ($course['id'] == $selected_course_id) {
            $has_access = true;
            $selected_course = $course;
            break;
        }
    }
    
    if ($has_access) {
        // Récupérer les posts du forum
        $res = $conn->query("SELECT fp.id, fp.message, fp.created_at, u.username, u.role, s.nom, s.prenom, t.email FROM forum_posts fp JOIN users u ON fp.user_id = u.id LEFT JOIN students s ON u.id = s.user_id LEFT JOIN teachers t ON u.id = t.user_id WHERE fp.course_id = $selected_course_id ORDER BY fp.created_at DESC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $forum_posts[] = $row;
            }
        }
    }
}

// Fonction utilitaire pour obtenir le nom complet de l'utilisateur
function get_user_display_name($post) {
    if ($post['role'] === 'student' && $post['nom']) {
        return htmlspecialchars($post['prenom'] . ' ' . $post['nom']);
    } elseif ($post['role'] === 'instructor') {
        return 'Enseignant: ' . htmlspecialchars($post['username']);
    }
    return htmlspecialchars($post['username']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, sans-serif; background: #f8f9fa; }
        main { padding: 2rem 1rem; }
        .container { max-width: 1000px; margin: 0 auto; }
        
        /* Header */
        .forum-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            border-radius: 12px;
            color: white;
            margin-bottom: 2rem;
        }
        
        .forum-header h1 {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .forum-header p {
            opacity: 0.95;
            font-size: 1.05rem;
        }
        
        /* Course Selection */
        .course-selector {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .course-selector label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #2d3748;
        }
        
        .course-selector select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #cbd5e0;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
        }
        
        .course-selector select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Main Forum Container */
        .forum-container {
            display: grid;
            gap: 2rem;
        }
        
        /* Messages Section */
        .messages-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .messages-header {
            background: #f7fafc;
            padding: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .messages-header h2 {
            color: #2d3748;
            font-size: 1.3rem;
            margin: 0;
        }
        
        .messages-list {
            max-height: 500px;
            overflow-y: auto;
            padding: 0;
        }
        
        .forum-post {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 1rem;
        }
        
        .forum-post:last-child {
            border-bottom: none;
        }
        
        .forum-post:hover {
            background: #f7fafc;
        }
        
        .post-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .post-content {
            flex: 1;
        }
        
        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .post-author {
            font-weight: 600;
            color: #2d3748;
        }
        
        .post-role {
            font-size: 0.85rem;
            background: #667eea;
            color: white;
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            margin-left: 0.5rem;
        }
        
        .post-role.student {
            background: #48bb78;
        }
        
        .post-role.teacher {
            background: #ed8936;
        }
        
        .post-time {
            font-size: 0.85rem;
            color: #718096;
        }
        
        .post-message {
            color: #4a5568;
            line-height: 1.6;
            word-wrap: break-word;
        }
        
        /* Message Form */
        .message-form {
            background: #f7fafc;
            padding: 1.5rem;
            border-top: 2px solid #e2e8f0;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #cbd5e0;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-submit {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        /* Messages */
        .success-message, .error-message {
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
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #718096;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .forum-header h1 {
                font-size: 1.5rem;
            }
            
            .forum-post {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<main>
    <div class="container">
        
        <!-- Forum Header -->
        <div class="forum-header">
            <h1><i class="fas fa-comments"></i> Forum de Discussion</h1>
            <p>Discutez avec vos enseignants et posez vos questions par cours</p>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Course Selection -->
        <?php if (count($available_courses) > 0): ?>
            <div class="course-selector">
                <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label for="course">Sélectionner un cours</label>
                        <select name="course" id="course" onchange="this.form.submit()">
                            <?php foreach ($available_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo ($selected_course_id == $course['id']) ? 'selected' : ''; ?>>
                                    📚 <?php echo htmlspecialchars($course['titre']); ?> (<?php echo htmlspecialchars($course['public_cible']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Forum Container -->
            <?php if ($selected_course): ?>
                <div class="messages-section">
                    <div class="messages-header">
                        <h2><?php echo htmlspecialchars($selected_course['titre']); ?> - Forum</h2>
                    </div>
                    
                    <!-- Messages List -->
                    <div class="messages-list">
                        <?php if (empty($forum_posts)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">💬</div>
                                <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Aucun message dans ce forum</p>
                                <p style="font-size: 0.95rem;">Soyez le premier à poser une question!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($forum_posts as $post): ?>
                                <div class="forum-post">
                                    <div class="post-avatar">
                                        <?php echo strtoupper(substr($post['username'], 0, 1)); ?>
                                    </div>
                                    <div class="post-content">
                                        <div class="post-header">
                                            <div>
                                                <span class="post-author"><?php echo get_user_display_name($post); ?></span>
                                                <span class="post-role <?php echo strtolower($post['role']); ?>">
                                                    <?php echo ($post['role'] === 'student') ? 'Étudiant' : 'Enseignant'; ?>
                                                </span>
                                            </div>
                                            <span class="post-time">
                                                <i class="far fa-clock"></i> 
                                                <?php 
                                                    $time = strtotime($post['created_at']);
                                                    $now = time();
                                                    $diff = $now - $time;
                                                    
                                                    if ($diff < 60) {
                                                        echo "à l'instant";
                                                    } elseif ($diff < 3600) {
                                                        echo "il y a " . floor($diff / 60) . " min";
                                                    } elseif ($diff < 86400) {
                                                        echo "il y a " . floor($diff / 3600) . " h";
                                                    } else {
                                                        echo date('d/m/y à H:i', $time);
                                                    }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="post-message">
                                            <?php echo htmlspecialchars($post['message']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Message Form -->
                    <form method="POST" class="message-form">
                        <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                        
                        <div class="form-group">
                            <label for="message">Votre message</label>
                            <textarea name="message" id="message" placeholder="Écrivez votre question ou commentaire ici..." required></textarea>
                        </div>
                        
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Envoyer
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Vous n'êtes inscrit à aucun cours</p>
                <p style="font-size: 0.95rem;">Inscrivez-vous à un cours pour accéder aux forums de discussion.</p>
                <a href="my_courses.php" style="display: inline-block; margin-top: 1rem; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">
                    Voir les cours disponibles
                </a>
            </div>
        <?php endif; ?>
        
    </div>
</main>

<footer>
    <div class="footer-container">
        <p>&copy; <?php echo date('Y'); ?> E-Learning. Connecté : <?php echo htmlspecialchars($_SESSION['username']); ?></p>
    </div>
</footer>

</body>
</html>
