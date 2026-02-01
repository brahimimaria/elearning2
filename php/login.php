<?php
// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = '$username'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: ../index.php");
            exit();
        } else {
            $error_message = "Identifiants invalides. Veuillez réessayer.";
        }
    } else {
        $error_message = "Utilisateur introuvable. Veuillez vérifier vos informations.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 4rem auto;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h1 {
            font-size: 2rem;
            color: #1a365d;
            margin-bottom: 1rem;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .login-button {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .error-message {
            background-color: #fed7d7;
            color: #c53030;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: 500;
        }

        .back-to-home {
            text-align: center;
            margin-top: 1rem;
        }

        .back-to-home a {
            color: #4299e1;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .back-to-home a:hover {
            color: #3182ce;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Bienvenue</h1>
            <p>Veuillez vous connecter pour accéder à la plateforme E-Learning</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="form-group">
                <label for="username">Identifiant</label>
                <input type="text" name="username" id="username" required 
                       placeholder="Entrez votre identifiant">
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required
                       placeholder="Entrez votre mot de passe">
            </div>

            <button type="submit" class="login-button">Se connecter</button>
        </form>

        <div class="back-to-home">
            <a href="../index.php">← Retour à l'accueil</a>
        </div>
    </div>
</body>
</html>
