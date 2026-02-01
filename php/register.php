<?php
session_start();
include 'db.php';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $numero_carte = trim($_POST['numero_carte'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $annee = (int)($_POST['annee'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    if (!$username || !$password || !$numero_carte || !$nom || !$prenom || !$annee || !$email) {
        $error = 'Tous les champs sont requis.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        $r = registerStudent($username, $password, $numero_carte, $nom, $prenom, $annee, $email);
        if ($r['ok']) {
            $success = 'Compte créé. Vous pouvez vous connecter.';
            header('Refresh: 2; url=login.php');
        } else {
            $error = $r['msg'];
        }
    }
}
$is_php_folder = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription étudiant - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .register-container { max-width: 500px; margin: 2rem auto; padding: 2rem; background: rgba(255,255,255,0.95); border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
        .register-header { text-align: center; margin-bottom: 1.5rem; }
        .register-header h1 { font-size: 1.8rem; color: #1a365d; }
        .form-group { margin-bottom: 1rem; }
        .login-button { background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%); color: white; padding: 0.75rem; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500; cursor: pointer; width: 100%; }
        .error-message { background: #fed7d7; color: #c53030; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .success-message { background: #c6f6d5; color: #2f855a; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .back-to-home { text-align: center; margin-top: 1rem; }
        .back-to-home a { color: #4299e1; text-decoration: none; }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<main>
    <div class="register-container">
        <div class="register-header">
            <h1>Créer un compte étudiant</h1>
            <p>Chaque étudiant peut créer un compte et accéder aux cours avec la clé d'inscription.</p>
        </div>
        <?php if ($error): ?><div class="error-message"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success-message"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="numero_carte">Numéro de la carte</label>
                <input type="text" name="numero_carte" id="numero_carte" required placeholder="Numéro de carte" value="<?php echo htmlspecialchars($_POST['numero_carte'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="nom">Nom</label>
                <input type="text" name="nom" id="nom" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="prenom">Prénom</label>
                <input type="text" name="prenom" id="prenom" required value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="annee">Année</label>
                <input type="number" name="annee" id="annee" required min="1" max="5" value="<?php echo (int)($_POST['annee'] ?? 3); ?>">
            </div>
            <div class="form-group">
                <label for="email">Adresse Email</label>
                <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="username">Identifiant (username)</label>
                <input type="text" name="username" id="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required minlength="6">
            </div>
            <button type="submit" class="login-button">S'inscrire</button>
        </form>
        <div class="back-to-home"><a href="../index.php">← Retour à l'accueil</a></div>
    </div>
</main>
<footer><div class="footer-container"><p>&copy; <?php echo date('Y'); ?> E-Learning.</p></div></footer>
</body>
</html>
