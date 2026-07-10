<?php
// 1. KUNCI RAHASIA (Sama dengan isi kolom 'Secret' di Webhook GitHub Anda)
$secret_token = 'admin123'; 

// 2. VERIFIKASI HEADER (Validasi resmi dari GitHub)
$hub_signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if (!$hub_signature) {
    http_response_code(403);
    die('Akses ditolak: Signature tidak ditemukan.');
}

list($algo, $hash) = explode('=', $hub_signature, 2);
$payload = file_get_contents('php://input');
$payload_hash = hash_hmac($algo, $payload, $secret_token);

if (!hash_equals($payload_hash, $hash)) {
    http_response_code(403);
    die('Akses ditolak: Token Secret tidak valid.');
}

// 3. DAFTAR PERINTAH OTOMATISASI (Memaksa sinkronisasi bersih)
$commands = [
    'cd ' . __DIR__,
    'git fetch --all 2>&1',
    'git reset --hard origin/main 2>&1',
    'git clean -fd 2>&1'
];

$output = '';
foreach ($commands as $command) {
    $output .= "=> $command\n";
    $output .= shell_exec($command) . "\n";
}

// 4. KIRIM STATUS SUKSES KE GITHUB
http_response_code(200);
echo "<pre>$output</pre>";