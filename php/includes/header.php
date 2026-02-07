<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<header>
    <div class="header-container">
        <h1><a href="<?php echo $is_php_folder ? '../index.php' : 'index.php'; ?>" style="color: inherit; text-decoration: none;">Plateforme E-Learning</a></h1>
        <form class="search-form" action="<?php echo $is_php_folder ? 'search.php' : 'php/search.php'; ?>" method="GET" role="search">
            <input type="search" name="q" placeholder="Rechercher cours, enseignants, ressources..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>" class="search-input" aria-label="Recherche">
            <button type="submit" class="search-button">Rechercher</button>
        </form>
        <nav>
            <ul>
                <?php if (isset($_SESSION['username'])): ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                        <li><a href="<?php echo $is_php_folder ? 'my_courses.php' : 'php/my_courses.php'; ?>" class="button"><i class="fas fa-book"></i> Cours</a></li>
                        <li><a href="<?php echo $is_php_folder ? 'forum.php' : 'php/forum.php'; ?>" class="button"><i class="fas fa-comments"></i> Forum</a></li>
                    <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'instructor'): ?>
                        <li><a href="<?php echo $is_php_folder ? 'teacher_courses.php' : 'php/teacher_courses.php'; ?>" class="button"><i class="fas fa-book"></i> Mes Cours</a></li>
                        <li><a href="<?php echo $is_php_folder ? 'forum.php' : 'php/forum.php'; ?>" class="button"><i class="fas fa-comments"></i> Forum</a></li>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <li><a href="<?php echo $is_php_folder ? 'admin_teachers.php' : 'php/admin_teachers.php'; ?>" class="button">Enseignants</a></li>
                        <li><a href="<?php echo $is_php_folder ? 'admin_students.php' : 'php/admin_students.php'; ?>" class="button">Étudiants</a></li>
                        <li><a href="<?php echo $is_php_folder ? 'admin_courses.php' : 'php/admin_courses.php'; ?>" class="button">Cours</a></li>
                    <?php endif; ?>
                    <li><a href="<?php echo $is_php_folder ? 'logout.php' : 'php/logout.php'; ?>" class="button">Déconnexion</a></li>
                <?php else: ?>
                    <li><a href="<?php echo $is_php_folder ? 'login.php' : 'php/login.php'; ?>" class="button">Connexion</a></li>
                    <li><a href="<?php echo $is_php_folder ? 'register.php' : 'php/register.php'; ?>" class="button">Inscription</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
