<?php
session_start(); // بدء الجلسة الحالية

// حذف جميع متغيرات الجلسة
$_SESSION = array();

// تدمير ملف تعريف الارتباط الخاص بالجلسة (Cookie) إن وُجد لضمان الامان التام
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة نهائياً من الخادم
session_destroy();

// التوجيه إلى صفحة تسجيل الدخول
header("Location: index.html");
exit();
?>