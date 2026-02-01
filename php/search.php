<?php
session_start();
include 'db.php';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = ['courses' => [], 'teachers' => [], 'resources' => []];
if ($q !== '') {
    $esc = $conn->real_escape_string($q);
    $like = "%{$esc}%";
    $stmt = $conn->prepare("SELECT c.id, c.titre, c.public_cible, c.cle_inscription, t.nom AS t_nom, t.prenom AS t_prenom FROM courses c JOIN teachers t ON c.teacher_id = t.id WHERE c.titre LIKE ? OR c.public_cible LIKE ? OR c.description LIKE ?");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $results['courses'][] = $row;
    $stmt->close();
    $stmt = $conn->prepare("SELECT id, nom, prenom, domaine, grade, email FROM teachers WHERE nom LIKE ? OR prenom LIKE ? OR domaine LIKE ? OR email LIKE ?");
    $stmt->bind_param("ssss", $like, $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $results['teachers'][] = $row;
    $stmt->close();
    $r = @$conn->query("SELECT cr.id, cr.title, cr.type, c.titre AS course_titre FROM course_resources cr JOIN courses c ON cr.course_id = c.id WHERE cr.title LIKE '%$esc%' LIMIT 20");
    if ($r) while ($row = $r->fetch_assoc()) $results['resources'][] = $row;
}
$is_php_folder = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche - E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include 'includes/header.php'; ?>
<main>
    <h2>Résultats pour « <?php echo htmlspecialchars($q); ?> »</h2>
    <?php if ($q === ''): ?>
        <p>Utilisez la barre de recherche pour trouver des cours, enseignants ou ressources.</p>
    <?php else: ?>
        <?php if (!empty($results['courses'])): ?>
        <section class="search-section">
            <h3>Cours</h3>
            <ul>
                <?php foreach ($results['courses'] as $c): ?>
                <li><a href="course_detail.php?id=<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['titre']); ?></a> — <?php echo htmlspecialchars($c['public_cible']); ?> (<?php echo htmlspecialchars($c['t_nom'] . ' ' . $c['t_prenom']); ?>)</li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
        <?php if (!empty($results['teachers'])): ?>
        <section class="search-section">
            <h3>Enseignants</h3>
            <ul>
                <?php foreach ($results['teachers'] as $t): ?>
                <li><?php echo htmlspecialchars($t['prenom'] . ' ' . $t['nom']); ?> — <?php echo htmlspecialchars($t['domaine']); ?>, <?php echo htmlspecialchars($t['grade']); ?> — <?php echo htmlspecialchars($t['email']); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
        <?php if (!empty($results['resources'])): ?>
        <section class="search-section">
            <h3>Ressources</h3>
            <ul>
                <?php foreach ($results['resources'] as $r): ?>
                <li><?php echo htmlspecialchars($r['title']); ?> (<?php echo htmlspecialchars($r['type']); ?>) — Cours: <?php echo htmlspecialchars($r['course_titre']); ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
        <?php if (array_reduce($results, function($c, $a) { return $c + count($a); }, 0) === 0): ?>
        <p>Aucun résultat trouvé.</p>
        <?php endif; ?>
    <?php endif; ?>
</main>
<footer><div class="footer-container"><p>&copy; <?php echo date('Y'); ?> Plateforme E-Learning.</p></div></footer>
</body>
</html>
