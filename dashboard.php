<?php
/**
 * dashboard.php - Staff Project Management Hub
 * Protected by authentication guard.
 * Displays project list, status summaries, and CRUD actions.
 */

session_start();

// Include database connection
require_once("db.php");

// Auth Guard: Redirect unauthenticated users to login page
if (!isset($_SESSION["user_id"])) {
  $_SESSION["flash_error"] = "Please sign in to access your dashboard.";
  header("Location: login.php");
  exit;
}

$staff_id = $_SESSION["user_id"];
$full_name = $_SESSION["full_name"] ?? "Staff Member";
$user_name = $_SESSION["user_name"] ?? "";
$email = $_SESSION["email"] ?? "";

// Retrieve and clear flash notification messages
$flash_success = $_SESSION["flash_success"] ?? "";
$flash_error = $_SESSION["flash_error"] ?? "";
unset($_SESSION["flash_success"], $_SESSION["flash_error"]);

// Fetch projects belonging to this staff member with category name
$projects = [];
try {
  $stmt = $conn->prepare("SELECT p.project_id, p.staff_id, p.category_id, p.title, p.project_description, p.status, p.created_at, c.category_name 
                           FROM project p 
                           LEFT JOIN category c ON p.category_id = c.category_id 
                           WHERE p.staff_id = ? 
                           ORDER BY p.created_at DESC");
  $stmt->execute([$staff_id]);
  $projects = $stmt->fetchAll();
  $stmt = null;
} catch (PDOException $e) {
  $flash_error = "Error loading projects: " . $e->getMessage();
}

// Calculate Summary Statistics
$total_projects = count($projects);
$in_progress_count = 0;
$completed_count = 0;
$pending_count = 0;

foreach ($projects as $p) {
  $status_lower = strtolower($p["status"] ?? "");
  if ($status_lower === "completed") {
    $completed_count++;
  } elseif ($status_lower === "in progress") {
    $in_progress_count++;
  } else {
    $pending_count++;
  }
}

// Fetch recent activity audit trail for this staff member
$recent_activities = [];
try {
  $actStmt = $conn->prepare("SELECT activity_id, activity_notes, logged_at FROM activity_log WHERE staff_id = ? ORDER BY logged_at DESC LIMIT 6");
  $actStmt->execute([$staff_id]);
  $recent_activities = $actStmt->fetchAll();
  $actStmt = null;
} catch (PDOException $e) {
  // If activity_log table has not been created yet in MySQL, fail gracefully
}

// Fetch all tasks for this staff member's projects
$tasks_by_project = [];
try {
  $taskStmt = $conn->prepare("SELECT t.task_id, t.project_id, t.task_name, t.is_completed 
                               FROM task t 
                               JOIN project p ON t.project_id = p.project_id 
                               WHERE p.staff_id = ? 
                               ORDER BY t.task_id ASC");
  $taskStmt->execute([$staff_id]);
  $all_tasks = $taskStmt->fetchAll();
  $taskStmt = null;

  foreach ($all_tasks as $t) {
    $tasks_by_project[$t['project_id']][] = $t;
  }
} catch (PDOException $e) {
  // Graceful fail if task table not created yet
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard · Staff Project Portal</title>

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
              <a class="nav-link active d-flex align-items-center gap-2" href="dashboard.php">
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

      <!-- Main Dashboard Content -->
      <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

        <!-- Flash Notifications -->
        <?php if (!empty($flash_success)): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($flash_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if (!empty($flash_error)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($flash_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <!-- Header & Action Button -->
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-3 mb-4 border-bottom">
          <div>
            <h1 class="h2 brand-gradient-text mb-1">Projects Overview</h1>
            <p class="text-muted small mb-0">Welcome back, <?= htmlspecialchars($full_name) ?>! Here is what's happening
              with your projects.</p>
          </div>
          <div class="btn-toolbar mb-2 mb-md-0 mt-3 mt-md-0">
            <a href="project_create.php" class="btn btn-primary d-flex align-items-center gap-2 shadow-sm">
              <i class="bi bi-plus-lg"></i>
              <span>Create New Project</span>
            </a>
          </div>
        </div>

        <!-- Quick Stat Cards (Color Palette Coordinated) -->
        <div class="row g-3 mb-4">
          <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card stat-primary">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-muted small text-uppercase fw-bold">Total Projects</div>
                  <div class="fs-3 fw-bold text-dark mt-1"><?= $total_projects ?></div>
                </div>
                <div class="rounded-circle p-3"
                  style="background-color: rgba(33, 118, 174, 0.12); color: var(--bright-teal-blue);">
                  <i class="bi bi-folder2-open fs-4"></i>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card stat-warning">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-muted small text-uppercase fw-bold">In Progress</div>
                  <div class="fs-3 fw-bold text-dark mt-1"><?= $in_progress_count ?></div>
                </div>
                <div class="rounded-circle p-3"
                  style="background-color: rgba(251, 177, 60, 0.15); color: var(--honey-bronze);">
                  <i class="bi bi-hourglass-split fs-4"></i>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-sm-6 col-xl-4">
            <div class="stat-card stat-success">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="text-muted small text-uppercase fw-bold">Completed</div>
                  <div class="fs-3 fw-bold text-dark mt-1"><?= $completed_count ?></div>
                </div>
                <div class="rounded-circle p-3"
                  style="background-color: rgba(87, 184, 255, 0.2); color: var(--bright-teal-blue);">
                  <i class="bi bi-check2-circle fs-4"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Projects Table Card -->
        <div class="card border-0 shadow-sm rounded-3">
          <div
            class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0 fw-bold" style="color: var(--bright-teal-blue);">
              <i class="bi bi-table me-2"></i>Project Records
            </h5>

            <div class="d-flex align-items-center gap-2">
              <!-- Live Search Bar -->
              <div class="input-group input-group-sm" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="projectSearch" class="form-control border-start-0"
                  placeholder="Filter projects...">
              </div>

              <span class="badge bg-light text-dark border">
                <?= $total_projects ?> <?= $total_projects === 1 ? 'Record' : 'Records' ?>
              </span>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="projectTable">
              <thead>
                <tr>
                  <th scope="col" style="width: 70px;">#ID</th>
                  <th scope="col">Title</th>
                  <th scope="col">Category</th>
                  <th scope="col" style="max-width: 230px;">Description</th>
                  <th scope="col">Status</th>
                  <th scope="col">Subtasks</th>
                  <th scope="col">Created Date</th>
                  <th scope="col" class="text-end" style="min-width: 150px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($projects)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-5">
                      <div class="py-4">
                        <i class="bi bi-folder-x fs-1 text-muted"></i>
                        <h6 class="mt-3 fw-bold text-dark">No Projects Found</h6>
                        <p class="text-muted small mb-3">You haven't created any projects yet.</p>
                        <a href="project_create.php" class="btn btn-sm btn-primary">
                          <i class="bi bi-plus-lg me-1"></i>Create First Project
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($projects as $project): ?>
                    <?php
                    $pid = $project["project_id"];
                    $p_tasks = $tasks_by_project[$pid] ?? [];
                    $total_t = count($p_tasks);
                    $done_t = count(array_filter($p_tasks, fn($t) => $t["is_completed"] == 1));
                    ?>
                    <tr>
                      <td class="fw-bold text-muted">#<?= htmlspecialchars($pid) ?></td>
                      <td>
                        <strong class="text-dark"><?= htmlspecialchars($project["title"]) ?></strong>
                      </td>
                      <td>
                        <span class="badge badge-sky px-2 py-1">
                          <i class="bi bi-tag-fill me-1 small"></i>
                          <?= htmlspecialchars($project["category_name"] ?? (!empty($project["category_id"]) ? 'Cat #' . $project["category_id"] : 'Uncategorized')) ?>
                        </span>
                      </td>
                      <td class="text-muted small text-truncate" style="max-width: 230px;"
                        title="<?= htmlspecialchars($project["project_description"] ?? '') ?>">
                        <?= htmlspecialchars($project["project_description"] ?? 'No description provided.') ?>
                      </td>
                      <td>
                        <?php
                        $status = $project["status"] ?? "Pending";
                        $badgeClass = "badge-copper";
                        if (strtolower($status) === "completed") {
                          $badgeClass = "badge-sky";
                        } elseif (strtolower($status) === "in progress") {
                          $badgeClass = "badge-honey";
                        }
                        ?>
                        <span class="badge <?= $badgeClass ?> px-2 py-1">
                          <?= htmlspecialchars($status) ?>
                        </span>
                      </td>
                      <td>
                        <button type="button"
                          class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 py-1 px-2"
                          data-bs-toggle="modal" data-bs-target="#taskModal-<?= $pid ?>" title="Manage Subtasks">
                          <i class="bi bi-list-check" style="color: var(--bright-teal-blue);"></i>
                          <span class="small fw-bold"><?= $done_t ?>/<?= $total_t ?></span>
                        </button>
                      </td>
                      <td class="text-muted small">
                        <?= date("M d, Y", strtotime($project["created_at"])) ?>
                      </td>
                      <td class="text-end">
                        <div class="btn-group btn-group-sm">
                          <a href="project_edit.php?id=<?= urlencode($project["project_id"]) ?>"
                            class="btn btn-outline-primary" title="Edit Project">
                            <i class="bi bi-pencil-square"></i> Edit
                          </a>
                          <a href="project_delete.php?id=<?= urlencode($project["project_id"]) ?>"
                            class="btn btn-outline-danger" title="Delete Project"
                            onclick="return confirm('Are you sure you want to permanently delete this project: <?= htmlspecialchars(addslashes($project['title'])) ?>?');">
                            <i class="bi bi-trash"></i> Delete
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Recent Activity Audit Trail Card -->
        <div class="card border-0 shadow-sm rounded-3 mt-4">
          <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold" style="color: var(--bright-teal-blue);">
              <i class="bi bi-clock-history me-2"></i>Recent Activity (Audit Trail)
            </h5>
            <span class="badge bg-light text-muted border">Automated</span>
          </div>
          <div class="card-body p-0">
            <?php if (empty($recent_activities)): ?>
              <div class="p-4 text-center text-muted small">
                <i class="bi bi-journal-text fs-3 d-block mb-2 text-muted"></i>
                No automated activity recorded yet. Perform actions like creating or editing projects to see your audit
                log!
              </div>
            <?php else: ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($recent_activities as $act): ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                    <div class="d-flex align-items-center gap-3">
                      <div class="rounded-circle p-2 d-flex align-items-center justify-content-center"
                        style="background-color: rgba(33, 118, 174, 0.1); color: var(--bright-teal-blue); width: 36px; height: 36px;">
                        <i class="bi bi-activity"></i>
                      </div>
                      <div>
                        <div class="text-dark fw-medium small mb-0"><?= htmlspecialchars($act["activity_notes"]) ?></div>
                        <div class="text-muted" style="font-size: 0.75rem;">Log
                          #<?= htmlspecialchars($act["activity_id"]) ?></div>
                      </div>
                    </div>
                    <span class="text-muted small" style="font-size: 0.8rem;">
                      <i class="bi bi-calendar3 me-1"></i><?= date("M d, Y h:i A", strtotime($act["logged_at"])) ?>
                    </span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>

      </main>
    </div>
  </div>

  <!-- ================= SUBTASK MODALS (One per project) ================= -->
  <?php foreach ($projects as $project): ?>
    <?php
    $pid = $project['project_id'];
    $p_tasks = $tasks_by_project[$pid] ?? [];
    $total_t = count($p_tasks);
    $done_t = count(array_filter($p_tasks, fn($t) => $t["is_completed"] == 1));
    $pct_t = $total_t > 0 ? round(($done_t / $total_t) * 100) : 0;
    ?>
    <div class="modal fade" id="taskModal-<?= $pid ?>" tabindex="-1" aria-labelledby="taskModalLabel-<?= $pid ?>"
      aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">

          <!-- Modal Header -->
          <div class="modal-header border-bottom py-3 px-4">
            <div>
              <h5 class="modal-title fw-bold" id="taskModalLabel-<?= $pid ?>" style="color: var(--bright-teal-blue);">
                <i class="bi bi-list-task me-2"></i><?= htmlspecialchars($project['title']) ?>
              </h5>
              <div class="text-muted small">Subtasks & Progress Tracking</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <!-- Modal Body -->
          <div class="modal-body p-4">

            <!-- Progress Indicator -->
            <div class="d-flex justify-content-between align-items-center mb-2 small">
              <span class="fw-semibold text-dark">Progress:</span>
              <span class="badge badge-sky px-2 py-1"><?= $done_t ?> of <?= $total_t ?> Completed (<?= $pct_t ?>%)</span>
            </div>
            <div class="progress mb-4" style="height: 8px;">
              <div class="progress-bar" role="progressbar"
                style="width: <?= $pct_t ?>%; background-color: var(--bright-teal-blue);" aria-valuenow="<?= $pct_t ?>"
                aria-valuemin="0" aria-valuemax="100"></div>
            </div>

            <!-- Subtasks Checklist -->
            <h6 class="fw-bold small text-muted text-uppercase mb-2">Checklist</h6>
            <?php if (empty($p_tasks)): ?>
              <div class="p-3 text-center text-muted small bg-light rounded-3 mb-3">
                <i class="bi bi-clipboard2-check fs-4 d-block mb-1 text-muted"></i>
                No subtasks added yet. Type your first subtask below!
              </div>
            <?php else: ?>
              <div class="list-group list-group-flush border rounded-3 mb-3">
                <?php foreach ($p_tasks as $task): ?>
                  <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">

                    <!-- Toggle Status Checkbox Form -->
                    <form action="task_action.php" method="POST" class="d-flex align-items-center gap-2 mb-0 flex-grow-1">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="project_id" value="<?= $pid ?>">
                      <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">

                      <input class="form-check-input mt-0" type="checkbox" id="taskCheck-<?= $task['task_id'] ?>"
                        <?= $task['is_completed'] ? 'checked' : '' ?> onchange="this.form.submit()" style="cursor: pointer;">
                      <label
                        class="form-check-label mb-0 small <?= $task['is_completed'] ? 'text-decoration-line-through text-muted' : 'text-dark fw-medium' ?>"
                        for="taskCheck-<?= $task['task_id'] ?>" style="cursor: pointer;">
                        <?= htmlspecialchars($task['task_name']) ?>
                      </label>
                    </form>

                    <!-- Delete Subtask Form -->
                    <form action="task_action.php" method="POST" class="mb-0 ms-2">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="project_id" value="<?= $pid ?>">
                      <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="Delete subtask"
                        onclick="return confirm('Remove subtask: <?= htmlspecialchars(addslashes($task['task_name'])) ?>?');">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>

                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <!-- Add New Subtask Inline Form -->
            <div class="card bg-light border-0 p-3 rounded-3 mt-3">
              <h6 class="fw-bold small text-dark mb-2">
                <i class="bi bi-plus-circle-fill text-primary me-1"></i> Add New Subtask
              </h6>
              <form action="task_action.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="project_id" value="<?= $pid ?>">
                <div class="input-group">
                  <input type="text" name="task_name" class="form-control"
                    placeholder="e.g. Design wireframes, test database..." required autocomplete="off">
                  <button type="submit" class="btn btn-primary d-flex align-items-center gap-1">
                    <i class="bi bi-plus-lg"></i> Add
                  </button>
                </div>
              </form>
            </div>

          </div>

          <!-- Modal Footer -->
          <div class="modal-footer border-top py-2 px-4">
            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
          </div>

        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Bootstrap 5 JavaScript Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Main Script with Cache-Buster to prevent stale browser cache -->
  <script src="main.js?v=<?= time() ?>"></script>

  <!-- Auto-reopen modal if redirected from task action -->
  <?php if (!empty($_GET['open_modal'])): ?>
    <script>
      document.addEventListener("DOMContentLoaded", () => {
        const modalEl = document.getElementById("taskModal-<?= (int) $_GET['open_modal'] ?>");
        if (modalEl) {
          const modalInstance = new bootstrap.Modal(modalEl);
          modalInstance.show();
        }
      });
    </script>
  <?php endif; ?>
</body>

</html>