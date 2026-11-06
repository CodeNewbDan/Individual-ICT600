<?php
/**
 * project_delete.php - Securely Delete a Project
 * Checks authentication and enforces staff ownership before deletion.
 */

session_start();

// Include database connection
require_once("db.php");

// Auth Guard: Redirect unauthenticated users
if (!isset($_SESSION["user_id"])) {
    $_SESSION["flash_error"] = "Please sign in to delete projects.";
    header("Location: login.php");
    exit;
}

$staff_id = $_SESSION["user_id"];

// Validate incoming Project ID
$project_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$project_id) {
    $_SESSION["flash_error"] = "Invalid project ID.";
    header("Location: dashboard.php");
    exit;
}

try {
    // 1. Verify that the project exists and belongs to the currently logged-in staff member
    $checkStmt = $conn->prepare("SELECT title FROM project WHERE project_id = ? AND staff_id = ? LIMIT 1");
    $checkStmt->execute([$project_id, $staff_id]);
    $project = $checkStmt->fetch();

    if ($project) {
        // 2. Perform secure deletion strictly scoped to staff_id
        $deleteStmt = $conn->prepare("DELETE FROM project WHERE project_id = ? AND staff_id = ?");
        $deleteStmt->execute([$project_id, $staff_id]);

        if ($deleteStmt->rowCount() > 0) {
            $_SESSION["flash_success"] = "Project '" . htmlspecialchars($project["title"]) . "' was deleted successfully.";
        } else {
            $_SESSION["flash_error"] = "System error: Failed to delete the project. Please try again.";
        }
        $deleteStmt = null;
    } else {
        $_SESSION["flash_error"] = "Project not found or you do not have permission to delete it.";
    }
    $checkStmt = null;
} catch (PDOException $e) {
    $_SESSION["flash_error"] = "Database error: " . $e->getMessage();
}

// Redirect back to dashboard
header("Location: dashboard.php");
exit;

