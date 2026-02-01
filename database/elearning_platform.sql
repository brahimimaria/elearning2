-- E-Learning Platform - Database Schema (matches mini.pdf)
-- Teachers: Nom, Prénom, Domaine, Grade, Adresse Email
-- Courses: Titre, responsable (enseignant), Public ciblé, clé d'inscription, Information sur le cours
-- Students: Numéro de la carte, Nom, Prénom, L'année, Adresse Email

DROP DATABASE IF EXISTS elearning_platform;
CREATE DATABASE elearning_platform;
USE elearning_platform;

-- Users (login: admin, student, instructor)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'instructor', 'student') NOT NULL
);

-- Teachers: Nom, Prénom, Domaine, Grade, Adresse Email
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    domaine VARCHAR(100) NOT NULL,
    grade VARCHAR(50) NOT NULL,
    email VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Students: Numéro de la carte, Nom, Prénom, L'année, Adresse Email
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    numero_carte VARCHAR(50) NOT NULL UNIQUE,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    annee INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Courses: Titre, responsable (enseignant), Public ciblé, clé d'inscription, Information sur le cours
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    teacher_id INT NOT NULL,
    public_cible VARCHAR(100) NOT NULL,
    cle_inscription VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('Active', 'Completed', 'Upcoming') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- Student enrollment in courses (via clé d'inscription)
CREATE TABLE course_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (student_id, course_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Course supports: vidéo, documents (linked to course)
CREATE TABLE course_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    type ENUM('video', 'document') NOT NULL,
    url_or_path VARCHAR(500),
    upload_date DATE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Forums: discussion entre étudiants et enseignant
CREATE TABLE forum_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Evaluations: Quiz, devoirs, examens
CREATE TABLE evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    type ENUM('quiz', 'devoir', 'examen') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    due_date DATE,
    max_score INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Schedule (optional, for class dates)
CREATE TABLE schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NULL,
    instructor_name VARCHAR(100),
    class_date DATE,
    class_time TIME,
    topic VARCHAR(200),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Default admin (password: admin123)
INSERT INTO users (username, password, role) VALUES ('admin', '$2y$10$t9Ac104GU/kI7rvHRwM0kOjvP3.G0caydx8kk5CSUOGLcLoj6.fVy', 'admin');

-- Sample teacher and course for testing
INSERT INTO teachers (nom, prenom, domaine, grade, email) VALUES ('Dupont', 'Jean', 'Informatique', 'Professeur', 'jean.dupont@univ.edu');
INSERT INTO courses (titre, teacher_id, public_cible, cle_inscription, description, status) 
VALUES ('Base de données', 1, 'L3 Informatique', 'DB2024', 'Cours de bases de données relationnelles.', 'Active');
