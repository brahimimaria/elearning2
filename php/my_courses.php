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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, sans-serif; background: #f8f9fa; }
        main { padding: 2rem 1rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Header Section */
        .header-section { 
            display: flex; 
            align-items: center; 
            gap: 2rem; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 3rem 2rem;
            border-radius: 12px;
            color: white;
            margin-bottom: 3rem;
        }
        .header-icon { font-size: 4rem; }
        .header-text h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .header-text p { font-size: 1.1rem; opacity: 0.9; }

        /* Card Styles */
        .card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }
        
        .card h2 { 
            font-size: 1.8rem; 
            color: #2d3748; 
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Specialty Buttons */
        .specialty-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .specialty-btn {
            padding: 1rem;
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .specialty-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-informatique { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .btn-biologie { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .btn-st { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

        /* Enrollment Form */
        #enrollForm {
            display: none;
            background: #f7fafc;
            padding: 2rem;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-top: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .form-group label {
            font-weight: 600;
            color: #2d3748;
        }

        .form-group input {
            padding: 0.75rem;
            border: 2px solid #cbd5e0;
            border-radius: 6px;
            font-size: 1rem;
        }

        .btn-submit {
            padding: 0.75rem 2rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-submit:hover {
            background: #5568d3;
        }

        /* Courses Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .course-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .course-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.12);
        }

        .course-card h3 {
            color: #2d3748;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .course-card a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .course-info {
            color: #718096;
            font-size: 0.95rem;
            margin: 0.5rem 0;
        }

        .btn-access {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.6rem 1.2rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-access:hover {
            background: #5568d3;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: #f0f4ff;
            border-radius: 12px;
            color: #718096;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

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
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<main>
    <div class="container">
        
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-icon">📚</div>
            <div class="header-text">
                <h1>Mes Cours</h1>
                <p>Gérez vos cours et inscrivez-vous à de nouveaux</p>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($enroll_msg): ?>
            <div class="<?php echo strpos($enroll_msg, 'réussie') !== false ? 'success-message' : 'error-message'; ?>">
                <?php echo htmlspecialchars($enroll_msg); ?>
            </div>
        <?php endif; ?>

        <!-- Enrollment Section -->
        <div class="card">
            <h2>➕ Rejoindre un nouveau cours</h2>
            
            <label style="display: block; margin-bottom: 1rem; font-weight: 600; color: #2d3748;">Sélectionnez une spécialité :</label>
            <div class="specialty-buttons">
                <button type="button" onclick="showEnrollForm('Informatique')" class="specialty-btn btn-informatique">
                    💻 Informatique
                </button>
                <button type="button" onclick="showEnrollForm('Biologie')" class="specialty-btn btn-biologie">
                    🧪 Biologie
                </button>
                <button type="button" onclick="showEnrollForm('ST')" class="specialty-btn btn-st">
                    🔬 ST
                </button>
            </div>

            <!-- Hidden Enrollment Form -->
            <div id="enrollForm">
                <h3 style="color: #2d3748; margin-bottom: 1rem;">Entrez la clé d'inscription pour <span id="specialtyName" style="color: #667eea;"></span></h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="cle_inscription">Clé d'inscription</label>
                        <input type="text" name="cle_inscription" id="cle_inscription" required placeholder="Ex: DB2024" autofocus>
                    </div>
                    <button type="submit" class="btn-submit">🔑 S'inscrire</button>
                </form>
            </div>
        </div>

        <!-- Enrolled Courses Section -->
        <div class="card">
            <h2>🎓 Mes cours inscrits</h2>
            
            <?php if (empty($enrolled)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">Vous n'êtes inscrit à aucun cours</p>
                    <p style="font-size: 0.95rem;">Utilisez la section ci-dessus pour rejoindre des cours avec une clé d'inscription</p>
                </div>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($enrolled as $c): ?>
                    <div class="course-card">
                        <h3>
                            <a href="course_detail.php?id=<?php echo (int)$c['id']; ?>">
                                📖 <?php echo htmlspecialchars($c['titre']); ?>
                            </a>
                        </h3>
                        <div class="course-info">
                            <strong>Spécialité :</strong> <?php echo htmlspecialchars($c['public_cible']); ?>
                        </div>
                        <div class="course-info">
                            <strong>Enseignant :</strong> <?php echo htmlspecialchars($c['t_prenom'] . ' ' . $c['t_nom']); ?>
                        </div>
                        <a href="course_detail.php?id=<?php echo (int)$c['id']; ?>" class="btn-access">
                            ➡️ Accéder au cours
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
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
    function showEnrollForm(specialty) {
        const form = document.getElementById('enrollForm');
        const input = document.getElementById('cle_inscription');
        
        form.style.display = 'block';
        document.getElementById('specialtyName').textContent = specialty;
        input.value = '';
        input.focus();
        
        // Scroll to form
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
</script>

</body>
</html>
