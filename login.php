<?php
/**
 * login.php - User Authentication with PDO & Password Hashing
 * Validates credentials against the 'staff' database table
 */

// Start session
session_start();

// Include database connection
require_once("db.php");

// Redirect to dashboard if user is already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

// Retrieve and clear flash success message (e.g. from successful registration)
$flash_success = $_SESSION["flash_success"] ?? "";
unset($_SESSION["flash_success"]);

// Initialize error array and form retention variable
$errors = [];
$identifier = "";

// Handle Form Submission (POST Request)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = trim($_POST["identifier"] ?? "");
    $password = $_POST["password"] ?? "";

    // Validation: Check for empty fields
    if (empty($identifier)) {
        $errors[] = "Username or Email is required.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // Verify credentials if input is not empty
    if (empty($errors)) {
        try {
            // Find staff record by username OR email
            $stmt = $conn->prepare("SELECT staff_id, user_name, full_name, email, password FROM staff WHERE user_name = ? OR email = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch();

            // Verify password using PHP's password_verify()
            if ($user && password_verify($password, $user["password"])) {
                // Regenerate session ID to prevent Session Fixation attacks
                session_regenerate_id(true);

                // Store authenticated user data in session
                $_SESSION["user_id"] = $user["staff_id"];
                $_SESSION["user_name"] = $user["user_name"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["email"] = $user["email"];

                // Automated Audit Trail
                logActivity($conn, $user["staff_id"], "Staff logged into the system (@" . $user["user_name"] . ")");

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $errors[] = "Invalid credentials. Please check your username/email and password.";
            }

            $stmt = null;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="auto">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Sign In</title>

    <!-- Bootstrap 5 CDN CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Palette Styles -->
    <link href="style.css" rel="stylesheet">
  </head>
  <body class="d-flex align-items-center py-4">

    <main class="form-auth form-signin auth-card">
      <form action="login.php" method="POST" novalidate>
        
        <div class="text-center mb-4">
          <h1 class="h3 brand-gradient-text mb-1">Staff Portal</h1>
          <p class="text-muted small">Sign in to access your dashboard</p>
        </div>

        <!-- Flash Success Notification (e.g. After Registration) -->
        <?php if (!empty($flash_success)): ?>
          <div class="alert alert-success py-2 small" role="alert">
            <?= htmlspecialchars($flash_success) ?>
          </div>
        <?php endif; ?>

        <!-- Server-side Error Notification Alert -->
        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger py-2 small" role="alert">
            <ul class="mb-0 ps-3">
              <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <!-- Username or Email Floating Input -->
        <div class="form-floating mb-3">
          <input 
            type="text" 
            class="form-control" 
            id="identifier" 
            name="identifier" 
            placeholder="Username or Email" 
            value="<?= htmlspecialchars($identifier) ?>" 
            required 
            autofocus
          >
          <label for="identifier">Username or Email</label>
        </div>

        <!-- Password Floating Input -->
        <div class="form-floating mb-3">
          <input 
            type="password" 
            class="form-control" 
            id="password" 
            name="password" 
            placeholder="Password" 
            required
          >
          <label for="password">Password</label>
        </div>

        <!-- Remember Me Checkbox -->
        <div class="form-check text-start mb-3">
          <input class="form-check-input" type="checkbox" value="remember-me" id="rememberMe">
          <label class="form-check-label small text-muted" for="rememberMe">
            Remember me on this device
          </label>
        </div>

        <!-- Submit Button -->
        <button class="btn btn-primary w-100 py-2 fw-semibold" type="submit">
          Sign In
        </button>

        <!-- Register Link -->
        <div class="text-center mt-4">
          <span class="text-secondary small">Don't have an account? </span>
          <a href="register.php" class="auth-link small">Register here</a>
        </div>

        <p class="mt-4 mb-0 text-center text-body-secondary small">&copy; <?= date('Y') ?> Staff System</p>
      </form>
    </main>

    <!-- Bootstrap 5 JavaScript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>

