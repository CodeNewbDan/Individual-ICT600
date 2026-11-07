<?php
/**
 * profile.php - Staff Profile Management Hub
 * Handles Read, Update, and Delete operations for the logged-in staff member.
 */

session_start();

// Include database connection (and automated logger)
require_once("db.php");

// Auth Guard: Enforce authenticated session
if (!isset($_SESSION["user_id"])) {
  $_SESSION["flash_error"] = "Please sign in to access your profile.";
  header("Location: login.php");
  exit;
}

$staff_id = $_SESSION["user_id"];


// Flash notification messages
$flash_success = $_SESSION["flash_success"] ?? "";
$flash_error = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

// Action errors
$profile_errors = [];
$password_errors = [];
$delete_errors = [];



// Handle Form Submissions (POST Requests)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "";

  // -------------------------------------------------------------
  // ACTION 1: UPDATE PROFILE (Name & Email)
  // -------------------------------------------------------------
  if ($action === "update_profile") {
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $profile_password = $_POST["profile_password"] ?? "";

    // Password Authorization Check
    if (empty($profile_password)) {
      $profile_errors[] = "Your current password is required to save profile changes.";
    } else {
      try {
        $pwdStmt = $conn->prepare("SELECT password FROM staff WHERE staff_id = ? LIMIT 1");
        $pwdStmt->execute([$staff_id]);
        $staffAuth = $pwdStmt->fetch();
        $pwdStmt = null;

        if (!$staffAuth || !password_verify($profile_password, $staffAuth["password"])) {
          $profile_errors[] = "Incorrect password. Profile details were not updated.";
        }
      } catch (PDOException $e) {
        $profile_errors[] = "Security verification failed: " . $e->getMessage();
      }
    }

    // Validation
    if (empty($full_name)) {
      $profile_errors[] = "Full name is required.";
    } elseif (strlen($full_name) < 3 || strlen($full_name) > 100) {
      $profile_errors[] = "Full name must be between 3 and 100 characters.";
    } elseif (!preg_match("/^[a-zA-Z\s'.-]+$/", $full_name)) {
      $profile_errors[] = "Full name can only contain letters, spaces, hyphens, and apostrophes.";
    }

    if (empty($email)) {
      $profile_errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $profile_errors[] = "Please provide a valid email address.";
    } else {
      // Check if email already belongs to another staff account
      try {
        $emailCheck = $conn->prepare("SELECT staff_id FROM staff WHERE LOWER(email) = LOWER(?) AND staff_id != ? LIMIT 1");
        $emailCheck->execute([$email, $staff_id]);
        if ($emailCheck->fetch()) {
          $profile_errors[] = "The email address is already in use by another staff member.";
        }
        $emailCheck = null;
      } catch (PDOException $e) {
        $profile_errors[] = "Database check failed: " . $e->getMessage();
      }
    }

    // Execute update if valid
    if (empty($profile_errors)) {
      try {
        $updateStmt = $conn->prepare("UPDATE staff SET full_name = ?, email = ? WHERE staff_id = ?");
        $updateStmt->execute([$full_name, $email, $staff_id]);

        // Synchronize active session variables
        $_SESSION["full_name"] = $full_name;
        $_SESSION["email"] = $email;


        // Log activity
        logActivity($conn, $staff_id, "Updated profile information (Full Name / Email)");

        $_SESSION["flash_success"] = "Your profile details have been successfully updated!";
        header("Location: profile.php");
        exit;
      } catch (PDOException $e) {
        $profile_errors[] = "Failed to update profile: " . $e->getMessage();
      }
    }
  }

  // -------------------------------------------------------------
  // ACTION 2: UPDATE PASSWORD
  // -------------------------------------------------------------
  elseif ($action === "update_password") {
    $current_password = $_POST["current_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if (empty($current_password)) {
      $password_errors[] = "Current password is required.";
    }
    if (empty($new_password)) {
      $password_errors[] = "New password is required.";
    } elseif (strlen($new_password) < 6) {
      $password_errors[] = "New password must be at least 6 characters long.";
    }
    if ($new_password !== $confirm_password) {
      $password_errors[] = "New password and password confirmation do not match.";
    }

    if (empty($password_errors)) {
      try {
        // Fetch existing password hash
        $pwdStmt = $conn->prepare("SELECT password FROM staff WHERE staff_id = ? LIMIT 1");
        $pwdStmt->execute([$staff_id]);
        $staffRecord = $pwdStmt->fetch();
        $pwdStmt = null;

        if (!$staffRecord || !password_verify($current_password, $staffRecord["password"])) {
          $password_errors[] = "Your current password does not match our records.";
        } else {
          $newHashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
          $upPwdStmt = $conn->prepare("UPDATE staff SET password = ? WHERE staff_id = ?");
          $upPwdStmt->execute([$newHashedPassword, $staff_id]);
          $upPwdStmt = null;

          // Log activity
          logActivity($conn, $staff_id, "Changed account password");

          $_SESSION["flash_success"] = "Your password has been changed successfully!";
          header("Location: profile.php");
          exit;
        }
      } catch (PDOException $e) {
        $password_errors[] = "Password update failed: " . $e->getMessage();
      }
    }
  }

  // -------------------------------------------------------------
  // ACTION 3: DELETE ACCOUNT (Permanent Removal)
  // -------------------------------------------------------------
  elseif ($action === "delete_account") {
    $confirm_delete_password = $_POST["confirm_delete_password"] ?? "";

    if (empty($confirm_delete_password)) {
      $delete_errors[] = "Password confirmation is required to delete your account.";
    } else {
      try {
        // Verify password before deleting
        $checkStmt = $conn->prepare("SELECT password, full_name, user_name FROM staff WHERE staff_id = ? LIMIT 1");
        $checkStmt->execute([$staff_id]);
        $staffUser = $checkStmt->fetch();
        $checkStmt = null;

        if (!$staffUser || !password_verify($confirm_delete_password, $staffUser["password"])) {
          $delete_errors[] = "Incorrect password. Account deletion aborted for security.";
        } else {
          // Safe cascading deletion via PDO Transaction
          $conn->beginTransaction();

          // 1. Remove subtasks for all projects owned by this staff
          $delTasks = $conn->prepare("DELETE FROM task WHERE project_id IN (SELECT project_id FROM project WHERE staff_id = ?)");
          $delTasks->execute([$staff_id]);

          // 2. Remove all projects owned by this staff
          $delProjects = $conn->prepare("DELETE FROM project WHERE staff_id = ?");
          $delProjects->execute([$staff_id]);

          // 3. Remove audit activity logs
          $delLogs = $conn->prepare("DELETE FROM activity_log WHERE staff_id = ?");
          $delLogs->execute([$staff_id]);

          // 4. Remove the staff member account
          $delStaff = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
          $delStaff->execute([$staff_id]);

          $conn->commit();

          // Destroy all session data & cookies
          $_SESSION = [];
          if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
              session_name(),
              '',
              time() - 42000,
              $params["path"],
              $params["domain"],
              $params["secure"],
              $params["httponly"]
            );
          }
          session_destroy();

          // Start fresh session to display exit notification
          session_start();
          $_SESSION["flash_success"] = "Your account and all associated projects and tasks have been permanently deleted.";
          header("Location: login.php");
          exit;
        }
      } catch (PDOException $e) {
        if ($conn->inTransaction()) {
          $conn->rollBack();
        }
        $delete_errors[] = "Account deletion failed: " . $e->getMessage();
      }
    }
  }
}

