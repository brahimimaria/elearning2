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

// Traiter l'ajout, modification et suppression d'un étudiant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $numero_carte = trim($_POST['numero_carte'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $annee = (int)($_POST['annee'] ?? 0);
        
        if (!$username || !$password || !$numero_carte || !$nom || !$prenom || !$email || !$annee) {
            $error = 'Tous les champs sont requis.';
        } elseif (strlen($password) < 6) {
            $error = 'Le mot de passe doit contenir au moins 6 caractères.';
        } else {
            $u = $conn->real_escape_string($username);
            $check = $conn->query("SELECT id FROM users WHERE username = '$u'");
            if ($check && $check->num_rows > 0) {
                $error = 'Ce nom d\'utilisateur existe déjà.';
            } else {
                $nc = $conn->real_escape_string($numero_carte);
                $check2 = $conn->query("SELECT id FROM students WHERE numero_carte = '$nc'");
                if ($check2 && $check2->num_rows > 0) {
                    $error = 'Ce numéro de carte est déjà enregistré.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $nom_esc = $conn->real_escape_string($nom);
                    $prenom_esc = $conn->real_escape_string($prenom);
                    $email_esc = $conn->real_escape_string($email);
                    
                    $conn->query("INSERT INTO users (username, password, role) VALUES ('$u', '$hash', 'student')");
                    $uid = $conn->insert_id;
                    if ($uid) {
                        $conn->query("INSERT INTO students (user_id, numero_carte, nom, prenom, annee, email) VALUES ($uid, '$nc', '$nom_esc', '$prenom_esc', $annee, '$email_esc')");
                        $message = 'Étudiant ajouté avec succès.';
                    }
                }
            }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $numero_carte = trim($_POST['numero_carte'] ?? '');
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $annee = (int)($_POST['annee'] ?? 0);
        
        if (!$numero_carte || !$nom || !$prenom || !$email || !$annee) {
            $error = 'Tous les champs sont requis.';
        } else {
            $nc = $conn->real_escape_string($numero_carte);
            $nom_esc = $conn->real_escape_string($nom);
            $prenom_esc = $conn->real_escape_string($prenom);
            $email_esc = $conn->real_escape_string($email);
            
            // Check if numero_carte already exists for another student
            $checkQuery = "SELECT id FROM students WHERE numero_carte = '$nc' AND user_id != $id";
            $check = $conn->query($checkQuery);
            if ($check && $check->num_rows > 0) {
                $error = 'Ce numéro de carte est déjà enregistré pour un autre étudiant.';
            } else {
                // Check if student exists for this user
                $studentCheck = $conn->query("SELECT id FROM students WHERE user_id = $id");
                if ($studentCheck && $studentCheck->num_rows > 0) {
                    $updateQuery = "UPDATE students SET numero_carte = '$nc', nom = '$nom_esc', prenom = '$prenom_esc', annee = $annee, email = '$email_esc' WHERE user_id = $id";
                    if ($conn->query($updateQuery)) {
                        $message = 'Étudiant modifié avec succès.';
                    } else {
                        $error = 'Erreur lors de la modification de l\'étudiant.';
                    }
                } else {
                    $error = 'Étudiant non trouvé.';
                }
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM course_enrollments WHERE student_id = (SELECT id FROM students WHERE user_id = $id)");
        $conn->query("DELETE FROM students WHERE user_id = $id");
        $conn->query("DELETE FROM users WHERE id = $id");
        $message = 'Étudiant supprimé avec succès.';
    }
}

// Récupérer les données de l'étudiant à modifier si nécessaire
$student_to_edit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT u.id, u.username, s.numero_carte, s.nom, s.prenom, s.annee, s.email FROM users u LEFT JOIN students s ON u.id = s.user_id WHERE u.id = $edit_id AND u.role = 'student'");
    if ($result && $result->num_rows > 0) {
        $student_to_edit = $result->fetch_assoc();
    }
}

// Récupérer les étudiants
$students = [];
$sql = "SELECT u.id, u.username, u.role, s.numero_carte, s.nom, s.prenom, s.annee, s.email FROM users u LEFT JOIN students s ON u.id = s.user_id WHERE u.role = 'student' ORDER BY u.username";
$result = $conn->query($sql);
if ($result) {
    $students = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Étudiants - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main>
    <section class="admin-section">
        <h2>Gestion des Étudiants</h2>
        
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Formulaire d'ajout/modification -->
        <div class="admin-form">
            <h3><?php echo $student_to_edit ? 'Modifier l\'étudiant' : 'Ajouter un nouvel étudiant'; ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $student_to_edit ? 'edit' : 'add'; ?>">
                <?php if ($student_to_edit): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($student_to_edit['id']); ?>">
                <?php endif; ?>
                
                <?php if (!$student_to_edit): ?>
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
                        <input type="text" value="<?php echo htmlspecialchars($student_to_edit['username']); ?>" readonly style="background: #f0f0f0; color: #666;">
                    </div>
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 1rem;">Le mot de passe ne peut pas être modifié depuis cette page.</p>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="numero_carte">Numéro de carte</label>
                        <input type="text" name="numero_carte" id="numero_carte" required placeholder="Ex: 2024001" value="<?php echo $student_to_edit ? htmlspecialchars($student_to_edit['numero_carte'] ?? '') : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" name="nom" id="nom" required placeholder="Nom de famille" value="<?php echo $student_to_edit ? htmlspecialchars($student_to_edit['nom'] ?? '') : ''; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" name="prenom" id="prenom" required placeholder="Prénom" value="<?php echo $student_to_edit ? htmlspecialchars($student_to_edit['prenom'] ?? '') : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="annee">Année d'étude</label>
                        <input type="number" name="annee" id="annee" required min="1" max="5" placeholder="1-5" value="<?php echo $student_to_edit ? htmlspecialchars($student_to_edit['annee'] ?? '') : ''; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" required placeholder="exemple@email.com" value="<?php echo $student_to_edit ? htmlspecialchars($student_to_edit['email'] ?? '') : ''; ?>">
                    </div>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn-add"><?php echo $student_to_edit ? '✓ Modifier l\'étudiant' : '+ Ajouter un étudiant'; ?></button>
                    <?php if ($student_to_edit): ?>
                        <a href="admin_students.php" class="btn-cancel" style="display: inline-flex; align-items: center; padding: 0.75rem 1.5rem; background: #999; color: white; text-decoration: none; border-radius: 6px; border: none; cursor: pointer;">✕ Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Liste des étudiants -->
        <div class="admin-list">
            <h3>Liste des étudiants (<?php echo count($students); ?>)</h3>
            <?php if (empty($students)): ?>
                <p>Aucun étudiant enregistré.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Identifiant</th>
                            <th>Nom</th>
                            <th>Prénom</th>
                            <th>Carte</th>
                            <th>Année</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['username']); ?></td>
                                <td><?php echo htmlspecialchars($student['nom'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['prenom'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['numero_carte'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['annee'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <a href="admin_students.php?edit=<?php echo $student['id']; ?>" class="btn-edit" style="padding: 0.5rem 1rem; background: #4a90e2; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 0.9rem;">Modifier</a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($student['id']); ?>">
                                            <button type="submit" class="btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet étudiant ?');">Supprimer</button>
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
