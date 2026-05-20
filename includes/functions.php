<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clean input data to prevent SQL injection
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = mysqli_real_escape_string($conn, $data);
    return $data;
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function check_login() {
    if (!is_logged_in()) {
        header("Location: ../login.php");
        exit;
    }
}

// Flash messages
function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function display_flash_message() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];
        
        echo "<div class='alert alert-{$type} alert-dismissible fade show mt-3' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
        
        unset($_SESSION['flash']);
    }
}

// Fungsi untuk mendapatkan daftar grup dari OneSender API
function get_groups_from_onesender($api_url, $api_key) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $api_url . "/api/groups",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Authorization: Bearer " . $api_key
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return ["error" => "cURL Error #:" . $err];
    } else {
        $result = json_decode($response, true);
        return $result;
    }
}

// Fungsi untuk mengirim pesan melalui OneSender API
function send_whatsapp_message($api_url, $api_key, $recipient, $message, $image_url = null) {
    $curl = curl_init();
    
    // Tentukan tipe penerima (grup atau individu)
    $recipient_type = (strpos($recipient, '@g.us') !== false) ? "group" : "individual";
    
    if ($image_url) {
        // Mengirim pesan dengan gambar
        $postData = [
            "recipient_type" => $recipient_type,
            "to" => $recipient,
            "type" => "image",
            "image" => [
                "link" => $image_url,
                "caption" => $message
            ]
        ];
    } else {
        // Mengirim pesan teks saja
        $postData = [
            "recipient_type" => $recipient_type,
            "to" => $recipient,
            "type" => "text",
            "text" => [
                "body" => $message
            ]
        ];
    }
    
    // Untuk development lokal, tambahkan opsi ini jika ada masalah SSL
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    // Gunakan URL API lengkap tanpa menambahkan /api/v1/messages lagi
    curl_setopt_array($curl, [
        CURLOPT_URL => $api_url, // URL lengkap tanpa tambahan path
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $api_key,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    curl_close($curl);
    
    if ($err) {
        return ["error" => "cURL Error: " . $err];
    } else {
        $result = json_decode($response, true);
        if (!$result) {
            $result = [];
        }
        // Tambahkan http_code dan response mentah ke respons untuk debugging
        $result['http_code'] = $http_code;
        $result['raw_response'] = $response;
        return $result;
    }
}

// Fungsi untuk memeriksa status pengiriman pesan
function check_message_status($api_url, $api_key, $message_id) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => $api_url . "/api/status/" . $message_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "Accept: application/json",
            "Authorization: Bearer " . $api_key
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        return ["error" => "cURL Error #:" . $err];
    } else {
        $result = json_decode($response, true);
        return $result;
    }
}

// Fungsi untuk mendapatkan konten promosi dari grup
function get_group_promotions_content($conn, $group_id) {
    $query = "SELECT p.promotion_content 
              FROM group_promotions gp 
              JOIN promotions p ON gp.promotion_id = p.id 
              WHERE gp.group_id = $group_id AND p.active = 1
              ORDER BY gp.display_order ASC";
    $result = mysqli_query($conn, $query);
    
    $promotions_content = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $promotions_content[] = $row['promotion_content'];
    }
    
    // Jika ada promosi, gabungkan dengan pemisah baris baru ganda
    if (!empty($promotions_content)) {
        return implode("\n\n", $promotions_content);
    }
    
    return null;
}

// Fungsi untuk menguji koneksi API OneSender
function test_api_connection($api_url, $api_key) {
    if (empty($api_url) || empty($api_key)) {
        return [
            'success' => false,
            'message' => 'API URL and API Key are required',
            'details' => null
        ];
    }
    
    $curl = curl_init();
    
    // Disable SSL verification for development
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    // Prepare the request
    curl_setopt_array($curl, [
        CURLOPT_URL => $api_url, // Asumsi URL ini sudah termasuk endpoint lengkap
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET", // Metode GET untuk hanya mengecek koneksi
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $api_key,
            "Content-Type: application/json"
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $info = curl_getinfo($curl);
    
    curl_close($curl);
    
    // Process response
    if ($err) {
        return [
            'success' => false,
            'message' => "Connection failed: $err",
            'details' => $info
        ];
    } else {
        $result = json_decode($response, true);
        
        // Check if response is successful (HTTP 200 OK)
        if ($http_code >= 200 && $http_code < 300) {
            return [
                'success' => true,
                'message' => 'Connection successful! API is responding correctly.',
                'details' => [
                    'response' => $result,
                    'http_code' => $http_code
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => "Connection test failed with HTTP code: $http_code",
                'details' => [
                    'response' => $result,
                    'http_code' => $http_code,
                    'info' => $info
                ]
            ];
        }
    }
}
?>