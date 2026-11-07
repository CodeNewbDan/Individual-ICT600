<?php
/**
 * logger.php - Automated Audit Trail Logger
 * Automatically records staff actions into the 'activity_log' database table.
 */

if (!function_exists('logActivity')) {
    /**
     * Records an automated activity log entry.
     *
     * @param PDO $conn The active database PDO connection
     * @param int|null $staff_id The ID of the staff performing the action
     * @param string $activity_notes Clear description of what action occurred
     * @return bool True if logged successfully, false otherwise
     */
    function logActivity($conn, $staff_id, $activity_notes) {
        if (!$conn) {
            return false;
        }

        try {
            $stmt = $conn->prepare("INSERT INTO activity_log (staff_id, activity_notes, logged_at) VALUES (?, ?, NOW())");
            $success = $stmt->execute([$staff_id, $activity_notes]);
            $stmt = null;
            return $success;
        } catch (PDOException $e) {
            // Fail silently so an audit log error never breaks the main user experience
            error_log("Audit Trail Error: " . $e->getMessage());
            return false;
        }
    }
}
