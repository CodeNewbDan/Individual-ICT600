<?php
/**
 * task_action.php - Handles Subtask CRUD Actions
 * Supports: add, toggle, and delete
 * Enforces staff ownership and automatically logs every action.
 */

session_start();

// Include database (auto-loads logger.php)
require_once("db.php");

// Auth Guard: Redirect unauthenticated users
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$staff_id = $_SESSION["user_id"];

// Only handle POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: dashboard.php");
    exit;
}

$action = trim($_POST["action"] ?? "");
$project_id = filter_input(INPUT_POST, "project_id", FILTER_VALIDATE_INT);
$return_to = trim($_POST["return_to"] ?? "dashboard");

// Helper function to handle redirection back to either edit page or dashboard
function redirectTask($return_to, $project_id) {
    if ($return_to === "edit" && $project_id) {
        header("Location: project_edit.php?id=" . $project_id);
    } else {
        header("Location: dashboard.php" . ($project_id ? "?open_modal=" . $project_id : ""));
    }
    exit;
}

// --- 1. ACTION: ADD TASK ---
if ($action === "add") {
    $task_name = trim(filter_input(INPUT_POST, "task_name", FILTER_SANITIZE_SPECIAL_CHARS) ?? "");

    if (!$project_id || empty($task_name)) {
        $_SESSION["flash_error"] = "Please provide a valid task name.";
        redirectTask($return_to, $project_id);
    }

    try {
        // Verify project belongs to logged-in staff member
        $projStmt = $conn->prepare("SELECT project_id, title FROM project WHERE project_id = ? AND staff_id = ? LIMIT 1");
        $projStmt->execute([$project_id, $staff_id]);
        $project = $projStmt->fetch();

        if ($project) {
            // Insert new task with is_completed = 0
            $insertStmt = $conn->prepare("INSERT INTO task (project_id, task_name, is_completed) VALUES (?, ?, 0)");
            $insertStmt->execute([$project_id, $task_name]);

            // Automated Audit Trail
            logActivity($conn, $staff_id, "Added subtask '" . $task_name . "' to project '" . $project["title"] . "'");
            $_SESSION["flash_success"] = "Task added successfully.";
        } else {
            $_SESSION["flash_error"] = "Unauthorized project access.";
        }
    } catch (PDOException $e) {
        $_SESSION["flash_error"] = "Database error: " . $e->getMessage();
    }

    redirectTask($return_to, $project_id);
}

// --- 2. ACTION: TOGGLE TASK STATUS ---
if ($action === "toggle") {
    $task_id = filter_input(INPUT_POST, "task_id", FILTER_VALIDATE_INT);

    if (!$task_id || !$project_id) {
        redirectTask($return_to, $project_id);
    }

    try {
        // Verify task and project ownership
        $stmt = $conn->prepare("SELECT t.task_id, t.task_name, t.is_completed, p.project_id, p.title 
                               FROM task t 
                               JOIN project p ON t.project_id = p.project_id 
                               WHERE t.task_id = ? AND p.staff_id = ? LIMIT 1");
        $stmt->execute([$task_id, $staff_id]);
        $task = $stmt->fetch();

        if ($task) {
            $new_status = $task["is_completed"] ? 0 : 1;
            $update = $conn->prepare("UPDATE task SET is_completed = ? WHERE task_id = ?");
            $update->execute([$new_status, $task_id]);

            $status_text = ($new_status === 1) ? "completed" : "reopened (pending)";
            logActivity($conn, $staff_id, "Marked subtask '" . $task["task_name"] . "' as " . $status_text . " in project '" . $task["title"] . "'");
            $_SESSION["flash_success"] = "Task status updated.";
        }
    } catch (PDOException $e) {
        $_SESSION["flash_error"] = "Database error: " . $e->getMessage();
    }

    redirectTask($return_to, $project_id);
}

// --- 3. ACTION: DELETE TASK ---
if ($action === "delete") {
    $task_id = filter_input(INPUT_POST, "task_id", FILTER_VALIDATE_INT);

    if (!$task_id || !$project_id) {
        redirectTask($return_to, $project_id);
    }

    try {
        // Verify task and project ownership
        $stmt = $conn->prepare("SELECT t.task_id, t.task_name, p.project_id, p.title 
                               FROM task t 
                               JOIN project p ON t.project_id = p.project_id 
                               WHERE t.task_id = ? AND p.staff_id = ? LIMIT 1");
        $stmt->execute([$task_id, $staff_id]);
        $task = $stmt->fetch();

        if ($task) {
            $del = $conn->prepare("DELETE FROM task WHERE task_id = ?");
            $del->execute([$task_id]);

            logActivity($conn, $staff_id, "Removed subtask '" . $task["task_name"] . "' from project '" . $task["title"] . "'");
            $_SESSION["flash_success"] = "Task deleted.";
        }
    } catch (PDOException $e) {
        $_SESSION["flash_error"] = "Database error: " . $e->getMessage();
    }

    redirectTask($return_to, $project_id);
}

// Default fallback
redirectTask($return_to, $project_id);

