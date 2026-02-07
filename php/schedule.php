<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Check if user is an admin or instructor
$canSchedule = ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'instructor');

// Handle form submission to add new class (only if the user is authorized)
if ($canSchedule && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $instructor_name = $_POST['instructor_name'];
    $topic = $_POST['topic'];
    $class_date = $_POST['class_date'];
    $class_time = $_POST['class_time'];

    $sql = "INSERT INTO schedule (instructor_name, topic, class_date, class_time)
            VALUES ('$instructor_name', '$topic', '$class_date', '$class_time')";

    if ($conn->query($sql) === TRUE) {
        $message = "Nouvelle session planifiée avec succès !";
        $message_type = "success";
    } else {
        $message = "Erreur : " . $conn->error;
        $message_type = "error";
    }
}

// Fetch existing classes from the database
$sql = "SELECT * FROM schedule ORDER BY class_date, class_time";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planning - Plateforme E-Learning</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        .add-class-form {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .schedule-list {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .class-card {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .class-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .class-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        }

        .class-time {
            color: #4a5568;
            font-size: 1.1rem;
            margin-bottom: 0.8rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .class-time::before {
            content: '🕒';
            font-size: 1rem;
        }

        .class-topic {
            color: #2d3748;
            font-weight: 600;
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .class-topic::before {
            content: '📚';
            font-size: 1.2rem;
        }

        .class-instructor {
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .class-instructor::before {
            content: '👨‍🏫';
            font-size: 1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            align-items: end;
        }

        .date-header {
            background: linear-gradient(135deg, #1a365d 0%, #2d4a8c 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin: 2rem 0 1rem;
            font-weight: 500;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .date-header::before {
            content: '📅';
            font-size: 1.2rem;
        }

        .no-classes {
            text-align: center;
            padding: 3rem 2rem;
            color: #4a5568;
            font-style: italic;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px dashed #e2e8f0;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .calendar-filters {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-button {
            padding: 0.7rem 1.2rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #4a5568;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-button:hover {
            background: #f7fafc;
            border-color: #4299e1;
            color: #4299e1;
        }

        .filter-button.active {
            background: #4299e1;
            color: white;
            border-color: #4299e1;
        }

        .class-count {
            background: #ebf8ff;
            color: #2c5282;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .calendar-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <h1>Planning</h1>
            <nav>
                <ul>
                    <li><a href="../index.php" class="button">Retour à l'accueil</a></li>
                    <li><a href="logout.php" class="button">Déconnexion</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <?php if (isset($message)): ?>
            <div class="<?php echo $message_type; ?>-message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="calendar-grid">
            <?php if ($canSchedule): ?>
            <div class="add-class-form">
                <h2>Planifier une nouvelle session</h2>
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="topic">Sujet / Cours</label>
                            <input type="text" name="topic" id="topic" required
                                   placeholder="Sujet de la session">
                        </div>

                        <div class="form-group">
                            <label for="instructor_name">Enseignant</label>
                            <input type="text" name="instructor_name" id="instructor_name" required
                                   placeholder="Nom de l'enseignant">
                        </div>

                        <div class="form-group">
                            <label for="class_date">Date</label>
                            <input type="date" name="class_date" id="class_date" required
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label for="class_time">Heure</label>
                            <input type="time" name="class_time" id="class_time" required>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="button">Planifier</button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="schedule-list">
                <div class="calendar-header">
                    <h2>Sessions planifiées</h2>
                    <div class="calendar-filters">
                        <button class="filter-button active" data-filter="all">
                            Toutes
                            <span class="class-count"><?php echo $result->num_rows; ?></span>
                        </button>
                        <button class="filter-button" data-filter="today">Aujourd'hui</button>
                        <button class="filter-button" data-filter="week">Cette semaine</button>
                        <button class="filter-button" data-filter="month">Ce mois</button>
                    </div>
                </div>

                <?php
                $current_date = '';
                if ($result->num_rows > 0):
                    while ($row = $result->fetch_assoc()):
                        $date = date('Y-m-d', strtotime($row['class_date']));
                        if ($date != $current_date):
                            $current_date = $date;
                ?>
                            <div class="date-header">
                                <?php echo date('l, F j, Y', strtotime($date)); ?>
                            </div>
                <?php
                        endif;
                ?>
                        <div class="class-card" data-date="<?php echo $date; ?>">
                            <div class="class-time">
                                <?php echo date('g:i A', strtotime($row['class_time'])); ?>
                            </div>
                            <div class="class-topic">
                                <?php echo htmlspecialchars($row['topic']); ?>
                            </div>
                            <div class="class-instructor">
                                Enseignant : <?php echo htmlspecialchars($row['instructor_name']); ?>
                            </div>
                        </div>
                <?php
                    endwhile;
                else:
                ?>
                    <div class="no-classes">
                        Aucune session planifiée pour le moment.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="footer-container">
            <p>Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?> | Role: <?php echo htmlspecialchars($_SESSION['role']); ?></p>
        </div>
    </footer>

    <script>
        // Add click handlers for filter buttons
        document.querySelectorAll('.filter-button').forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons
                document.querySelectorAll('.filter-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Add active class to clicked button
                button.classList.add('active');
                
                const filter = button.dataset.filter;
                const cards = document.querySelectorAll('.class-card');
                const today = new Date().toISOString().split('T')[0];
                
                cards.forEach(card => {
                    const cardDate = card.dataset.date;
                    const dateObj = new Date(cardDate);
                    const now = new Date();
                    
                    if (filter === 'all') {
                        card.style.display = '';
                        card.closest('.date-header')?.style.display = '';
                    } else if (filter === 'today' && cardDate === today) {
                        card.style.display = '';
                        card.closest('.date-header')?.style.display = '';
                    } else if (filter === 'week') {
                        const weekAhead = new Date();
                        weekAhead.setDate(weekAhead.getDate() + 7);
                        if (dateObj >= now && dateObj <= weekAhead) {
                            card.style.display = '';
                            card.closest('.date-header')?.style.display = '';
                        } else {
                            card.style.display = 'none';
                            card.closest('.date-header')?.style.display = 'none';
                        }
                    } else if (filter === 'month') {
                        const monthAhead = new Date();
                        monthAhead.setMonth(monthAhead.getMonth() + 1);
                        if (dateObj >= now && dateObj <= monthAhead) {
                            card.style.display = '';
                            card.closest('.date-header')?.style.display = '';
                        } else {
                            card.style.display = 'none';
                            card.closest('.date-header')?.style.display = 'none';
                        }
                    } else {
                        card.style.display = 'none';
                        card.closest('.date-header')?.style.display = 'none';
                    }
                });
            });
        });

        // Set min date for class date input
        const dateInput = document.getElementById('class_date');
        if (dateInput) {
            dateInput.min = new Date().toISOString().split('T')[0];
        }
    </script>
</body>
</html>
