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

// Traiter l'ajout, modification et suppression d'un cours
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $titre = trim($_POST['titre'] ?? '');
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $public_cible = trim($_POST['public_cible'] ?? '');
        $cle_inscription = trim($_POST['cle_inscription'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        
        if (!$titre || !$teacher_id || !$public_cible || !$cle_inscription) {
            $error = 'Tous les champs requis doivent être remplis.';
        } else {
            $cle_check = $conn->query("SELECT id FROM courses WHERE cle_inscription = '" . $conn->real_escape_string($cle_inscription) . "'");
            if ($cle_check && $cle_check->num_rows > 0) {
                $error = 'Cette clé d\'inscription existe déjà.';
            } else {
                $titre_esc = $conn->real_escape_string($titre);
                $public_esc = $conn->real_escape_string($public_cible);
                $cle_esc = $conn->real_escape_string($cle_inscription);
                $desc_esc = $conn->real_escape_string($description);
                
                if ($conn->query("INSERT INTO courses (titre, teacher_id, public_cible, cle_inscription, description, status) VALUES ('$titre_esc', $teacher_id, '$public_esc', '$cle_esc', '$desc_esc', '$status')")) {
                    $message = 'Cours ajouté avec succès.';
                } else {
                    $error = 'Erreur lors de l\'ajout du cours.';
                }
            }
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $titre = trim($_POST['titre'] ?? '');
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $public_cible = trim($_POST['public_cible'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        
        if (!$titre || !$teacher_id || !$public_cible) {
            $error = 'Tous les champs requis doivent être remplis.';
        } else {
            $titre_esc = $conn->real_escape_string($titre);
            $public_esc = $conn->real_escape_string($public_cible);
            $desc_esc = $conn->real_escape_string($description);
            
            $updateQuery = "UPDATE courses SET titre = '$titre_esc', teacher_id = $teacher_id, public_cible = '$public_esc', description = '$desc_esc', status = '$status' WHERE id = $id";
            if ($conn->query($updateQuery)) {
                $message = 'Cours modifié avec succès.';
            } else {
                $error = 'Erreur lors de la modification du cours.';
            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if ($conn->query("DELETE FROM courses WHERE id = $id")) {
            $message = 'Cours supprimé avec succès.';
        } else {
            $error = 'Erreur lors de la suppression du cours.';
        }
    }
}

// Récupérer les données du cours à modifier si nécessaire
$course_to_edit = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT c.id, c.titre, c.teacher_id, c.public_cible, c.cle_inscription, c.description, c.status FROM courses c WHERE c.id = $edit_id");
    if ($result && $result->num_rows > 0) {
        $course_to_edit = $result->fetch_assoc();
    }
}

// Récupérer les enseignants pour le formulaire
$teachers = [];
$sql_teachers = "SELECT id, nom, prenom FROM teachers ORDER BY nom";
$result_teachers = $conn->query($sql_teachers);
if ($result_teachers) {
    $teachers = $result_teachers->fetch_all(MYSQLI_ASSOC);
}

// Récupérer tous les cours
$courses = [];
$sql = "SELECT c.id, c.titre, c.public_cible, c.cle_inscription, c.description, c.status, t.nom, t.prenom FROM courses c JOIN teachers t ON c.teacher_id = t.id ORDER BY c.titre";
$result = $conn->query($sql);
if ($result) {
    $courses = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Cours - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main>
    <section class="admin-section">
        <h2>Gestion des Cours</h2>
        
        <?php if ($message): ?>
            <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Formulaire d'ajout/modification -->
        <div class="admin-form">
            <h3><i class="fas fa-plus"></i> <?php echo $course_to_edit ? 'Modifier le cours' : 'Ajouter un nouveau cours'; ?></h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?php echo $course_to_edit ? 'edit' : 'add'; ?>">
                <?php if ($course_to_edit): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($course_to_edit['id']); ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="titre">Titre du cours *</label>
                        <input type="text" name="titre" id="titre" required placeholder="Ex: Base de données" value="<?php echo $course_to_edit ? htmlspecialchars($course_to_edit['titre']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="teacher_id">Enseignant responsable *</label>
                        <select name="teacher_id" id="teacher_id" required>
                            <option value="">-- Sélectionner un enseignant --</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo ($course_to_edit && $course_to_edit['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['nom'] . ' ' . $teacher['prenom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="public_cible">Public ciblé *</label>
                        <input type="text" name="public_cible" id="public_cible" required placeholder="Ex: L3 Informatique" value="<?php echo $course_to_edit ? htmlspecialchars($course_to_edit['public_cible']) : ''; ?>">
                    </div>
                    <?php if (!$course_to_edit): ?>
                        <div class="form-group">
                            <label for="cle_inscription">Clé d'inscription *</label>
                            <input type="text" name="cle_inscription" id="cle_inscription" required placeholder="Ex: DB2024" maxlength="50">
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Clé d'inscription</label>
                            <input type="text" value="<?php echo htmlspecialchars($course_to_edit['cle_inscription']); ?>" readonly style="background: #f0f0f0; color: #666;">
                            <small style="color: #666;">La clé d'inscription ne peut pas être modifiée.</small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Statut</label>
                        <select name="status" id="status">
                            <option value="Active" <?php echo (!$course_to_edit || $course_to_edit['status'] == 'Active') ? 'selected' : ''; ?>>Actif</option>
                            <option value="Upcoming" <?php echo ($course_to_edit && $course_to_edit['status'] == 'Upcoming') ? 'selected' : ''; ?>>À venir</option>
                            <option value="Completed" <?php echo ($course_to_edit && $course_to_edit['status'] == 'Completed') ? 'selected' : ''; ?>>Complété</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description du cours</label>
                    <textarea name="description" id="description" rows="4" placeholder="Décrivez le contenu et les objectifs du cours..."><?php echo $course_to_edit ? htmlspecialchars($course_to_edit['description']) : ''; ?></textarea>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn-add"><i class="fas fa-plus"></i> <?php echo $course_to_edit ? '✓ Modifier le cours' : 'Ajouter un cours'; ?></button>
                    <?php if ($course_to_edit): ?>
                        <a href="admin_courses.php" class="btn-cancel" style="display: inline-flex; align-items: center; padding: 0.75rem 1.5rem; background: #999; color: white; text-decoration: none; border-radius: 6px; border: none; cursor: pointer;">✕ Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Liste des cours -->
        <div class="admin-list">
            <h3><i class="fas fa-book"></i> Liste des cours (<?php echo count($courses); ?>)</h3>
            <?php if (empty($courses)): ?>
                <p>Aucun cours enregistré.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Enseignant</th>
                            <th>Public ciblé</th>
                            <th>Clé inscription</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($course['titre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($course['nom'] . ' ' . $course['prenom']); ?></td>
                                <td><?php echo htmlspecialchars($course['public_cible']); ?></td>
                                <td><code><?php echo htmlspecialchars($course['cle_inscription']); ?></code></td>
                                <td>
                                    <?php 
                                    $status_class = 'badge-status-' . strtolower($course['status']);
                                    $status_text = ['Active' => 'Actif', 'Upcoming' => 'À venir', 'Completed' => 'Complété'][$course['status']] ?? $course['status'];
                                    ?>
                                    <span class="badge-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <a href="admin_courses.php?edit=<?php echo $course['id']; ?>" class="btn-edit" style="padding: 0.5rem 1rem; background: #4a90e2; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 0.9rem;">Modifier</a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" class="btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce cours ?');"><i class="fas fa-trash"></i> Supprimer</button>
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
<script src="../js/scripts.js"></script>
</body>
</html>
