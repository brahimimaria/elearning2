<?php
session_start();
include 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';

// Handle form submission for adding new resources (Teachers only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resource'])) {
    $course_id = $_POST['course_id'];
    $title = $_POST['title'];
    $type = $_POST['type'];
    $url_or_path = $_POST['url_or_path'];

    // Validation
    if (!$course_id || !$title || !$type || !$url_or_path) {
        $message = "Tous les champs sont requis";
        $message_type = "error";
    } else {
        // Insert resource
        $sql = "INSERT INTO course_resources (course_id, title, type, url_or_path, upload_date) 
                VALUES ('$course_id', '$title', '$type', '$url_or_path', NOW())";

        if ($conn->query($sql) === TRUE) {
            $message = "Ressource ajoutée avec succès !";
            $message_type = "success";
        } else {
            $message = "Erreur : " . $conn->error;
            $message_type = "error";
        }
    }
}

// Get user role for permissions
$is_teacher = isset($_SESSION['role']) && $_SESSION['role'] === 'instructor';
$is_student = isset($_SESSION['role']) && $_SESSION['role'] === 'student';

// Fetch courses with their resources
if ($is_teacher) {
    // Teachers see only their courses and resources
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT c.id, c.titre, c.description, cr.id as resource_id, cr.title, cr.type, cr.url_or_path, cr.upload_date
            FROM courses c
            LEFT JOIN course_resources cr ON c.id = cr.course_id
            WHERE c.teacher_id = (SELECT id FROM teachers WHERE user_id = $user_id)
            ORDER BY c.titre, cr.upload_date DESC";
} else {
    // Students see resources from courses they're enrolled in
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT c.id, c.titre, c.description, cr.id as resource_id, cr.title, cr.type, cr.url_or_path, cr.upload_date
            FROM courses c
            LEFT JOIN course_resources cr ON c.id = cr.course_id
            LEFT JOIN course_enrollments ce ON c.id = ce.course_id
            LEFT JOIN students s ON ce.student_id = s.id
            WHERE s.user_id = $user_id
            ORDER BY c.titre, cr.upload_date DESC";
}

$result = $conn->query($sql);
$courses_with_resources = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $course_id = $row['id'];
        if (!isset($courses_with_resources[$course_id])) {
            $courses_with_resources[$course_id] = [
                'id' => $course_id,
                'titre' => $row['titre'],
                'description' => $row['description'],
                'resources' => []
            ];
        }
        if ($row['resource_id']) {
            $courses_with_resources[$course_id]['resources'][] = [
                'id' => $row['resource_id'],
                'title' => $row['title'],
                'type' => $row['type'],
                'url_or_path' => $row['url_or_path'],
                'upload_date' => $row['upload_date']
            ];
        }
    }
}

