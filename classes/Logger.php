<?php
class Logger {
    public static function log($db, $action, $details = null) {
        //If the user is not connected, no insertion in the database
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $userId = $_SESSION['user_id'];
        
        try {
            // Insert into the database, the action taken by the user, details about it and is id
            $stmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $action, $details]);
        } catch (Exception $e) {
        }
    }
}
?>