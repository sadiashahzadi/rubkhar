<?php
session_start();
require_once 'includes/db.php';

// Ensure the 'phone' column exists in the users table for registration
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(50) NULL AFTER email");
} catch(PDOException $e) {
    // Column already exists, safe to ignore
}

// Manage redirect logic so users go back to where they came from
if (!isset($_SESSION['redirect_to'])) {
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    if (strpos($referer, 'login.php') !== false) {
        $referer = 'index.php';
    }
    $_SESSION['redirect_to'] = $referer;
}

$error = '';
// Determine which tab to show if validation fails
$active_tab = 'login'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $active_tab = 'register';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $terms = isset($_POST['terms']);

        if (!$name || !$email || !$phone || !$password || !$confirm) {
            $error = "All fields are required.";
        } elseif (!$terms) {
            $error = "You must accept the Terms and Conditions.";
        } elseif ($password !== $confirm) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "This email is already registered. Please log in.";
            } else {
                // Hash password with bcrypt
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'customer')");
                if ($stmt->execute([$name, $email, $phone, $hash])) {
                    // Auto-login the user
                    $_SESSION['user_id'] = $pdo->lastInsertId();
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_role'] = 'customer';
                    
                    $redirect = $_SESSION['redirect_to'];
                    unset($_SESSION['redirect_to']);
                    header("Location: " . $redirect);
                    exit;
                } else {
                    $error = "Registration failed due to a server error. Please try again.";
                }
            }
        }
    } elseif ($action === 'login') {
        $active_tab = 'login';
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $error = "Please enter both email and password.";
        } else {
            $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Verify bcrypt hash
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                
                // If "Remember Me" is needed, we would set a secure cookie here.
                
                $redirect = $_SESSION['redirect_to'];
                unset($_SESSION['redirect_to']);
                header("Location: " . $redirect);
                exit;
            } else {
                $error = "Invalid email address or password.";
            }
        }
    }
}

include 'includes/header.php';
?>
<style>
/* Login/Register Auth Styles */
.auth-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 80vh;
    padding: 50px 20px;
    /* Body is already light pink via header.php var(--light-pink), but forcing it here just in case */
    background-color: var(--light-pink); 
}
.auth-card {
    background: var(--white);
    width: 100%;
    max-width: 500px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(139, 26, 74, 0.08);
    overflow: hidden;
}
.auth-tabs {
    display: flex;
    border-bottom: 1px solid #eee;
}
.auth-tab {
    flex: 1;
    text-align: center;
    padding: 22px;
    font-family: var(--font-heading);
    font-size: 1.3rem;
    color: #888;
    cursor: pointer;
    background: #fafafa;
    transition: all 0.3s;
    user-select: none;
}
.auth-tab:hover {
    color: var(--maroon);
}
.auth-tab.active {
    background: var(--white);
    color: var(--maroon);
    border-bottom: 3px solid var(--maroon);
    font-weight: 600;
}
.auth-body {
    padding: 40px;
}
.auth-form {
    display: none;
}
.auth-form.active {
    display: block;
    animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(15px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form Groups */
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #444;
    font-weight: 500;
    font-size: 0.95rem;
}
.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.input-wrapper i.input-icon {
    position: absolute;
    left: 15px;
    color: #aaa;
}
.form-control {
    width: 100%;
    padding: 14px 15px 14px 45px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-family: var(--font-body);
    font-size: 1rem;
    outline: none;
    transition: border-color 0.3s;
    color: #333;
}
.form-control:focus {
    border-color: var(--gold);
}
.form-control:focus + .input-icon {
    color: var(--gold);
}
.password-toggle {
    position: absolute;
    right: 15px;
    color: #aaa;
    cursor: pointer;
    padding: 5px;
    transition: color 0.2s;
}
.password-toggle:hover {
    color: var(--maroon);
}

.auth-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    font-size: 0.9rem;
}
.checkbox-group {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #555;
    cursor: pointer;
}
.checkbox-group input {
    accent-color: var(--maroon);
    width: 16px;
    height: 16px;
}
.forgot-link {
    color: var(--maroon);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}
