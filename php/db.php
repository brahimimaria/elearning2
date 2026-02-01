<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "elearning_platform";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['username']);
}

function loginUser($username, $password) {
    global $conn;
    $u = $conn->real_escape_string($username);
    $sql = "SELECT id, username, password, role FROM users WHERE username = '$u'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
    }
    return false;
}

function registerUser($username, $password, $role) {
    global $conn;
    $u = $conn->real_escape_string($username);
    $r = $conn->real_escape_string($role);
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $check = $conn->query("SELECT id FROM users WHERE username = '$u'");
    if ($check && $check->num_rows > 0) return false;
    return $conn->query("INSERT INTO users (username, password, role) VALUES ('$u', '$hash', '$r')") === true;
}

/** Register student (public): creates user + students row. */
function registerStudent($username, $password, $numero_carte, $nom, $prenom, $annee, $email) {
    global $conn;
    $u = $conn->real_escape_string($username);
    $nc = $conn->real_escape_string($numero_carte);
    $nom = $conn->real_escape_string($nom);
    $prenom = $conn->real_escape_string($prenom);
    $email = $conn->real_escape_string($email);
    $annee = (int)$annee;
    $check = $conn->query("SELECT id FROM users WHERE username = '$u'");
    if ($check && $check->num_rows > 0) return ['ok' => false, 'msg' => 'Ce nom d\'utilisateur existe déjà.'];
    $check = $conn->query("SELECT id FROM students WHERE numero_carte = '$nc'");
    if ($check && $check->num_rows > 0) return ['ok' => false, 'msg' => 'Ce numéro de carte est déjà enregistré.'];
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $conn->query("INSERT INTO users (username, password, role) VALUES ('$u', '$hash', 'student')");
    $uid = $conn->insert_id;
    if ($uid && $conn->query("INSERT INTO students (user_id, numero_carte, nom, prenom, annee, email) VALUES ($uid, '$nc', '$nom', '$prenom', $annee, '$email')")) {
        return ['ok' => true];
    }
    return ['ok' => false, 'msg' => 'Erreur lors de l\'inscription.'];
}

function getStudentByUserId($user_id) {
    global $conn;
    $id = (int)$user_id;
    $r = $conn->query("SELECT * FROM students WHERE user_id = $id LIMIT 1");
    return $r && $r->num_rows ? $r->fetch_assoc() : null;
}

function getTeacherByUserId($user_id) {
    global $conn;
    $id = (int)$user_id;
    $r = $conn->query("SELECT * FROM teachers WHERE user_id = $id LIMIT 1");
    return $r && $r->num_rows ? $r->fetch_assoc() : null;
}

/** Enroll current student in course by cle_inscription. */
function enrollByKey($user_id, $cle_inscription) {
    global $conn;
    $student = getStudentByUserId($user_id);
    if (!$student) return ['ok' => false, 'msg' => 'Compte étudiant non trouvé.'];
    $cle = $conn->real_escape_string($cle_inscription);
    $c = $conn->query("SELECT id FROM courses WHERE cle_inscription = '$cle' LIMIT 1");
    if (!$c || $c->num_rows === 0) return ['ok' => false, 'msg' => 'Clé d\'inscription invalide.'];
    $course = $c->fetch_assoc();
    $sid = (int)$student['id'];
    $cid = (int)$course['id'];
    $exists = $conn->query("SELECT id FROM course_enrollments WHERE student_id = $sid AND course_id = $cid");
    if ($exists && $exists->num_rows > 0) return ['ok' => false, 'msg' => 'Vous êtes déjà inscrit à ce cours.'];
    if ($conn->query("INSERT INTO course_enrollments (student_id, course_id) VALUES ($sid, $cid)")) {
        return ['ok' => true];
    }
    return ['ok' => false, 'msg' => 'Erreur d\'inscription.'];
}

function logoutUser() {
    session_unset();
    session_destroy();
}
?>
