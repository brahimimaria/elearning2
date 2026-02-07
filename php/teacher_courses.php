<?php
session_start();
include 'db.php';

// Définir la variable pour le header
$is_php_folder = true;

// Vérifier que c'est un enseignant
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
    header('Location: login.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Récupérer l'ID du professeur
$teacher = $conn->query("SELECT id FROM teachers WHERE user_id = $user_id")->fetch_assoc();
if (!$teacher) {
    header('Location: index.php');
    exit();
}
$teacher_id = $teacher['id'];

// AJOUTER UN COURS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_course'])) {
    $titre = $conn->real_escape_string(trim($_POST['titre']));
    $public_cible = $conn->real_escape_string(trim($_POST['public_cible']));
    $cle_inscription = bin2hex(random_bytes(6));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $status = $_POST['status'];

    if (!$titre || !$public_cible) {
        $message = "Tous les champs obligatoires doivent être remplis";
        $message_type = "error";
    } else {
        $sql = "INSERT INTO courses (titre, teacher_id, public_cible, cle_inscription, description, status) 
                VALUES ('$titre', $teacher_id, '$public_cible', '$cle_inscription', '$description', '$status')";
        
        if ($conn->query($sql)) {
            $message = "Cours créé avec succès! Clé: " . $cle_inscription;
            $message_type = "success";
        } else {
            $message = "Erreur: " . $conn->error;
            $message_type = "error";
        }
    }
}

// MODIFIER UN COURS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_course'])) {
    $course_id = (int)$_POST['course_id'];
    $titre = $conn->real_escape_string(trim($_POST['titre']));
    $public_cible = $conn->real_escape_string(trim($_POST['public_cible']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $status = $_POST['status'];

    if (!$titre || !$public_cible) {
        $message = "Tous les champs obligatoires doivent être remplis";
        $message_type = "error";
    } else {
        $sql = "UPDATE courses SET titre='$titre', public_cible='$public_cible', description='$description', status='$status' 
                WHERE id=$course_id AND teacher_id=$teacher_id";
        
        if ($conn->query($sql)) {
            $message = "Cours mis à jour avec succès!";
            $message_type = "success";
        } else {
            $message = "Erreur: " . $conn->error;
            $message_type = "error";
        }
    }
}

// SUPPRIMER UN COURS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_course'])) {
    $course_id = (int)$_POST['course_id'];
    
    // Vérifier que c'est le cours de ce professeur
    $check = $conn->query("SELECT id FROM courses WHERE id=$course_id AND teacher_id=$teacher_id");
    if ($check->num_rows > 0) {
        $conn->query("DELETE FROM course_enrollments WHERE course_id=$course_id");
        $conn->query("DELETE FROM course_resources WHERE course_id=$course_id");
        $conn->query("DELETE FROM forum_posts WHERE course_id=$course_id");
        $conn->query("DELETE FROM evaluations WHERE course_id=$course_id");
        $conn->query("DELETE FROM courses WHERE id=$course_id");
        $message = "Cours supprimé avec succès!";
        $message_type = "success";
    }
}

// SUPPRIMER UN ÉTUDIANT D'UN COURS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_student'])) {
    $course_id = (int)$_POST['course_id'];
    $student_id = (int)$_POST['student_id'];
    
    // Vérifier que c'est le cours de ce professeur
    $check = $conn->query("SELECT id FROM courses WHERE id=$course_id AND teacher_id=$teacher_id");
    if ($check->num_rows > 0) {
        // Supprimer l'étudiant du cours
        $sql = "DELETE FROM course_enrollments WHERE course_id=$course_id AND student_id=$student_id";
        if ($conn->query($sql)) {
            $message = "Étudiant supprimé du cours avec succès!";
            $message_type = "success";
        } else {
            $message = "Erreur lors de la suppression de l'étudiant";
            $message_type = "error";
        }
    } else {
        $message = "Erreur: Cours non trouvé ou non autorisé";
        $message_type = "error";
    }
}