.forgot-link:hover {
    color: var(--gold);
}

.btn-auth {
    width: 100%;
    padding: 16px;
    background: var(--maroon);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 1.1rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    cursor: pointer;
    transition: background 0.3s, transform 0.1s;
    box-shadow: 0 4px 15px rgba(139, 26, 74, 0.2);
}
.btn-auth:hover {
    background: #6a1338;
}
.btn-auth:active {
    transform: translateY(2px);
}

.alert-error {
    background: #fff0f0;
    color: #e51e25;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #ffcccc;
    margin-bottom: 25px;
    text-align: center;
    font-weight: 500;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
</style>

<main class="auth-container">
    <div class="auth-card">
        
        <div class="auth-tabs">
            <div class="auth-tab <?= $active_tab === 'login' ? 'active' : '' ?>" data-tab="login" onclick="switchTab('login')">Login</div>
            <div class="auth-tab <?= $active_tab === 'register' ? 'active' : '' ?>" data-tab="register" onclick="switchTab('register')">Register</div>
        </div>
        
        <div class="auth-body">
            
            <?php if ($error): ?>
                <div class="alert-error" id="errorAlert">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <form id="login" class="auth-form <?= $active_tab === 'login' ? 'active' : '' ?>" method="POST" action="login.php">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" class="form-control" required placeholder="Enter your email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="login_password" class="form-control" required placeholder="Enter your password">
                        <i class="fas fa-lock input-icon"></i>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('login_password', this)" title="Show/Hide Password"></i>
                    </div>
                </div>
                
                <div class="auth-options">
                    <label class="checkbox-group">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-auth">Login Securely</button>
            </form>

            <!-- REGISTER FORM -->
            <form id="register" class="auth-form <?= $active_tab === 'register' ? 'active' : '' ?>" method="POST" action="login.php">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <div class="input-wrapper">
                        <input type="text" name="name" class="form-control" required placeholder="Jane Doe" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                        <i class="fas fa-user input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" name="email" class="form-control" required placeholder="jane@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <div class="input-wrapper">
                        <input type="tel" name="phone" class="form-control" required placeholder="03xx xxxxxxx" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        <i class="fas fa-phone-alt input-icon"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="password" id="reg_password" class="form-control" required placeholder="Minimum 6 characters">
                        <i class="fas fa-lock input-icon"></i>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('reg_password', this)" title="Show/Hide Password"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="input-wrapper">
                        <input type="password" name="confirm_password" id="reg_confirm" class="form-control" required placeholder="Re-enter password">
                        <i class="fas fa-lock input-icon"></i>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('reg_confirm', this)" title="Show/Hide Password"></i>
                    </div>
                </div>
                
                <div class="auth-options" style="margin-bottom: 25px;">
                    <label class="checkbox-group">
                        <input type="checkbox" name="terms" required>
                        <span>I accept the <a href="#" class="forgot-link">Terms and Conditions</a></span>
                    </label>
                </div>
                
                <button type="submit" class="btn-auth" style="background: var(--gold); box-shadow: 0 4px 15px rgba(201, 150, 62, 0.3);">Create Account</button>
            </form>

        </div>
    </div>
</main>

<script>
// Tab Switching Logic
function switchTab(tabId) {
    // Update active tab buttons
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
    
    // Update active forms
    document.querySelectorAll('.auth-form').forEach(f => f.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    
    // Hide error alerts when switching tabs to avoid confusion
    const alert = document.getElementById('errorAlert');
    if (alert) alert.style.display = 'none';
}

// Show/Hide Password Toggle Logic
function togglePassword(inputId, iconElement) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        iconElement.classList.remove('fa-eye');
        iconElement.classList.add('fa-eye-slash');
        iconElement.style.color = 'var(--maroon)';
    } else {
        input.type = 'password';
        iconElement.classList.remove('fa-eye-slash');
        iconElement.classList.add('fa-eye');
        iconElement.style.color = '#aaa';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