// -------------------------------------------------------------
// READ: Fetch current staff details and activity statistics
// -------------------------------------------------------------
$staff = null;
try {
  $staffStmt = $conn->prepare("SELECT * FROM staff WHERE staff_id = ? LIMIT 1");
  $staffStmt->execute([$staff_id]);
  $staff = $staffStmt->fetch();
  $staffStmt = null;
} catch (PDOException $e) {
  die("Error retrieving profile data: " . $e->getMessage());
}

if (!$staff) {
  session_destroy();
  header("Location: login.php");
  exit;
}

// Fetch project counts
$project_stats = [
  'total' => 0,
  'completed' => 0,
  'in_progress' => 0,
  'pending' => 0
];
try {
  $statsStmt = $conn->prepare("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending
        FROM project WHERE staff_id = ?");
  $statsStmt->execute([$staff_id]);
  $row = $statsStmt->fetch();
  if ($row) {
    $project_stats['total'] = (int) ($row['total'] ?? 0);
    $project_stats['completed'] = (int) ($row['completed'] ?? 0);
    $project_stats['in_progress'] = (int) ($row['in_progress'] ?? 0);
    $project_stats['pending'] = (int) ($row['pending'] ?? 0);
  }
  $statsStmt = null;
} catch (PDOException $e) {
  // Fail gracefully
}

// Fetch subtask counts
$task_stats = ['total' => 0, 'completed' => 0];
try {
  $tStatsStmt = $conn->prepare("SELECT 
        COUNT(t.task_id) AS total,
        SUM(CASE WHEN t.is_completed = 1 THEN 1 ELSE 0 END) AS completed
        FROM task t
        JOIN project p ON t.project_id = p.project_id
        WHERE p.staff_id = ?");
  $tStatsStmt->execute([$staff_id]);
  $tRow = $tStatsStmt->fetch();
  if ($tRow) {
    $task_stats['total'] = (int) ($tRow['total'] ?? 0);
    $task_stats['completed'] = (int) ($tRow['completed'] ?? 0);
  }
  $tStatsStmt = null;
} catch (PDOException $e) {
  // Fail gracefully
}

// Pre-fill input fields (retaining submitted values if an error occurred)
$current_full_name = (!empty($profile_errors) && isset($_POST["full_name"])) ? trim($_POST["full_name"]) : $staff["full_name"];
$current_email = (!empty($profile_errors) && isset($_POST["email"])) ? trim($_POST["email"]) : $staff["email"];
$current_username = $staff["user_name"];
$true_name = $_SESSION["full_name"] ?? "Staff Member";
$true_email = $_SESSION["email"] ?? "Email";

?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Staff Profile · Staff Project Portal</title>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Custom Palette Theme -->
  <link rel="stylesheet" href="style.css">
</head>

<body class="dashboard-body">

  <!-- Top Sticky Navigation Bar -->
  <header class="navbar navbar-dark sticky-top dashboard-navbar flex-md-nowrap p-2 px-3 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 fs-6 fw-bold text-white d-flex align-items-center gap-2"
      href="dashboard.php">
      <i class="bi bi-kanban-fill text-info"></i>
      <span>Staff Portal</span>
    </a>

    <!-- Mobile Sidebar Toggle Button -->
    <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse"
      data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation"
      style="right: 15px; top: 10px;">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="navbar-nav flex-row ms-auto align-items-center gap-2">
      <span class="text-white-50 small d-none d-sm-inline">
        Logged in as: <strong class="text-white"><?= htmlspecialchars($true_name) ?></strong>
      </span>
      <a class="btn btn-sm btn-outline-light d-flex align-items-center gap-1 active" href="profile.php">
        <i class="bi bi-person-fill"></i>
        <span class="d-none d-md-inline">Profile</span>
      </a>
      <a class="btn btn-sm btn-outline-light d-flex align-items-center gap-1" href="logout.php">
        <i class="bi bi-box-arrow-right"></i>
        <span>Sign out</span>
      </a>
    </div>
  </header>

  <div class="container-fluid">
    <div class="row">

      <!-- Sidebar Navigation -->
      <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block dashboard-sidebar collapse p-3">
        <div class="position-sticky pt-3">
          <h6 class="sidebar-heading px-3 mt-2 mb-2 text-uppercase text-muted small fw-bold">
            Menu
          </h6>
          <ul class="nav flex-column mb-4">
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center gap-2" href="dashboard.php">
                <i class="bi bi-speedometer2"></i>
                Dashboard
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link d-flex align-items-center gap-2" href="project_create.php">
                <i class="bi bi-plus-circle"></i>
                New Project
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link active d-flex align-items-center gap-2" href="profile.php">
                <i class="bi bi-person-circle"></i>
                Staff Profile
              </a>
            </li>
          </ul>

          <h6 class="sidebar-heading px-3 mt-4 mb-2 text-uppercase text-muted small fw-bold">
            Account
          </h6>
          <ul class="nav flex-column">
            <li class="nav-item px-3 py-2 small text-muted">
              <div><strong class="text-dark">@<?= htmlspecialchars($current_username) ?></strong></div>
              <div class="text-truncate"><?= htmlspecialchars($current_email) ?></div>
            </li>
            <li class="nav-item mt-2">
              <a class="nav-link text-danger d-flex align-items-center gap-2" href="logout.php">
                <i class="bi bi-power"></i>
                Sign Out
              </a>
            </li>
          </ul>
        </div>
      </nav>

      <!-- Main Profile Workspace -->
      <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

        <!-- Top Breadcrumb & Actions -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
          <div>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Staff Profile</li>
              </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-0">Staff Profile Management</h1>
          </div>
          <div class="btn-toolbar mb-2 mb-md-0">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 shadow-sm">
              <i class="bi bi-arrow-left"></i>
              <span>Back to Dashboard</span>
            </a>
          </div>
        </div>

        <!-- Global Flash Alerts -->
        <?php if (!empty($flash_success)): ?>
          <div class="alert alert-success alert-dismissible fade show shadow-sm d-flex align-items-center gap-2"
            role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($flash_success) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
          <div class="alert alert-danger alert-dismissible fade show shadow-sm d-flex align-items-center gap-2"
            role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= htmlspecialchars($flash_error) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <!-- READ: Profile Hero Card -->
        <div class="card border-0 shadow-sm mb-4"
          style="background: linear-gradient(135deg, #ffffff 0%, #f4f8fb 100%); border-left: 5px solid var(--bright-teal-blue) !important;">
          <div class="card-body p-4">
            <div class="row align-items-center g-3">
              <div class="col-auto">
                <div
                  class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm"
                  style="width: 72px; height: 72px; font-size: 1.75rem; background: linear-gradient(135deg, var(--bright-teal-blue), var(--cool-sky));">
                  <?= strtoupper(substr($true_name, 0, 1)) ?>
                </div>
              </div>
              <div class="col">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                  <h3 class="h4 fw-bold text-dark mb-0"><?= htmlspecialchars($true_name) ?></h3>
                  <span class="badge bg-light text-muted border">ID #<?= htmlspecialchars($staff["staff_id"]) ?></span>
                  <span class="badge badge-sky"><i class="bi bi-shield-check me-1"></i>Active Staff</span>
                </div>
                <div class="text-muted small d-flex flex-wrap align-items-center gap-3">
                  <span><i class="bi bi-at text-primary"></i><?= htmlspecialchars($current_username) ?></span>
                  <span><i class="bi bi-envelope text-secondary me-1"></i><?= htmlspecialchars($true_email) ?></span>
                  <?php if (!empty($staff["created_at"])): ?>
                    <span><i class="bi bi-calendar3 me-1"></i>Joined
                      <?= date("M d, Y", strtotime($staff["created_at"])) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Summary Stats Grid -->
        <div class="row g-3 mb-4">
          <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm border-0 h-100">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-light text-primary fs-3">
                  <i class="bi bi-folder-fill" style="color: var(--bright-teal-blue);"></i>
                </div>
                <div>
                  <div class="text-muted small fw-semibold text-uppercase">Total Projects</div>
                  <div class="h3 fw-bold mb-0 text-dark"><?= $project_stats['total'] ?></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm border-0 h-100">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-light text-success fs-3">
                  <i class="bi bi-check-circle-fill text-success"></i>
                </div>
                <div>
                  <div class="text-muted small fw-semibold text-uppercase">Completed Projects</div>
                  <div class="h3 fw-bold mb-0 text-dark"><?= $project_stats['completed'] ?></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm border-0 h-100">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-light text-warning fs-3">
                  <i class="bi bi-hourglass-split" style="color: var(--honey-bronze);"></i>
                </div>
                <div>
                  <div class="text-muted small fw-semibold text-uppercase">In Progress</div>
                  <div class="h3 fw-bold mb-0 text-dark"><?= $project_stats['in_progress'] ?></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm border-0 h-100">
              <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 p-3 bg-light text-info fs-3">
                  <i class="bi bi-list-check" style="color: var(--cool-sky);"></i>
                </div>
                <div>
                  <div class="text-muted small fw-semibold text-uppercase">Subtasks Done</div>
                  <div class="h3 fw-bold mb-0 text-dark"><?= $task_stats['completed'] ?> <small
                      class="text-muted fs-6">/ <?= $task_stats['total'] ?></small></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4">

          <!-- Left Column: Update Profile & Change Password Forms -->
          <div class="col-lg-7">

            <!-- UPDATE: Personal Details Card -->
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                  <i class="bi bi-person-lines-fill" style="color: var(--bright-teal-blue);"></i>
                  <span>Personal Details</span>
                </h5>
                <span class="badge bg-light text-muted border">Editable</span>
              </div>
              <div class="card-body p-4">

                <?php if (!empty($profile_errors)): ?>
                  <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <ul class="mb-0 ps-3">
                      <?php foreach ($profile_errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                      <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
                <?php endif; ?>

                <form action="profile.php" method="POST" novalidate>
                  <input type="hidden" name="action" value="update_profile">

                  <!-- Username (Readonly) -->
                  <div class="mb-3">
                    <label class="form-label small fw-semibold text-muted">Username</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light border-end-0 text-muted">@</span>
                      <input type="text" class="form-control bg-light"
                        value="<?= htmlspecialchars($current_username) ?>" readonly>
                    </div>
                    <div class="form-text small">Your unique username identifier cannot be changed.</div>
                  </div>

                  <!-- Full Name Input -->
                  <div class="mb-3">
                    <label for="full_name" class="form-label small fw-semibold text-muted">Full Name *</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                      <input type="text"
                        class="form-control <?= in_array('Full name is required.', $profile_errors) ? 'is-invalid' : '' ?>"
                        id="full_name" name="full_name" value="<?= htmlspecialchars($current_full_name) ?? '' ?>"
                        required>
                    </div>
                  </div>

                  <!-- Email Address Input -->
                  <div class="mb-3">
                    <label for="email" class="form-label small fw-semibold text-muted">Email Address *</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                      <input type="email"
                        class="form-control <?= in_array('Email address is required.', $profile_errors) ? 'is-invalid' : '' ?>"
                        id="email" name="email" value="<?= htmlspecialchars($current_email) ?>" required>
                    </div>
                  </div>

                  <!-- Current Password Authorization -->
                  <div class="mb-4">
                    <label for="profile_password" class="form-label small fw-semibold text-muted">Current Password
                      *</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light"><i class="bi bi-shield-lock"></i></span>
                      <input type="password"
                        class="form-control <?= (in_array('Your current password is required to save profile changes.', $profile_errors) || in_array('Incorrect password. Profile details were not updated.', $profile_errors)) ? 'is-invalid' : '' ?>"
                        id="profile_password" name="profile_password"
                        placeholder="Enter current password to authorize changes" required>
                    </div>
                    <div class="form-text small">For security, please enter your password to authorize changes to your
                      personal details.</div>
                  </div>

                  <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary d-flex align-items-center gap-2 px-4 shadow-sm">
                      <i class="bi bi-save2"></i>
                      <span>Save Changes</span>
                    </button>
                  </div>
                </form>
              </div>
            </div>

            <!-- UPDATE: Change Password Card -->
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <h5 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                  <i class="bi bi-shield-lock" style="color: var(--copperwood);"></i>
                  <span>Security & Password</span>
                </h5>
                <span class="badge bg-light text-muted border">Optional</span>
              </div>
              <div class="card-body p-4">

                <?php if (!empty($password_errors)): ?>
                  <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <ul class="mb-0 ps-3">
                      <?php foreach ($password_errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                      <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
                <?php endif; ?>

                <form action="profile.php" method="POST" novalidate>
                  <input type="hidden" name="action" value="update_password">

                  <!-- Current Password -->
                  <div class="mb-3">
                    <label for="current_password" class="form-label small fw-semibold text-muted">Current Password
                      *</label>
                    <div class="input-group">
                      <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                      <input type="password" class="form-control" id="current_password" name="current_password"
                        placeholder="Enter existing password" required>
                    </div>
                  </div>

                  <!-- New Password -->
                  <div class="row g-3 mb-4">
                    <div class="col-md-6">
                      <label for="new_password" class="form-label small fw-semibold text-muted">New Password *</label>
                      <input type="password" class="form-control" id="new_password" name="new_password"
                        placeholder="Min. 6 characters" required>
                    </div>
                    <div class="col-md-6">
                      <label for="confirm_password" class="form-label small fw-semibold text-muted">Confirm New Password
                        *</label>
                      <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                        placeholder="Re-type new password" required>
                    </div>
                  </div>

                  <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-warning d-flex align-items-center gap-2 px-4 shadow-sm">
                      <i class="bi bi-arrow-repeat"></i>
                      <span>Update Password</span>
                    </button>
                  </div>
                </form>
              </div>
            </div>

          </div>

          <!-- Right Column: Account Insights & Danger Zone (Delete) -->
          <div class="col-lg-5">

            <!-- Security & Account Notes -->
            <div class="card border-0 shadow-sm mb-4">
              <div class="card-header bg-white py-3 border-bottom">
                <h6 class="card-title mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                  <i class="bi bi-info-circle-fill" style="color: var(--cool-sky);"></i>
                  <span>Staff Account Guidelines</span>
                </h6>
              </div>
              <div class="card-body p-3 small text-muted">
                <p class="mb-2">
                  <i class="bi bi-check2-circle text-success me-1"></i>
                  <strong>Session Sync:</strong> Updates to your full name or email take effect immediately across all
                  dashboard screens.
                </p>
                <p class="mb-2">
                  <i class="bi bi-check2-circle text-success me-1"></i>
                  <strong>Audit Logging:</strong> All profile updates and password resets are automatically tracked in
                  the system audit trail.
                </p>
                <p class="mb-0">
                  <i class="bi bi-check2-circle text-success me-1"></i>
                  <strong>Data Integrity:</strong> Your staff ID is linked to all project records and subtasks you
                  manage.
                </p>
              </div>
            </div>

            <!-- DELETE: Danger Zone Card -->
            <div class="card border-danger shadow-sm mb-4" style="background-color: #fff9f8;">
              <div class="card-header bg-transparent border-danger py-3">
                <h6 class="card-title mb-0 fw-bold text-danger d-flex align-items-center gap-2">
                  <i class="bi bi-exclamation-octagon-fill"></i>
                  <span>Danger Zone · Account Deletion</span>
                </h6>
              </div>
              <div class="card-body p-4">

                <?php if (!empty($elete_errors)): ?>
                  <div class="alert alert-danger alert-dismissible fade show small" role="alert">
                    <ul class="mb-0 ps-3">
                      <?php foreach ($delete_errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                      <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
                <?php endif; ?>

                <p class="small text-danger mb-3">
                  Permanently remove your staff account. This action is <strong>irreversible</strong> and will
                  immediately delete:
                </p>
                <ul class="small text-muted mb-4 ps-3">
                  <li>Your staff profile and login credentials</li>
                  <li>All <strong><?= $project_stats['total'] ?> project(s)</strong> created by you</li>
                  <li>All <strong><?= $task_stats['total'] ?> subtask(s)</strong> attached to your projects</li>
                  <li>All associated activity audit logs</li>
                </ul>

                <button type="button"
                  class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-2 shadow-sm"
                  data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                  <i class="bi bi-trash3-fill"></i>
                  <span>Delete My Account</span>
                </button>
              </div>
            </div>

          </div>

        </div>

      </main>

    </div>
  </div>

  <!-- Account Deletion Confirmation Modal -->
  <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow">
        <form action="profile.php" method="POST">
          <input type="hidden" name="action" value="delete_account">

          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="deleteAccountModalLabel">
              <i class="bi bi-exclamation-triangle-fill"></i>
              <span>Confirm Account Deletion</span>
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body p-4">
            <p class="text-dark mb-3">
              Are you sure you want to permanently delete the account for
              <strong><?= htmlspecialchars($current_full_name) ?></strong>
              (<code>@<?= htmlspecialchars($current_username) ?></code>)?
            </p>
            <div class="alert alert-warning small d-flex align-items-center gap-2 mb-3">
              <i class="bi bi-info-circle-fill fs-5"></i>
              <div>All associated projects, tasks, and audit logs will be wiped permanently.</div>
            </div>

            <div class="mb-3">
              <label for="confirm_delete_password" class="form-label small fw-semibold text-danger">Enter your password
                to confirm:</label>
              <input type="password" class="form-control border-danger" id="confirm_delete_password"
                name="confirm_delete_password" placeholder="Your current password" required>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger btn-sm d-flex align-items-center gap-1 shadow-sm">
              <i class="bi bi-trash-fill"></i>
              <span>Permanently Delete</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap 5 JavaScript Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
</body>

</html>