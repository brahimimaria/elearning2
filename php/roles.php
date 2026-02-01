<?php
session_start();
include 'db.php';

// Check if the user is logged in and has the 'admin' role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle form submission to update user roles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];

    $sql = "UPDATE users SET role = '$new_role' WHERE id = $user_id";
    if ($conn->query($sql) === TRUE) {
        $message = "Role updated successfully!";
    } else {
        $message = "Error updating role: " . $conn->error;
    }
}

// Fetch all users
$sql = "SELECT * FROM users";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <header>
        <div class="header-container">
            <h1>Role Management</h1>
            <nav>
                <ul>
                    <li><a href="../index.php" class="button">Return to Main Page</a></li>
                    <li><a href="logout.php" class="button">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <h2>Manage User Roles</h2>
        <?php if (isset($message)) { echo "<p style='color: green;'>$message</p>"; } ?>

        <section>
            <h3>Current Users</h3>
            <table>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['role']); ?></td>
                    <td>
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                            <select name="new_role" required>
                                <option value="admin" <?php echo $row['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="instructor" <?php echo $row['role'] === 'instructor' ? 'selected' : ''; ?>>Instructor</option>
                                <option value="student" <?php echo $row['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                            </select>
                            <button type="submit" name="update_role" class="button">Update</button>
                        </form>
                    </td>
                </tr>
                <?php } ?>
            </table>
        </section>
    </main>

    <footer>
        <div class="footer-container">
            <p>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['role']); ?></strong></p>
        </div>
    </footer>
</body>
</html>
