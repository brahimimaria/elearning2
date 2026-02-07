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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription étudiant - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a365d 0%, #2d4a8c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .register-container {
            max-width: 500px;
            width: 100%;
            padding: 2.5rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header h1 {
            font-size: 2rem;
            color: #1a365d;
            margin-bottom: 0.5rem;
        }

        .register-header p {
            color: #4a5568;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2d3748;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4299e1;
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
        }

        .login-button {
            width: 100%;
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.4);
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(66, 153, 225, 0.6);
        }

        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #c53030;
        }

        .success-message {
            background: #c6f6d5;
            color: #2f855a;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #2f855a;
        }

        .back-to-home {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-to-home a {
            color: #4299e1;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-to-home a:hover {
            color: #3182ce;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1><i class="fas fa-user-plus"></i> S'inscrire</h1>
            <p>Créez votre compte pour accéder à la plateforme</p>
        </div>
        <?php if ($error): ?><div class="error-message"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success-message"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="numero_carte">Numéro de carte étudiant</label>
                <input type="text" name="numero_carte" id="numero_carte" required placeholder="Ex: 2024001" value="<?php echo htmlspecialchars($_POST['numero_carte'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="nom">Nom</label>
                <input type="text" name="nom" id="nom" required placeholder="Votre nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="prenom">Prénom</label>
                <input type="text" name="prenom" id="prenom" required placeholder="Votre prénom" value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="annee">Année d'étude</label>
                <input type="number" name="annee" id="annee" required min="1" max="5" value="<?php echo (int)($_POST['annee'] ?? 3); ?>">
            </div>
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" name="email" id="email" required placeholder="exemple@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="username">Identifiant (username)</label>
                <input type="text" name="username" id="username" required placeholder="Choisissez un identifiant" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required minlength="6" placeholder="Au moins 6 caractères">
            </div>
            <button type="submit" class="login-button"><i class="fas fa-arrow-right"></i> S'inscrire</button>
        </form>
        <div class="back-to-home">
            <p>Vous avez un compte? <a href="login.php">Se connecter</a></p>
            <a href="../index.php">← Retour à l'accueil</a>
        </div>
    </div>
</body>
</html>
