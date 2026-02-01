<?php
session_start();
include 'db.php';
$specialty = isset($_GET['specialty']) ? trim($_GET['specialty']) : '';
$sql = "SELECT c.id, c.titre, c.public_cible, c.cle_inscription, c.description, c.status, t.nom AS t_nom, t.prenom AS t_prenom, t.domaine FROM courses c JOIN teachers t ON c.teacher_id = t.id WHERE 1=1";
$params = [];
$types = '';
if ($specialty !== '') {
    $sql .= " AND c.public_cible = ?";
    $params[] = $specialty;
    $types .= 's';
}
$sql .= " ORDER BY c.titre";
if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
$specialties = [];
$r = $conn->query("SELECT DISTINCT public_cible FROM courses ORDER BY public_cible");
if ($r) while ($row = $r->fetch_assoc()) $specialties[] = $row['public_cible'];
$is_php_folder = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cours par spécialité - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .courses-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .course-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; }
        .course-card h3 { margin-bottom: 0.5rem; }
        .course-card a { color: #2b6cb0; text-decoration: none; font-weight: 600; }
        .filter-form { margin-bottom: 1.5rem; display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .filter-form select { max-width: 250px; }
        .status-badge { font-size: 0.85rem; padding: 0.25rem 0.5rem; border-radius: 6px; }
        .status-active { background: #c6f6d5; color: #2f855a; }
        .status-completed { background: #bee3f8; color: #2c5282; }
        .status-upcoming { background: #fefcbf; color: #975a16; }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>
<main>
    <h2>Cours disponibles par spécialité</h2>
    <form class="filter-form" method="GET">
        <label for="specialty">Spécialité / Public ciblé :</label>
        <select name="specialty" id="specialty" onchange="this.form.submit()">
            <option value="">Toutes</option>
            <?php foreach ($specialties as $s): ?>
                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $specialty === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="courses-grid">
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="course-card">
            <h3><a href="course_detail.php?id=<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars($row['titre']); ?></a></h3>
            <p><strong>Public ciblé :</strong> <?php echo htmlspecialchars($row['public_cible']); ?></p>
            <p><strong>Enseignant :</strong> <?php echo htmlspecialchars($row['t_prenom'] . ' ' . $row['t_nom']); ?></p>
            <p><strong>Domaine :</strong> <?php echo htmlspecialchars($row['domaine']); ?></p>
            <?php if ($row['description']): ?><p><?php echo htmlspecialchars(mb_substr($row['description'], 0, 120)); ?>…</p><?php endif; ?>
            <span class="status-badge status-<?php echo strtolower($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
        </div>
        <?php endwhile; ?>
    </div>
    <?php if ($result->num_rows === 0): ?><p>Aucun cours trouvé.</p><?php endif; ?>
</main>
<footer><div class="footer-container"><p>&copy; <?php echo date('Y'); ?> E-Learning.</p></div></footer>
</body>
</html>