// AJOUTER UNE RESSOURCE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resource'])) {
    $course_id = (int)$_POST['course_id'];
    $title = $conn->real_escape_string(trim($_POST['resource_title']));
    $type = $conn->real_escape_string($_POST['resource_type']);
    $url_or_path = '';

    // Vérifier que c'est le cours de ce professeur
    $check = $conn->query("SELECT id FROM courses WHERE id=$course_id AND teacher_id=$teacher_id");
    if ($check->num_rows === 0) {
        $message = "Erreur: Cours non trouvé";
        $message_type = "error";
    } elseif (!$title || !$type) {
        $message = "Titre et type sont requis";
        $message_type = "error";
    } else {
        // Traiter l'upload de fichier ou l'URL
        if ($type === 'video' && isset($_FILES['resource_file']) && $_FILES['resource_file']['size'] > 0) {
            // Upload de fichier vidéo
            $uploads_dir = '../uploads/videos/';
            if (!is_dir($uploads_dir)) {
                mkdir($uploads_dir, 0755, true);
            }

            $file = $_FILES['resource_file'];
            $allowed_types = ['video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $message = "Type de fichier non autorisé. Formats acceptés: MP4, MPEG, MOV, AVI, WebM";
                $message_type = "error";
            } elseif ($file['size'] > 500000000) { // 500MB max
                $message = "Fichier trop volumineux (max 500MB)";
                $message_type = "error";
            } else {
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
                $file_path = $uploads_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $url_or_path = 'uploads/videos/' . $filename;
                } else {
                    $message = "Erreur lors de l'upload du fichier";
                    $message_type = "error";
                }
            }
        } else {
            // URL ou pas de fichier
            $url_or_path = $conn->real_escape_string(trim($_POST['resource_url']));
            if (!$url_or_path) {
                $message = "Veuillez fournir une URL ou uploadez un fichier";
                $message_type = "error";
            }
        }

        if ($url_or_path) {
            $sql = "INSERT INTO course_resources (course_id, title, type, url_or_path, upload_date) 
                    VALUES ($course_id, '$title', '$type', '$url_or_path', NOW())";
            
            if ($conn->query($sql)) {
                $message = "Ressource ajoutée avec succès!";
                $message_type = "success";
            } else {
                $message = "Erreur: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}

// SUPPRIMER UNE RESSOURCE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_resource'])) {
    $resource_id = (int)$_POST['resource_id'];
    $conn->query("DELETE FROM course_resources WHERE id=$resource_id");
    $message = "Ressource supprimée!";
    $message_type = "success";
}

// Récupérer tous les cours du professeur
$courses = $conn->query("SELECT * FROM courses WHERE teacher_id=$teacher_id ORDER BY titre");
$courses_data = [];
while ($course = $courses->fetch_assoc()) {
    $resources = $conn->query("SELECT * FROM course_resources WHERE course_id=" . $course['id']);
    $course['resources'] = $resources->fetch_all(MYSQLI_ASSOC);
    
    // Compter les étudiants inscrits
    $enrolled_count = $conn->query("SELECT COUNT(*) as count FROM course_enrollments WHERE course_id=" . $course['id']);
    $enrolled_result = $enrolled_count->fetch_assoc();
    $course['enrolled_count'] = $enrolled_result['count'] ?? 0;
    
    // Récupérer la liste des étudiants inscrits
    $enrolled_students = $conn->query("SELECT s.id, s.nom, s.prenom, s.numero_carte, s.email FROM students s 
                                       INNER JOIN course_enrollments ce ON s.id = ce.student_id 
                                       WHERE ce.course_id=" . $course['id'] . " 
                                       ORDER BY s.nom, s.prenom");
    $course['enrolled_students'] = $enrolled_students->fetch_all(MYSQLI_ASSOC);
    
    $courses_data[] = $course;
}

$is_php_folder = true;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Cours - Enseignant</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .teacher-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .form-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .form-section h2 {
            color: white;
            margin-top: 0;
        }

        .form-section .form-group {
            margin-bottom: 1rem;
        }

        .form-section label {
            color: white;
            font-weight: 500;
        }

        .form-section input,
        .form-section select,
        .form-section textarea {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.75rem;
            border-radius: 6px;
            width: 100%;
            font-size: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .btn-add, .btn-edit, .btn-delete {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-add {
            background: #10b981;
            color: white;
            margin-top: 0.5rem;
        }

        .btn-add:hover {
            background: #059669;
        }

        .btn-edit {
            background: #3b82f6;
            color: white;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }

        .course-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 1rem;
        }

        .course-title {
            font-size: 1.3rem;
            color: #2d3748;
        }

        .course-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
            font-size: 0.9rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-completed {
            background: #dbeafe;
            color: #0c4a6e;
        }

        .status-upcoming {
            background: #fef3c7;
            color: #92400e;
        }

        .resources-section {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .resources-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .resource-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .resource-type {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #e2e8f0;
        }

        .resource-type.video {
            background: #fed7d7;
            color: #742a2a;
        }

        .resource-type.document {
            background: #c6f6d5;
            color: #22543d;
        }

        .add-resource-form {
            background: #f0f9ff;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border: 1px dashed #3b82f6;
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .success-message, .error-message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .success-message {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .course-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .course-actions {
                flex-direction: column;
            }

            .btn-edit, .btn-delete {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="teacher-container">
        <h1><i class="fas fa-graduation-cap"></i> Mes Cours - Enseignant</h1>

        <!-- Statistiques de l'enseignant -->
        <div style="display: flex; gap: 1rem; margin: 2rem 0; flex-wrap: wrap;">
            <?php
                $total_courses = count($courses_data);
                $total_students = 0;
                foreach ($courses_data as $course) {
                    $total_students += count($course['enrolled_students']);
                }
            ?>
            <div style="flex: 1; min-width: 200px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 8px; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <div style="font-size: 2.5rem;"><i class="fas fa-book"></i></div>
                <div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">Mes Cours</div>
                    <div style="font-size: 2rem; font-weight: bold;"><?php echo $total_courses; ?></div>
                </div>
            </div>
            <div style="flex: 1; min-width: 200px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 8px; display: flex; align-items: center; gap: 1rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                <div style="font-size: 2.5rem;"><i class="fas fa-users"></i></div>
                <div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">Mes Étudiants</div>
                    <div style="font-size: 2rem; font-weight: bold;"><?php echo $total_students; ?></div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- SECTION: AJOUTER UN COURS -->
        <div class="form-section">
            <h2><i class="fas fa-plus"></i> Créer un Nouveau Cours</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="titre">Titre du cours *</label>
                        <input type="text" name="titre" id="titre" required 
                               placeholder="Ex: Programmation Web">
                    </div>

                    <div class="form-group">
                        <label for="public_cible">Public ciblé *</label>
                        <input type="text" name="public_cible" id="public_cible" required
                               placeholder="Ex: L2 Informatique">
                    </div>

                    <div class="form-group">
                        <label for="status">Statut</label>
                        <select name="status" id="status">
                            <option value="Active">Actif</option>
                            <option value="Upcoming">À venir</option>
                            <option value="Completed">Terminé</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" placeholder="Décrivez votre cours..."
                              style="min-height: 100px;"></textarea>
                </div>

                <button type="submit" name="add_course" class="btn-add">
                    <i class="fas fa-plus-circle"></i> Créer le Cours
                </button>
            </form>
        </div>

        <!-- SECTION: MES COURS -->
        <h2><i class="fas fa-book-open"></i> Mes Cours</h2>

        <?php if (empty($courses_data)): ?>
            <div style="text-align: center; padding: 2rem; color: #718096;">
                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                <p>Vous n'avez aucun cours encore. Créez-en un ci-dessus!</p>
            </div>
        <?php else: ?>
            <?php foreach ($courses_data as $course): ?>
            <div class="course-card">
                <!-- En-tête du cours -->
                <div class="course-header">
                    <div>
                        <h3 class="course-title"><?php echo htmlspecialchars($course['titre']); ?></h3>
                        <span class="status-badge status-<?php echo strtolower($course['status']); ?>">
                            <?php echo $course['status']; ?>
                        </span>
                    </div>
                </div>

                <!-- Métadonnées du cours -->
                <div class="course-meta">
                    <div class="meta-item">
                        <i class="fas fa-user-check"></i>
                        <span><strong>Inscrits:</strong> <?php echo $course['enrolled_count']; ?> étudiant(s)</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-users"></i>
                        <span><strong>Public:</strong> <?php echo htmlspecialchars($course['public_cible']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-key"></i>
                        <span><strong>Clé:</strong> <?php echo htmlspecialchars($course['cle_inscription']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><strong>Créé:</strong> <?php echo date('d/m/Y', strtotime($course['created_at'])); ?></span>
                    </div>
                </div>

                <?php if ($course['description']): ?>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($course['description']); ?></p>
                <?php endif; ?>

                <!-- Section: Liste des Étudiants Inscrits -->
                <div class="students-section" style="margin: 2rem 0; padding: 1.5rem; background: #f7fafc; border-radius: 8px; border-left: 4px solid #667eea;">
                    <h4><i class="fas fa-users"></i> Étudiants Inscrits (<?php echo count($course['enrolled_students']); ?>)</h4>
                    
                    <?php if (!empty($course['enrolled_students'])): ?>
                        <table style="width: 100%; margin-top: 1rem; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #edf2f7; border-bottom: 2px solid #cbd5e0;">
                                    <th style="padding: 0.8rem; text-align: left; color: #2d3748; font-weight: 600;">Nom</th>
                                    <th style="padding: 0.8rem; text-align: left; color: #2d3748; font-weight: 600;">Prénom</th>
                                    <th style="padding: 0.8rem; text-align: left; color: #2d3748; font-weight: 600;">N° Carte</th>
                                    <th style="padding: 0.8rem; text-align: left; color: #2d3748; font-weight: 600;">Email</th>
                                    <th style="padding: 0.8rem; text-align: center; color: #2d3748; font-weight: 600; width: 100px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($course['enrolled_students'] as $student): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 0.8rem; color: #2d3748;"><?php echo htmlspecialchars($student['nom']); ?></td>
                                    <td style="padding: 0.8rem; color: #2d3748;"><?php echo htmlspecialchars($student['prenom']); ?></td>
                                    <td style="padding: 0.8rem; color: #2d3748;"><code style="background: #edf2f7; padding: 0.2rem 0.5rem; border-radius: 4px;"><?php echo htmlspecialchars($student['numero_carte']); ?></code></td>
                                    <td style="padding: 0.8rem; color: #2d3748;">
                                        <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>" style="color: #667eea; text-decoration: none;">
                                            <?php echo htmlspecialchars($student['email']); ?>
                                        </a>
                                    </td>
                                    <td style="padding: 0.8rem; text-align: center;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="remove_student" class="btn-delete" 
                                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet étudiant du cours?')"
                                                    style="padding: 0.4rem 0.8rem; background: #fc5c65; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                                                <i class="fas fa-trash-alt"></i> Supprimer
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #718096; font-style: italic; margin-top: 1rem;">Aucun étudiant inscrit pour le moment.</p>
                    <?php endif; ?>
                </div>

                <!-- Section: Supports/Ressources -->
                <div class="resources-section">
                    <h4><i class="fas fa-file-upload"></i> Supports (<?php echo count($course['resources']); ?>)</h4>
                    
                    <?php if (!empty($course['resources'])): ?>
                        <div class="resources-list">
                            <?php foreach ($course['resources'] as $res): ?>
                            <div class="resource-item">
                                <div>
                                    <span class="resource-type <?php echo $res['type']; ?>">
                                        <?php echo strtoupper($res['type']); ?>
                                    </span>
                                    <strong><?php echo htmlspecialchars($res['title']); ?></strong>
                                </div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="resource_id" value="<?php echo $res['id']; ?>">
                                    <button type="submit" name="delete_resource" class="btn-delete" 
                                            onclick="return confirm('Supprimer cette ressource?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #718096; font-style: italic;">Aucune ressource pour le moment.</p>
                    <?php endif; ?>

                    <!-- Formulaire: Ajouter une ressource -->
                    <div class="add-resource-form">
                        <h5><i class="fas fa-plus"></i> Ajouter une Ressource</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            
                            <div class="form-group" style="margin-bottom: 0.8rem;">
                                <label style="color: #2d3748; font-weight: 500;">Titre *</label>
                                <input type="text" name="resource_title" placeholder="Titre de la ressource" required style="margin-top: 0.3rem;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0.8rem;">
                                <label style="color: #2d3748; font-weight: 500;">Type *</label>
                                <select name="resource_type" id="resourceType_<?php echo $course['id']; ?>" required style="margin-top: 0.3rem;" onchange="toggleFileUpload(this, <?php echo $course['id']; ?>)">
                                    <option value="">-- Sélectionnez --</option>
                                    <option value="video">Vidéo</option>
                                    <option value="document">Document (PDF)</option>
                                    <option value="image">Image</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0.8rem;" id="urlField_<?php echo $course['id']; ?>">
                                <label style="color: #2d3748; font-weight: 500;">URL</label>
                                <input type="text" name="resource_url" placeholder="Lien externe (optionnel si upload)" style="margin-top: 0.3rem;">
                            </div>

                            <div class="form-group" style="margin-bottom: 0.8rem; display: none;" id="fileField_<?php echo $course['id']; ?>">
                                <label style="color: #2d3748; font-weight: 500;">Upload Vidéo (MP4, WebM, etc.)</label>
                                <input type="file" name="resource_file" accept="video/*" style="margin-top: 0.3rem; padding: 0.5rem; border: 2px dashed #667eea; border-radius: 6px;">
                                <small style="color: #718096; display: block; margin-top: 0.3rem;">Max 500MB • Formats: MP4, WebM, MOV, AVI</small>
                            </div>

                            <button type="submit" name="add_resource" class="btn-add" style="margin: 0; width: 100%;">
                                <i class="fas fa-upload"></i> Ajouter la Ressource
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Actions du cours -->
                <div class="course-actions">
                    <button type="button" class="btn-edit" onclick="editCourse(<?php echo $course['id']; ?>)">
                        <i class="fas fa-edit"></i> Modifier
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                        <button type="submit" name="delete_course" class="btn-delete"
                                onclick="return confirm('Supprimer ce cours? Cela supprimera aussi tous les supports et évaluations.')">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <!-- MODALE D'ÉDITION -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2><i class="fas fa-edit"></i> Modifier le Cours</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="course_id" id="editCourseId">
                <input type="hidden" name="edit_course" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label for="editTitre">Titre du cours *</label>
                        <input type="text" name="titre" id="editTitre" required>
                    </div>

                    <div class="form-group">
                        <label for="editPublicCible">Public ciblé *</label>
                        <input type="text" name="public_cible" id="editPublicCible" required>
                    </div>

                    <div class="form-group">
                        <label for="editStatus">Statut</label>
                        <select name="status" id="editStatus">
                            <option value="Active">Actif</option>
                            <option value="Upcoming">À venir</option>
                            <option value="Completed">Terminé</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="editDescription">Description</label>
                    <textarea name="description" id="editDescription" 
                              style="min-height: 100px;"></textarea>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn-add" style="margin: 0;">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <button type="button" class="btn-delete" style="margin: 0; background: #6b7280;" 
                            onclick="closeEditModal()">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .modal-content h2 {
            margin-top: 0;
            color: #2d3748;
            margin-bottom: 1.5rem;
        }

        .close {
            color: #718096;
            float: right;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #2d3748;
        }

        .modal-content .form-group {
            margin-bottom: 1rem;
        }

        .modal-content label {
            color: #2d3748;
            font-weight: 600;
            display: block;
            margin-bottom: 0.5rem;
        }

        .modal-content input,
        .modal-content select,
        .modal-content textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
        }

        .modal-content input:focus,
        .modal-content select:focus,
        .modal-content textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
            }
        }
    </style>

    <script>
        // Récupérer les données des cours
        const coursesData = <?php echo json_encode($courses_data); ?>;

        function editCourse(courseId) {
            const course = coursesData.find(c => c.id === courseId);
            if (!course) return;

            document.getElementById('editCourseId').value = course.id;
            document.getElementById('editTitre').value = course.titre;
            document.getElementById('editPublicCible').value = course.public_cible;
            document.getElementById('editStatus').value = course.status;
            document.getElementById('editDescription').value = course.description || '';

            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Afficher/Masquer le champ upload fichier selon le type
        function toggleFileUpload(selectElement, courseId) {
            const fileField = document.getElementById('fileField_' + courseId);
            const urlField = document.getElementById('urlField_' + courseId);
            
            if (selectElement.value === 'video') {
                fileField.style.display = 'block';
                urlField.style.display = 'none';
                urlField.querySelector('input').removeAttribute('required');
            } else {
                fileField.style.display = 'none';
                urlField.style.display = 'block';
                urlField.querySelector('input').setAttribute('required', 'required');
            }
        }

        // Fermer la modale en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
