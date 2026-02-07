<?php
session_start();
include 'db.php';

// Vérifier si l'utilisateur est admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$is_php_folder = true;
$message = '';
$error = '';

// Traiter l'ajout, modification et suppression d'un enseignant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        
        if (!$username || !$password || !$email) {
            $error = 'Tous les champs sont requis.';
        } elseif (strlen($password) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        } else {
            $u = $conn->real_escape_string($username);
            $check = $conn->query("SELECT id FROM users WHERE username = '$u'");
            if ($check && $check->num_rows > 0) {
                $error = 'Ce nom d\'utilisateur existe déjà.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $email_esc = $conn->real_escape_string($email);
                $conn->query("INSERT INTO users (username, password, role) VALUES ('$u', '$hash', 'teacher')");
                $uid = $conn->insert_id;
                if ($uid) {
                    $conn->query("INSERT INTO teachers (user_id, email) VALUES ($uid, '$email_esc')");
                    $message = 'Enseignant ajouté avec succès.';
                }
            }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $email = trim($_POST['email'] ?? '');
        
        if (!$email) {
            $error = 'L\'email est requis.';
        } else {
            $email_esc = $conn->real_escape_string($email);
            $updateQuery = "UPDATE teachers SET email = '$email_esc' WHERE user_id = $id";
            if ($conn->query($updateQuery)) {
                $message = 'Enseignant modifié avec succès.';
            } else {
                $error = 'Erreur lors de la modification de l\'enseignant.';
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM teachers WHERE user_id = $id");
        $conn->query("DELETE FROM users WHERE id = $id");
        $message = 'Enseignant supprimé avec succès.';
    }
}

// Récupérer les données de l'enseignant à modifier si nécessaire
$teacher_to_edit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT u.id, u.username, t.email FROM users u LEFT JOIN teachers t ON u.id = t.user_id WHERE u.id = $edit_id AND u.role = 'teacher'");
    if ($result && $result->num_rows > 0) {
        $teacher_to_edit = $result->fetch_assoc();
    }
}

// Récupérer les enseignants
$teachers = [];
$sql = "SELECT u.id, u.username, u.role, t.email FROM users u LEFT JOIN teachers t ON u.id = t.user_id WHERE u.role = 'teacher' ORDER BY u.username";
$result = $conn->query($sql);
if ($result) {
    $teachers = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Enseignants - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main>
    <section class="admin-section">
        <h2>Gestion des Enseignants</h2>
        
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Formulaire d'ajout/modification -->
        <div class="admin-form">
            <h3><?php echo $teacher_to_edit ? 'Modifier l\'enseignant' : 'Ajouter un nouvel enseignant'; ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $teacher_to_edit ? 'edit' : 'add'; ?>">
                <?php if ($teacher_to_edit): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($teacher_to_edit['id']); ?>">
                <?php endif; ?>
                
                <?php if (!$teacher_to_edit): ?>
                    <div class="form-group">
                        <label for="username">Identifiant</label>
                        <input type="text" name="username" id="username" required placeholder="Identifiant unique">
                    </div>
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <input type="password" name="password" id="password" required minlength="6" placeholder="Au moins 6 caractères">
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label>Identifiant</label>
                        <input type="text" value="<?php echo htmlspecialchars($teacher_to_edit['username']); ?>" readonly style="background: #f0f0f0; color: #666;">
                    </div>
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">Le mot de passe ne peut pas être modifié depuis cette page.</p>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" required placeholder="exemple@email.com" value="<?php echo $teacher_to_edit ? htmlspecialchars($teacher_to_edit['email'] ?? '') : ''; ?>">
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn-add"><?php echo $teacher_to_edit ? '✓ Modifier l\'enseignant' : '+ Ajouter un enseignant'; ?></button>
                    <?php if ($teacher_to_edit): ?>
                        <a href="admin_teachers.php" class="btn-cancel" style="display: inline-flex; align-items: center; padding: 0.75rem 1.5rem; background: #999; color: white; text-decoration: none; border-radius: 6px; border: none; cursor: pointer;">✕ Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Liste des enseignants -->
        <div class="admin-list">
            <h3>Liste des enseignants (<?php echo count($teachers); ?>)</h3>
            <?php if (empty($teachers)): ?>
                <p>Aucun enseignant enregistré.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Identifiant</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['id']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['email'] ?? 'N/A'); ?></td>
                                <td><span class="badge-role"><?php echo htmlspecialchars($teacher['role']); ?></span></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <a href="admin_teachers.php?edit=<?php echo $teacher['id']; ?>" class="btn-edit" style="padding: 0.5rem 1rem; background: #4a90e2; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 0.9rem;">Modifier</a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($teacher['id']); ?>">
                                            <button type="submit" class="btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet enseignant ?');">Supprimer</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>
</main>
<footer>
    <div class="footer-container">
        <p>&copy; <?php echo date('Y'); ?> E-Learning.</p>
    </div>
</footer>
</body>
</html>
