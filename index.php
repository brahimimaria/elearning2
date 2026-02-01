<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$is_php_folder = false;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <title>Plateforme E-Learning</title>
</head>
<body>
<?php include 'php/includes/header.php'; ?>
<main>
    <section class="welcome-section">
        <h2>Bienvenue sur la plateforme E-Learning</h2>
        <p>Plateforme d'apprentissage en ligne destinée aux étudiants en informatique. Caractéristiques :</p>
        <ul>
            <li>Cours par spécialité avec supports vidéo et documents</li>
            <li>Forums de discussion entre étudiants et enseignants</li>
            <li>Évaluation en ligne : Quiz, devoirs et examens</li>
            <li>Inscription aux cours par clé d'inscription</li>
            <li>Barre de recherche sur toutes les pages</li>
        </ul>
    </section>
    <section class="features-section">
        <h2>Fonctionnalités</h2>
        <div class="features">
            <div class="feature">
                <h3>Cours</h3>
                <p>Consultez les cours disponibles par spécialité et inscrivez-vous avec la clé d'inscription.</p>
            </div>
            <div class="feature">
                <h3>Ressources</h3>
                <p>Vidéos et documents pour chaque cours.</p>
            </div>
            <div class="feature">
                <h3>Forums</h3>
                <p>Discussions entre étudiants et enseignants par cours.</p>
            </div>
            <div class="feature">
                <h3>Évaluations</h3>
                <p>Quiz, devoirs et examens en ligne.</p>
            </div>
        </div>
    </section>
</main>
<footer>
    <div class="footer-container">
        <p>&copy; <?php echo date('Y'); ?> Plateforme E-Learning.</p>
        <p id="role-display">
            <?php
            if (isset($_SESSION['role'])) {
                echo "Connecté en tant que : " . htmlspecialchars($_SESSION['role']);
                echo " | <a href='php/logout.php'>Déconnexion</a>";
            } else {
                echo "Non connecté.";
            }
            ?>
        </p>
    </div>
</footer>
<script src="js/scripts.js"></script>
</body>
</html>
