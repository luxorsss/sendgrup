<?php
// Pastikan path ke db_connect dan functions benar (sesuaikan jika perlu)
require_once(__DIR__ . '/config/db_connect.php');
require_once(__DIR__ . '/includes/functions.php');

// Mencegah script terhenti jika proses pengiriman memakan waktu lama
set_time_limit(0);

// =========================================================================
// ## FUNGSI SPINTAX UNTUK VARIASI TEKS (ANTI-SPAM) ##
// =========================================================================
if (!function_exists('parse_spintax')) {
    function parse_spintax($text) {
        return preg_replace_callback('/\{(((?>[^\{\}]+)|(?R))*)\}/x', function ($match) {
            $text = parse_spintax($match[1]);
            $parts = explode('|', $text);
            return $parts[array_rand($parts)];
        }, $text);
    }
}

// Ambil Hari dan Jam saat skrip ini dieksekusi oleh Cron Job
$current_day = date('l'); // Hasil: 'Monday', 'Tuesday', dll
$current_time = date('H:i'); // Hasil format jam-menit, contoh: '06:00'

echo "============================================\n";
echo "Cron Berjalan: $current_day, $current_time\n";
echo "============================================\n";

// 1. Cari jadwal yang cocok dengan hari dan jam SAAT INI (Toleransi 1 menit)
$query_schedules = "
    SELECT s.automation_list_id, l.list_name 
    FROM automation_schedules s
    JOIN automation_lists l ON s.automation_list_id = l.id
    WHERE s.send_day = '$current_day' 
    AND DATE_FORMAT(s.send_time, '%H:%i') = '$current_time'
    AND l.is_active = 1
";
$result_schedules = mysqli_query($conn, $query_schedules);

if (mysqli_num_rows($result_schedules) == 0) {
    die("Tidak ada jadwal automasi yang aktif pada waktu ini. Selesai.\n");
}

// 2. Loop melalui Kelompok Automasi yang jadwalnya tembus
while ($schedule = mysqli_fetch_assoc($result_schedules)) {
    $list_id = $schedule['automation_list_id'];
    $list_name = $schedule['list_name'];
    echo "\n=> Memproses Kelompok Automasi: '$list_name' (ID: $list_id)\n";

    // Ambil semua template yang tergabung di kelompok ini
    $query_templates = "SELECT id, message_content, image_url FROM message_templates WHERE automation_list_id = $list_id";
    $result_templates = mysqli_query($conn, $query_templates);
    $all_templates = [];
    while ($t = mysqli_fetch_assoc($result_templates)) {
        $all_templates[] = $t;
    }

    if (empty($all_templates)) {
        echo " - [LEWATI] Tidak ada template yang ditugaskan ke kelompok ini.\n";
        continue;
    }

    // Ambil semua grup yang menjadi target di kelompok ini
    $query_groups = "
        SELECT ag.group_id, wg.group_wa_id, wn.api_url, wn.api_key, wn.account_name, wg.group_name
        FROM automation_groups ag
        JOIN whatsapp_groups wg ON ag.group_id = wg.id
        JOIN whatsapp_numbers wn ON wg.whatsapp_number_id = wn.id
        WHERE ag.automation_list_id = $list_id AND wn.active = 1
    ";
    $result_groups = mysqli_query($conn, $query_groups);

    // 3. Loop untuk mengirim ke masing-masing grup
    while ($group = mysqli_fetch_assoc($result_groups)) {
        $group_id = $group['group_id'];
        $group_wa_id = $group['group_wa_id'];
        $group_name = $group['group_name'];
        
        // CEK LOG: Template mana saja yang SUDAH pernah dikirim ke grup ini dari kelompok ini?
        $query_logs = "SELECT template_id FROM automation_logs WHERE automation_list_id = $list_id AND group_id = $group_id";
        $result_logs = mysqli_query($conn, $query_logs);
        $sent_template_ids = [];
        while ($log = mysqli_fetch_assoc($result_logs)) {
            $sent_template_ids[] = $log['template_id'];
        }

        // FILTER: Buang template yang sudah terkirim
        $available_templates = [];
        foreach ($all_templates as $tpl) {
            if (!in_array($tpl['id'], $sent_template_ids)) {
                $available_templates[] = $tpl;
            }
        }

        // OPSI A: Jika semua template sudah terkirim, SKIP grup ini.
        if (empty($available_templates)) {
            echo "   -> Grup '$group_name' sudah menerima SEMUA pesan. (SKIP)\n";
            continue;
        }

        // ACAK: Pilih 1 template dari yang tersisa
        $random_index = array_rand($available_templates);
        $selected_template = $available_templates[$random_index];
        $template_id = $selected_template['id'];
        
        // Terapkan SPINTAX ke template terpilih
        $message_content = parse_spintax($selected_template['message_content']);
        $image_url = $selected_template['image_url'];

        // EKSEKUSI API ONESENDER
        echo "   -> Mengirim Template ID $template_id ke Grup '$group_name'...\n";
        $response = send_whatsapp_message($group['api_url'], $group['api_key'], $group_wa_id, $message_content, $image_url);

        // Jika berhasil merespons tanpa error curl
        if (!isset($response['error'])) {
            // CATAT KE JEJAK DIGITAL (Log)
            $insert_log = "INSERT INTO automation_logs (automation_list_id, group_id, template_id) VALUES ($list_id, $group_id, $template_id)";
            mysqli_query($conn, $insert_log);
            echo "      [SUKSES] Pesan terkirim dan jejak dicatat.\n";
        } else {
            echo "      [GAGAL] " . $response['error'] . "\n";
        }

        // JEDA ACAK ANTI-BANNED WA (5 hingga 15 detik antar pengiriman)
        // $delay = rand(5, 15);
        // echo "      Menunggu jeda aman $delay detik...\n";
        // sleep($delay);
    }
}
echo "\n============================================\n";
echo "Semua antrean automasi pada jam ini selesai.\n";
echo "============================================\n";
?>