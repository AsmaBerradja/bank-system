<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username       = $_POST['username'];
    $full_name      = $_POST['full_name'];
    $nin            = $_POST['nin'];
    $dob            = $_POST['dob'];
    $place_of_birth = $_POST['place_of_birth'];
    $phone          = $_POST['phone'];
    $email          = $_POST['email'];
    $password       = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // 1. توليد رقم حساب فريد مكون من 10 أرقام
    $account_number = mt_rand(1000000000, 9999999999);
    
    // التأكد من أن الرقم غير مكرر في قاعدة البيانات
    try {
        $check = $pdo->prepare("SELECT * FROM users WHERE account_number = ?");
        $check->execute([$account_number]);
        while ($check->rowCount() > 0) {
            $account_number = mt_rand(1000000000, 9999999999);
            $check->execute([$account_number]);
        }

        // 2. إدخال البيانات مع رقم الحساب الجديد
        $stmt = $pdo->prepare("INSERT INTO users (username, full_name, nin, dob, place_of_birth, phone, email, password, account_number, role, balance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'client', 0.00)");
        $stmt->execute([$username, $full_name, $nin, $dob, $place_of_birth, $phone, $email, $password, $account_number]);
        
        echo "Success: Account created successfully! Your Account Number is: " . $account_number;
        
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>