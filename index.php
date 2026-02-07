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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Plateforme E-Learning</title>
</head>
<body>
<?php 
if (isset($_SESSION['username'])) {
    include 'php/includes/header.php';
}
?>
<main class="<?php echo isset($_SESSION['username']) ? 'main-authenticated' : 'main-welcome'; ?>">
    <?php if (!isset($_SESSION['username'])): ?>
    <!-- Section d'accueil pour utilisateurs non connectés -->
    <section class="landing-section">
        <div class="landing-content">
            <div class="landing-header">
                <h1><i class="fas fa-graduation-cap"></i> Plateforme E-Learning</h1>
                <p class="tagline">Bienvenue sur notre plateforme d'apprentissage en ligne</p>
            </div>

            <!-- Boutons de Connexion et Inscription -->
            <div class="auth-buttons-container">
                <a href="php/login.php" class="auth-button login-button">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Se Connecter</span>
                </a>
                <a href="php/register.php" class="auth-button register-button">
                    <i class="fas fa-user-plus"></i>
                    <span>S'Inscrire</span>
                </a>
            </div>

            <!-- Fonctionnalités principales -->
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Cours Complets</h3>
                    <p>Accédez à des cours de qualité par spécialité en informatique avec supports vidéo et documents.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3>Ressources Variées</h3>
                    <p>Consultez des vidéos, documents et matériels pédagogiques pour chaque cours.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Forums Actifs</h3>
                    <p>Discutez avec vos enseignants et camarades dans les forums dédiés.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>Évaluations</h3>
                    <p>Passez des quiz, soumettez des devoirs et participez à des examens en ligne.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h3>Inscription par Clé</h3>
                    <p>Inscrivez-vous à des cours en utilisant une clé d'inscription unique.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Recherche Avancée</h3>
                    <p>Trouvez facilement les cours, enseignants et ressources avec notre barre de recherche.</p>
                </div>
            </div>

            <!-- Appel à l'action -->
            <div class="cta-section">
                <h2>Prêt à commencer votre apprentissage ?</h2>
                <p>Créez votre compte ou connectez-vous pour accéder à tous les cours.</p>
                <div class="cta-buttons">
                    <a href="php/register.php" class="cta-button primary">
                        <i class="fas fa-rocket"></i> Commencer maintenant
                    </a>
                    <a href="php/login.php" class="cta-button secondary">
                        <i class="fas fa-sign-in-alt"></i> Se connecter
                    </a>
                </div>
            </div>
        </div>
    </section>

    <?php else: ?>
    <!-- Section pour utilisateurs connectés -->
    <section class="welcome-section">
        <h2>Bienvenue sur la plateforme E-Learning</h2>
        <p>Plateforme d'apprentissage en ligne destinée aux étudiants en informatique.</p>
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
    <?php endif; ?>
</main>
<footer>
    <div class="footer-container">
        <p>&copy; <?php echo date('Y'); ?> Plateforme E-Learning.</p>
        <?php if (isset($_SESSION['username'])): ?>
        <p id="role-display">
            Connecté en tant que : <?php echo htmlspecialchars($_SESSION['role'] ?? 'Utilisateur'); ?>
            | <a href='php/logout.php'>Déconnexion</a>
        </p>
        <?php endif; ?>
    </div>
</footer>
<script src="js/scripts.js"></script>
</body>
</html>
