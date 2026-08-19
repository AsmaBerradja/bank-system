<?php
session_start();
include 'db.php';

// Check if the user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: Login.html");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and retrieve input values
    $account_number = trim($_POST['account_number']);
    $amount = floatval($_POST['amount']);

    // Validate inputs
    if (empty($account_number) || $amount <= 0) {
        echo "<script>alert('Error: Please enter a valid account number and a positive amount.'); window.location.href='admin_dashboard.php';</script>";
        exit();
    }

    try {
        // 1. Search for the client using the 10-digit account number
        $stmt = $pdo->prepare("SELECT * FROM users WHERE account_number = ?");
        $stmt->execute([$account_number]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($client) {
            $client_id = $client['id'];

            // 2. Update the client's balance in the users table
            $new_balance = $client['balance'] + $amount;
            $update_stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $update_stmt->execute([$new_balance, $client_id]);

            // 3. Insert a record into the transactions table
            $description = "Cash deposit processed by Admin";
            $type = "deposit";
            $trans_stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, description, type, date) VALUES (?, ?, ?, ?, NOW())");
            $trans_stmt->execute([$client_id, $amount, $description, $type]);

            // Success alert and redirection
            echo "<script>alert('Success: Amount successfully deposited to account number: " . htmlspecialchars($account_number) . "'); window.location.href='admin_dashboard.php';</script>";
            exit();

        } else {
            // Error if account number does not exist in the database
            echo "<script>alert('Error: The account number entered does not exist in the system.'); window.location.href='admin_dashboard.php';</script>";
            exit();
        }

    } catch (PDOException $e) {
        // Database exception handling
        echo "Database Error: " . $e->getMessage();
    }
} else {
    header("Location: admin_dashboard.php");
    exit();
}
?>