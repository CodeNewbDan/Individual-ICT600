<?php
/**
 * register.php - for single user/multiuser registration with dual layer validation
 * Client-side JavaScript and Server-Side PHP
 * Password hashing and prepared statements
 * 
 */

//start session
session_start();

//Include the db.php file
require_once("db.php");

//Redirect to dashboard if user is already logged in
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

// Initialize notification array and field data retention variables
$errors = [];
$success = "";
$user_name = "";
$full_name = "";
$email = "";

// Handle FORM Submission (POST Requests)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // SERVER-SIDE INPUT CLEANUP AND RETRIEVAL
    $user_name = trim(filter_input(INPUT_POST, 'user_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $raw_email = trim($_POST['email'] ?? '');
    $email = filter_var($raw_email, FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';


    // SERVER-SIDE INPUT VALIDATION
    //Validate username
    if (empty($user_name)) {
        $errors[] = "Username is required";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $user_name)) {
        $errors[] = "Username must be 3-30 characters long and contain only letters, numbers, and underscores.";

    }

    //Validate full name
    if (empty($full_name)) {
        $errors[] = "Full Name is required";

    } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
        $errors[] = "Full Name must be between 2 and 100 characters";
    }

    //Validate email address
    if (empty($raw_email)) {
        $errors[] = "Email Address is required";
    } elseif (!$email) {
        $errors[] = "Please provide a valid email address (e.g.,  user@example.com)";
    }

    //Validate Password Strength
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Passwords must be 8 characters long at least";
    } elseif (!preg_match("/[A-Z]/", $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter and one number.";
    }

    //Validate Confirm Password Match
    if ($password != $confirm_password) {
        $errors[] = "Password does not match";
    }

    // DATABASE VERIFICATION AND REGISTRATION PROCESSING
    if (empty($errors)) {
        try {
            // Check if username or email exists in MySQL
            $checkStmt = $conn->prepare("SELECT staff_id, user_name, email FROM staff WHERE user_name = ? OR email = ? LIMIT 1");
            $checkStmt->execute([$user_name, $email]);
            $existingUser = $checkStmt->fetch();

            if ($existingUser) {
                if ($existingUser['user_name'] === $user_name) {
                    $errors[] = "Username is already taken.";
                }
                if ($existingUser['email'] === $email) {
                    $errors[] = "Email is already registered.";
                }
            }

            //Proceed to insert a new user record if no duplicates
            if (empty($errors)) {
                //Secure password hashing using modern default algorithm 
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                //Insert into InnoDB 'staff' table using Prepared Statement
                $insertStmt = $conn->prepare("INSERT INTO staff(user_name, full_name, email, password) VALUES(?, ?, ?, ?)");
                $insertStmt->execute([$user_name, $full_name, $email, $hashed_password]);

                if ($insertStmt->rowCount() > 0) {
                    // Set session manage and redirect to login page
                    $_SESSION["flash_success"] = "Registration successful! You can now log in with your credentials";
                    header("Location: login.php");
                    exit;
                } else {
                    $errors[] = "System Error: Failed to register. Please Try Again.";
                }
                $insertStmt = null; // Explicit close statements (Optional but still helps to understand)
            }


        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }


}
?>