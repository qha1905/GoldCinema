<?php
session_start();
require_once 'includes/db_connect.php';

// 1. CẤU HÌNH THÔNG TIN (Nhập thông tin thật của bạn vào đây)
$client_id = 'YOUR_CLIENT_ID';
$client_secret = 'YOUR_CLIENT_SECRET';
$redirect_uri = 'http://localhost/GOLDCINEMA/google_callback.php';

if (isset($_GET['code'])) {
    // 2. Dùng 'code' Google trả về để đổi lấy Access Token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $_GET['code'],
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bỏ qua SSL trên localhost
    
    $response = curl_exec($ch);
    curl_close($ch);
    $token_data = json_decode($response, true);

    if (isset($token_data['access_token'])) {
        // 3. Dùng Access Token để lấy thông tin
        $access_token = $token_data['access_token'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com/oauth2/v2/userinfo');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $access_token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bỏ qua SSL trên localhost
        
        $user_info = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (isset($user_info['email'])) {
            $google_email = $user_info['email'];
            $google_name = $user_info['name'];

            // 4. KIỂM TRA DATABASE VÀ ĐĂNG NHẬP
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$google_email]);
            $user = $stmt->fetch();

            if ($user) {
                // Tình huống A: Email đã tồn tại
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                // ĐÃ FIX: Đổi thành 'fullname'
                $_SESSION['user_name'] = $user['fullname']; 
                $_SESSION['role'] = $user['role'];
                
                header("Location: index.php");
                exit;
            } else {
                // Tình huống B: Khách hàng mới
                $random_password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                
                // ĐÃ FIX: Thêm cột phone và truyền giá trị rỗng '' vào
                $stmt = $pdo->prepare("INSERT INTO users (fullname, email, phone, password, role) VALUES (?, ?, '', ?, 'member')");
                $stmt->execute([$google_name, $google_email, $random_password]);
                
                // Đăng nhập luôn
                $new_user_id = $pdo->lastInsertId();
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['user_name'] = $google_name;
                // ĐÃ FIX: Đổi role thành 'member'
                $_SESSION['role'] = 'member'; 
                
                header("Location: index.php");
                exit;
            }
        }
    }
}

// Nếu có lỗi, đẩy về trang đăng nhập
header("Location: login.php?error=google_auth_failed");
exit;
?>