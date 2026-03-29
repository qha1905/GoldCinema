<?php
// File: includes/db_connect.php

$host = 'sql208.infinityfree.com';
$dbname = 'if0_41322676_cinemaweb';
$username = 'if0_41322676';
$password = 'be9NATHNTsf';

try {
    // Tạo kết nối PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Thiết lập chế độ báo lỗi của PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Trả về dữ liệu dạng mảng kết hợp (associative array)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}
?>