<?php
session_start();
// Include the database connection from the main site
require_once '../includes/db.php';

// One-time script to ensure admin exists
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'admin@rubkhar.com'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES ('Administrator', 'admin@rubkhar.com', '', ?, 'admin')");
        $stmt->execute([$hash]);
    }
} catch(PDOException $e) {}

// Redirect if already logged in as admin
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid admin credentials.";
        }
    } else {
        $error = "Please enter email and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Rubkhar</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #8B1A4A;
            --dark-maroon: #5C1130;
            --gold: #C9963E;
            --light-pink: #FDF0F5;
            --font-heading: 'Playfair Display', serif;
            --font-body: 'Poppins', sans-serif;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: var(--font-body);
            background-color: var(--dark-maroon);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-card {
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-card h2 {
            font-family: var(--font-heading);
            color: var(--maroon);
            margin-bottom: 5px;
            font-size: 2rem;
        }
        .login-card p {
            color: #777;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #444;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: var(--font-body);
            outline: none;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        .form-control:focus {
            border-color: var(--gold);
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--maroon);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            transition: background 0.3s;
        }
        .btn-login:hover {
            background: #6a1338; /* Slightly darker maroon on hover */
        }
        .error-msg {
            color: #e51e25;
            background: #fff0f0;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Rubkhar Admin</h2>
    <p>Secure Dashboard Access</p>
    
    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Admin Email</label>
            <input type="email" name="email" class="form-control" placeholder="admin@rubkhar.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-login">Login to Dashboard</button>
    </form>
</div>

</body>
</html>