// Get teacher's courses for add form
$teacher_courses = [];
if ($is_teacher) {
    $user_id = $_SESSION['user_id'];
    $sql_courses = "SELECT c.id, c.titre FROM courses c
                    WHERE c.teacher_id = (SELECT id FROM teachers WHERE user_id = $user_id)";
    $courses_result = $conn->query($sql_courses);
    while ($course = $courses_result->fetch_assoc()) {
        $teacher_courses[] = $course;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ressources - Plateforme E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .resources-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .course-resources-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 1rem;
        }

        .course-title {
            font-size: 1.5rem;
            color: #2d3748;
            margin: 0;
        }

        .resources-count {
            background: #e2e8f0;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            color: #4a5568;
        }

        .resources-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .resource-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .resource-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .resource-type {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .resource-type.video {
            background: #fed7d7;
            color: #742a2a;
        }

        .resource-type.document {
            background: #c6f6d5;
            color: #22543d;
        }

        .resource-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin: 0.5rem 0;
            word-break: break-word;
        }

        .resource-link {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 1rem;
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #dbeafe;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .resource-link:hover {
            background: #bfdbfe;
        }

        .resource-date {
            color: #718096;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .no-resources {
            text-align: center;
            padding: 2rem;
            color: #718096;
            font-style: italic;
        }

        .add-resource-form {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .add-resource-form h2 {
            color: white;
            margin-top: 0;
        }

        .add-resource-form .form-group {
            margin-bottom: 1rem;
        }

        .add-resource-form label {
            color: white;
            font-weight: 500;
        }

        .add-resource-form input,
        .add-resource-form select {
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

        .btn-add {
            background: #10b981;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
            margin-top: 0.5rem;
        }

        .btn-add:hover {
            background: #059669;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #718096;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .course-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .resources-list {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php
    $is_php_folder = true;
    include 'includes/header.php';
    ?>

    <main class="resources-container">
        <?php if ($message): ?>
            <div class="<?php echo $message_type === 'success' ? 'success-message' : 'error-message'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Teacher: Add Resource Form -->
        <?php if ($is_teacher && !empty($teacher_courses)): ?>
        <div class="add-resource-form">
            <h2><i class="fas fa-plus"></i> Ajouter une Ressource</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_id">Cours *</label>
                        <select name="course_id" id="course_id" required>
                            <option value="">-- Sélectionner un cours --</option>
                            <?php foreach ($teacher_courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['titre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="title">Titre *</label>
                        <input type="text" name="title" id="title" required
                               placeholder="Ex: Leçon 1 - Introduction">
                    </div>

                    <div class="form-group">
                        <label for="type">Type *</label>
                        <select name="type" id="type" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="video">Vidéo</option>
                            <option value="document">Document</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="url_or_path">Lien/Chemin *</label>
                        <input type="text" name="url_or_path" id="url_or_path" required
                               placeholder="https://... ou chemin du fichier">
                    </div>
                </div>
                <button type="submit" name="add_resource" class="btn-add">
                    <i class="fas fa-upload"></i> Ajouter Ressource
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Resources Display -->
        <?php if (empty($courses_with_resources)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h2>Aucune ressource trouvée</h2>
                <p>Les ressources des cours apparaîtront ici.</p>
            </div>
        <?php else: ?>
            <?php foreach ($courses_with_resources as $course): ?>
            <div class="course-resources-section">
                <div class="course-header">
                    <h2 class="course-title">
                        <i class="fas fa-book"></i> <?php echo htmlspecialchars($course['titre']); ?>
                    </h2>
                    <span class="resources-count">
                        <?php echo count($course['resources']); ?> ressource(s)
                    </span>
                </div>

                <?php if (empty($course['resources'])): ?>
                    <div class="no-resources">
                        <i class="fas fa-inbox"></i> Aucune ressource pour ce cours
                    </div>
                <?php else: ?>
                    <div class="resources-list">
                        <?php foreach ($course['resources'] as $resource): ?>
                        <div class="resource-card">
                            <span class="resource-type <?php echo $resource['type']; ?>">
                                <?php 
                                if ($resource['type'] === 'video') {
                                    echo '<i class="fas fa-video"></i> Vidéo';
                                } else {
                                    echo '<i class="fas fa-file-pdf"></i> Document';
                                }
                                ?>
                            </span>
                            <h3 class="resource-title">
                                <?php echo htmlspecialchars($resource['title']); ?>
                            </h3>
                            <a href="<?php echo htmlspecialchars($resource['url_or_path']); ?>" 
                               target="_blank" class="resource-link">
                                <i class="fas fa-external-link-alt"></i> Accéder à la ressource
                            </a>
                            <div class="resource-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d/m/Y', strtotime($resource['upload_date'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <style>
        main.resources-container {
            min-height: calc(100vh - 200px);
        }
    </style>
</body>
</html>
                            <select name="type" id="type" required>
                                <option value="Video">Video</option>
                                <option value="PDF">PDF</option>
                                <option value="Book">Book</option>
                                <option value="Article">Article</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="upload_date">Date</label>
                            <input type="date" name="upload_date" id="upload_date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="author">Author/Source</label>
                            <input type="text" name="author" id="author" required
                                   placeholder="Enter author name">
                        </div>

                        <div class="form-group">
                            <button type="submit" name="add_resource" class="button">Add Resource</button>
                        </div>
                    </div>
                </form>
            </div>
         

            <div class="resources-list">
                <h2>Library Collection</h2>
                
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Date Added</th>
                            <th>Author</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td>
                                    <span class="type-badge">
                                        <?php echo htmlspecialchars($row['type']); ?>
                                    </span>
                                </td>
                                <td class="date-cell">
                                    <?php echo date('F j, Y', strtotime($row['upload_date'])); ?>
                                </td>
                                <td class="author-cell"><?php echo htmlspecialchars($row['author']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer>
        <div class="footer-container">
            <p>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['role']); ?></strong></p>
        </div>
    </footer>
</body>
</html>
