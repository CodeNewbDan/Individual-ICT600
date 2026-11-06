<?php
/**
 * project_edit.php - Edit / Update Existing Project
 * Pre-populates existing data and updates records securely with ownership validation.
 */

session_start();

// Include database connection
require_once("db.php");

// Auth Guard: Redirect unauthenticated users
if (!isset($_SESSION["user_id"])) {
    $_SESSION["flash_error"] = "Please sign in to edit projects.";
    header("Location: login.php");
    exit;
}

$staff_id = $_SESSION["user_id"];
$full_name = $_SESSION["full_name"] ?? "Staff Member";
$user_name = $_SESSION["user_name"] ?? "";
$email = $_SESSION["email"] ?? "";

// Validate incoming Project ID from URL parameter
$project_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$project_id) {
    $_SESSION["flash_error"] = "Invalid project ID specified.";
    header("Location: dashboard.php");
    exit;
}

// Fetch project record and ensure it belongs to the logged-in staff member
try {
    $stmt = $conn->prepare("SELECT p.project_id, p.staff_id, p.category_id, p.title, p.project_description, p.status, p.created_at, c.category_name 
                           FROM project p 
                           LEFT JOIN category c ON p.category_id = c.category_id 
                           WHERE p.project_id = ? AND p.staff_id = ? 
                           LIMIT 1");
    $stmt->execute([$project_id, $staff_id]);
    $project = $stmt->fetch();
    $stmt = null;

    if (!$project) {
        $_SESSION["flash_error"] = "Project not found or you do not have permission to edit it.";
        header("Location: dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    $_SESSION["flash_error"] = "Database error: " . $e->getMessage();
    header("Location: dashboard.php");
    exit;
}

// Fetch available categories for datalist suggestions
$categories = [];
try {
    $catStmt = $conn->query("SELECT category_id, category_name, category_details FROM category ORDER BY category_name ASC");
    $categories = $catStmt->fetchAll();
    $catStmt = null;
} catch (PDOException $e) {
    $cat_error = "Unable to load categories: " . $e->getMessage();
}

// Initialize form variables with existing database values
$errors = [];
$title = $project["title"];
$category_name_input = $project["category_name"] ?? "";
$status = $project["status"];
$project_description = $project["project_description"] ?? "";

// Handle Form Submission (POST Request)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim(filter_input(INPUT_POST, 'title', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $category_name_input = trim(filter_input(INPUT_POST, 'category_name_input', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $project_description = trim(filter_input(INPUT_POST, 'project_description', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');

    // Server-Side Input Validation
    if (empty($title)) {
        $errors[] = "Project title is required.";
    } elseif (strlen($title) < 3 || strlen($title) > 150) {
        $errors[] = "Project title must be between 3 and 150 characters.";
    }

    if (empty($category_name_input)) {
        $errors[] = "Category is required. Select an existing one or type a new one.";
    } elseif (strlen($category_name_input) < 2 || strlen($category_name_input) > 50) {
        $errors[] = "Category name must be between 2 and 50 characters.";
    } elseif (!preg_match('/^[a-zA-Z0-9\s&_\/-]{2,50}$/', $category_name_input)) {
        $errors[] = "Category name contains invalid characters. Use letters, numbers, spaces, and standard symbols (&, -, /).";
    }

    $valid_statuses = ['Pending', 'In Progress', 'Completed'];
    if (!in_array($status, $valid_statuses)) {
        $errors[] = "Invalid status selected.";
    }

    if (empty($project_description)) {
        $errors[] = "Project description is required.";
    } elseif (strlen($project_description) < 5) {
        $errors[] = "Description is too short. Please provide at least 5 characters.";
    }

    // Process update if no errors
    if (empty($errors)) {
        try {
            // Check if category exists or create new one on the fly
            $findCat = $conn->prepare("SELECT category_id FROM category WHERE LOWER(category_name) = LOWER(?) LIMIT 1");
            $findCat->execute([$category_name_input]);
            $existingCat = $findCat->fetch();

            if ($existingCat) {
                $category_id = $existingCat['category_id'];
            } else {
                $createCat = $conn->prepare("INSERT INTO category (category_name, category_details) VALUES (?, ?)");
                $createCat->execute([$category_name_input, "Added during project update"]);
                $category_id = $conn->lastInsertId();
                $createCat = null;
            }
            $findCat = null;

            // Update project record (Strictly scoped by staff_id for security)
            $updateStmt = $conn->prepare("UPDATE project 
                                         SET category_id = ?, title = ?, project_description = ?, status = ? 
                                         WHERE project_id = ? AND staff_id = ?");
            $updateStmt->execute([$category_id, $title, $project_description, $status, $project_id, $staff_id]);

            $_SESSION["flash_success"] = "Project '" . htmlspecialchars($title) . "' was updated successfully!";
            header("Location: dashboard.php");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Project · Staff Portal</title>

    <!-- Bootstrap 5 CDN CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Palette Styles -->
    <link href="style.css" rel="stylesheet">
  </head>
  <body>

    <!-- Top Sticky Navigation Bar -->
    <header class="navbar navbar-dark sticky-top dashboard-navbar flex-md-nowrap p-2 px-3 shadow">
      <a class="navbar-brand col-md-3 col-lg-2 me-0 fs-6 fw-bold text-white d-flex align-items-center gap-2" href="dashboard.php">
        <i class="bi bi-kanban-fill text-info"></i>
        <span>Staff Portal</span>
      </a>
      
      <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation" style="right: 15px; top: 10px;">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="navbar-nav flex-row ms-auto align-items-center gap-3">
        <span class="text-white-50 small d-none d-sm-inline">
          Logged in as: <strong class="text-white"><?= htmlspecialchars($full_name) ?></strong>
        </span>
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
            </ul>

            <h6 class="sidebar-heading px-3 mt-4 mb-2 text-uppercase text-muted small fw-bold">
              Account
            </h6>
            <ul class="nav flex-column">
              <li class="nav-item px-3 py-2 small text-muted">
                <div><strong class="text-dark"><?= htmlspecialchars($user_name) ?></strong></div>
                <div class="text-truncate"><?= htmlspecialchars($email) ?></div>
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

        <!-- Main Content Area -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

          <!-- Breadcrumb Navigation -->
          <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
              <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none" style="color: var(--bright-teal-blue);">Dashboard</a></li>
              <li class="breadcrumb-item text-muted">Projects</li>
              <li class="breadcrumb-item active" aria-current="page">Edit #<?= htmlspecialchars($project["project_id"]) ?></li>
            </ol>
          </nav>

          <!-- Page Heading -->
          <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
            <div>
              <h1 class="h2 brand-gradient-text mb-1">Edit Project #<?= htmlspecialchars($project["project_id"]) ?></h1>
              <p class="text-muted small mb-0">Update information and progress status for <strong><?= htmlspecialchars($project["title"]) ?></strong>.</p>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0 mt-3 mt-md-0">
              <a 
                href="project_delete.php?id=<?= urlencode($project["project_id"]) ?>" 
                class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1"
                onclick="return confirm('Are you sure you want to permanently delete this project?');"
              >
                <i class="bi bi-trash"></i> Delete Project
              </a>
            </div>
          </div>

          <!-- Error Alert Notification -->
          <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 small" role="alert">
              <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Please correct the following errors:</div>
              <ul class="mb-0 ps-3">
                <?php foreach ($errors as $error): ?>
                  <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <!-- 2-Column Combined Layout -->
          <div class="row g-4">

            <!-- Left Column: Edit Form (8 cols) -->
            <div class="col-lg-8">
              <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                  <h5 class="card-title mb-0 fw-bold" style="color: var(--bright-teal-blue);">
                    <i class="bi bi-pencil-square me-2"></i>Edit Details
                  </h5>
                  <span class="text-muted small">Created: <?= date("M d, Y", strtotime($project["created_at"])) ?></span>
                </div>
                <div class="card-body p-4">
                  <form action="project_edit.php?id=<?= urlencode($project_id) ?>" method="POST" novalidate>

                    <!-- Title -->
                    <div class="form-floating mb-3">
                      <input 
                        type="text" 
                        class="form-control" 
                        id="title" 
                        name="title" 
                        placeholder="Project Title" 
                        value="<?= htmlspecialchars($title) ?>" 
                        required 
                        autofocus
                      >
                      <label for="title">Project Title *</label>
                      <div class="form-text small">Between 3 and 150 characters.</div>
                    </div>

                    <!-- Category & Status Row -->
                    <div class="row g-3 mb-3">
                      
                      <!-- Category with Datalist Suggestions -->
                      <div class="col-md-6">
                        <div class="form-floating">
                          <input 
                            type="text" 
                            class="form-control" 
                            id="category_name_input" 
                            name="category_name_input" 
                            list="categoryList" 
                            placeholder="Type or select a category" 
                            value="<?= htmlspecialchars($category_name_input) ?>" 
                            required 
                            autocomplete="off"
                          >
                          <label for="category_name_input">Category *</label>
                          <datalist id="categoryList">
                            <?php foreach ($categories as $cat): ?>
                              <option value="<?= htmlspecialchars($cat['category_name']) ?>"></option>
                            <?php endforeach; ?>
                          </datalist>
                        </div>
                        <div class="form-text small">Select or type a new category.</div>
                      </div>

                      <!-- Status Dropdown -->
                      <div class="col-md-6">
                        <div class="form-floating">
                          <select class="form-select" id="status" name="status" required>
                            <option value="Pending" <?= ($status === 'Pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="In Progress" <?= ($status === 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                            <option value="Completed" <?= ($status === 'Completed') ? 'selected' : '' ?>>Completed</option>
                          </select>
                          <label for="status">Project Status *</label>
                        </div>
                      </div>

                    </div>

                    <!-- Project Description -->
                    <div class="form-floating mb-4">
                      <textarea 
                        class="form-control" 
                        placeholder="Project Description" 
                        id="project_description" 
                        name="project_description" 
                        style="height: 140px;" 
                        required
                      ><?= htmlspecialchars($project_description) ?></textarea>
                      <label for="project_description">Project Description *</label>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex align-items-center gap-2 pt-2 border-top">
                      <button type="submit" class="btn btn-primary px-4 py-2 d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Update Project</span>
                      </button>
                      <a href="dashboard.php" class="btn btn-outline-secondary px-4 py-2">
                        Cancel
                      </a>
                    </div>

                  </form>
                </div>
              </div>
            </div>

            <!-- Right Column: Meta Info & Status Guide (4 cols) -->
            <div class="col-lg-4">
              
              <!-- Project Meta Details Card -->
              <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                  <h6 class="card-title mb-0 fw-bold text-dark">
                    <i class="bi bi-info-circle-fill text-info me-2"></i>Record Details
                  </h6>
                </div>
                <div class="card-body p-3 small text-muted">
                  <div class="d-flex justify-content-between py-1 border-bottom">
                    <span>Project ID:</span>
                    <strong class="text-dark">#<?= htmlspecialchars($project["project_id"]) ?></strong>
                  </div>
                  <div class="d-flex justify-content-between py-1 border-bottom">
                    <span>Owner / Staff:</span>
                    <strong class="text-dark"><?= htmlspecialchars($full_name) ?></strong>
                  </div>
                  <div class="d-flex justify-content-between py-1 border-bottom">
                    <span>Current Status:</span>
                    <strong class="text-dark"><?= htmlspecialchars($project["status"]) ?></strong>
                  </div>
                  <div class="d-flex justify-content-between py-1">
                    <span>Date Added:</span>
                    <strong class="text-dark"><?= date("M d, Y h:i A", strtotime($project["created_at"])) ?></strong>
                  </div>
                </div>
              </div>

              <!-- Available Categories Card -->
              <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                  <h6 class="card-title mb-0 fw-bold text-dark">
                    <i class="bi bi-tags-fill me-2" style="color: var(--bright-teal-blue);"></i>Active Categories
                  </h6>
                  <span class="badge badge-sky px-2 py-1"><?= count($categories) ?> Total</span>
                </div>
                <div class="card-body p-0">
                  <ul class="list-group list-group-flush small">
                    <?php foreach ($categories as $cat): ?>
                      <li class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                        <span class="text-dark fw-semibold"><?= htmlspecialchars($cat["category_name"]) ?></span>
                        <span class="badge bg-light text-muted border">#<?= htmlspecialchars($cat["category_id"]) ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>

            </div>

          </div>

        </main>
      </div>
    </div>

    <!-- Bootstrap 5 JavaScript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  </body>
</html>

