<?php
/**
 * logout.php - Secure Session Termination
 * Clears all session data, destroys session cookie, and redirects to login
 */

session_start();

// Include database to record logout activity
require_once("db.php");

// Automated Audit Trail
if (!empty($_SESSION["user_id"])) {
    logActivity($conn, $_SESSION["user_id"], "Staff signed out of the system");
}

// Unset all session variables
$_SESSION = [];

// Delete the session cookie if it exists
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

// Destroy session on the server
session_destroy();

// Start a brief new session just to flash the logout message to the user
session_start();
$_SESSION["flash_success"] = "You have been logged out successfully.";

// Redirect to login page
header("Location: login.php");
exit;

