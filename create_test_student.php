<?php
session_start();
include 'php/db.php';

// Créer un compte étudiant de test
$username = 'etudiant_test';
$password = password_hash('123456', PASSWORD_BCRYPT);
$email = 'etudiant.test@example.com';

// Vérifier si l'utilisateur existe déjà
$check = $conn->query("SELECT id FROM users WHERE username = 'etudiant_test'");
if ($check->num_rows > 0) {
    echo 'Compte étudiant existe déjà' . PHP_EOL;
    $user_id = $check->fetch_assoc()['id'];
} else {
    // Créer l'utilisateur
    $conn->query("INSERT INTO users (username, password, role) VALUES ('etudiant_test', '$password', 'student')");
    $user_id = $conn->insert_id;
    echo 'Utilisateur créé: ID ' . $user_id . PHP_EOL;
}

// Créer le profil étudiant
$numero_carte = '2024TEST001';
$check_carte = $conn->query("SELECT id FROM students WHERE numero_carte = '$numero_carte'");
if ($check_carte->num_rows == 0) {
    $conn->query("INSERT INTO students (user_id, numero_carte, nom, prenom, annee, email) 
                   VALUES ($user_id, '$numero_carte', 'Test', 'Étudiant', 1, '$email')");
    echo 'Profil étudiant créé' . PHP_EOL;
    echo '---' . PHP_EOL;
    echo 'Identifiant: etudiant_test' . PHP_EOL;
    echo 'Mot de passe: 123456' . PHP_EOL;
    echo 'Email: etudiant.test@example.com' . PHP_EOL;
} else {
    echo 'Le numéro de carte existe déjà' . PHP_EOL;
}

$conn->close();
echo PHP_EOL . 'Compte de test créé avec succès!' . PHP_EOL;
echo 'Connectez-vous à: http://localhost/elearning2/php/login.php' . PHP_EOL;
