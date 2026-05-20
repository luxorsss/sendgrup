<?php
// This script should be run via cron job every minute
require_once(dirname(__FILE__) . '/config/db_connect.php');
require_once(dirname(__FILE__) . '/includes/functions.php');

set_time_limit(0);
$current_date = date('Y-m-d');
$current_time = date('H:i:00');
$now = date('Y-m-d H:i:s');

// =========================================================================
// ## QUERY YANG SUDAH DIPERBAIKI ##
// =========================================================================
$query = "SELECT 
            sm.id, 
            sm.template_id,
            sm.is_one_time,
            sm.one_time_content,
            sm.one_time_image_url,
            -- Ambil whatsapp_number_id dari grup pertama yang terkait
            COALESCE(
                (SELECT wg.whatsapp_number_id 
                 FROM scheduled_message_groups smg 
                 JOIN whatsapp_groups wg ON smg.group_id = wg.id 
                 WHERE smg.scheduled_message_id = sm.id 
                 LIMIT 1),
                mt.whatsapp_number_id
            ) AS whatsapp_number_id,
            wn.api_key, 
            wn.api_url,
            mt.message_content as template_content,
            mt.image_url as template_image_url
        FROM 
            scheduled_messages sm
        LEFT JOIN 
            message_templates mt ON sm.template_id = mt.id
        JOIN 
            whatsapp_numbers wn ON wn.id = COALESCE(
                (SELECT wg.whatsapp_number_id 
                 FROM scheduled_message_groups smg 
                 JOIN whatsapp_groups wg ON smg.group_id = wg.id 
                 WHERE smg.scheduled_message_id = sm.id 
                 LIMIT 1),
                mt.whatsapp_number_id
            )
        WHERE 
            sm.schedule_date <= ? 
            AND sm.schedule_time <= ? 
            AND sm.status = 'pending'
            AND wn.active = 1
        LIMIT 5";

$stmt_main = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt_main, "ss", $current_date, $current_time);
mysqli_stmt_execute($stmt_main);
$result = mysqli_stmt_get_result($stmt_main);

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $scheduled_id = $row['id'];
        $api_key = $row['api_key'];
        $api_url = $row['api_url'];
        $whatsapp_number_id = $row['whatsapp_number_id'];
        $template_id = $row['template_id'];
        
        // Tentukan konten berdasarkan tipe pesan
        if ($row['is_one_time'] == 1) {
            $message_content = $row['one_time_content'];
            $image_url = $row['one_time_image_url'];
        } else {
            $message_content = $row['template_content'];
            $image_url = $row['template_image_url'];
        }
        
        // Ambil grup tujuan
        $groups_query = "SELECT wg.id, wg.group_wa_id, wg.group_name
                         FROM scheduled_message_groups smg 
                         JOIN whatsapp_groups wg ON smg.group_id = wg.id 
                         WHERE smg.scheduled_message_id = ?";
        $stmt_groups = mysqli_prepare($conn, $groups_query);
        mysqli_stmt_bind_param($stmt_groups, "i", $scheduled_id);
        mysqli_stmt_execute($stmt_groups);
        $groups_result = mysqli_stmt_get_result($stmt_groups);
        
        $success_count = 0;
        $fail_count = 0;
        
        while ($group = mysqli_fetch_assoc($groups_result)) {
            $group_id = $group['id'];
            $group_wa_id = $group['group_wa_id'];
            
            $promotions_content_text = getGroupPromotions($conn, $group_id);
            $footer_content_text = getGroupFooter($conn, $group_id);
            
            $full_message = $message_content;
            if (!empty($footer_content_text)) {
                $full_message .= "\n\n" . $footer_content_text;
            }
            if (!empty($promotions_content_text)) {
                $full_message .= "\n\n" . $promotions_content_text;
            }
            
            $response = send_whatsapp_message($api_url, $api_key, $group_wa_id, $full_message, $image_url);
            
            if (isset($response['error'])) {
                $fail_count++;
                $error_message = mysqli_real_escape_string($conn, substr($response['error'], 0, 255));
                
                $history_query = "INSERT INTO message_history (whatsapp_number_id, group_id, template_id, message_content, image_url, promotion_content, footer_content, status, error_message, scheduled_id, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'failed', ?, ?, ?)";
                $stmt_history = mysqli_prepare($conn, $history_query);
                mysqli_stmt_bind_param($stmt_history, "iiisssssis", $whatsapp_number_id, $group_id, $template_id, $message_content, $image_url, $promotions_content_text, $footer_content_text, $error_message, $scheduled_id, $now);
                mysqli_stmt_execute($stmt_history);
            } else {
                $success_count++;
                $history_query = "INSERT INTO message_history (whatsapp_number_id, group_id, template_id, message_content, image_url, promotion_content, footer_content, status, scheduled_id, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'sent', ?, ?)";
                $stmt_history = mysqli_prepare($conn, $history_query);
                mysqli_stmt_bind_param($stmt_history, "iiissssis", $whatsapp_number_id, $group_id, $template_id, $message_content, $image_url, $promotions_content_text, $footer_content_text, $scheduled_id, $now);
                mysqli_stmt_execute($stmt_history);
            }
            sleep(1);
        }
        
        // Update status berdasarkan hasil pengiriman
        if ($fail_count == 0 && $success_count > 0) {
            // Hapus jika sukses semua
            mysqli_query($conn, "DELETE FROM scheduled_message_groups WHERE scheduled_message_id = $scheduled_id");
            mysqli_query($conn, "DELETE FROM scheduled_messages WHERE id = $scheduled_id");
        } else {
            // Update status
            $status = ($success_count > 0) ? 'partial' : 'failed';
            mysqli_query($conn, "UPDATE scheduled_messages SET status = '$status' WHERE id = $scheduled_id");
        }
    }
}

mysqli_close($conn);
echo "Cron job selesai pada " . date('Y-m-d H:i:s');

// Helper functions (bisa dipindah ke functions.php jika mau)
function getGroupPromotions($conn, $group_id) {
    $query = "SELECT p.promotion_content FROM group_promotions gp JOIN promotions p ON gp.promotion_id = p.id AND p.active = 1 WHERE gp.group_id = ? ORDER BY gp.display_order ASC";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $promotions = [];
    while($row = mysqli_fetch_assoc($result)) {
        $promotions[] = $row['promotion_content'];
    }
    return !empty($promotions) ? implode("\n\n", $promotions) : null;
}

function getGroupFooter($conn, $group_id) {
    $query = "SELECT f.footer_content FROM group_settings gs JOIN footers f ON gs.footer_id = f.id AND f.active = 1 WHERE gs.group_id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $group_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if($row = mysqli_fetch_assoc($result)) {
        return $row['footer_content'];
    }
    return null;
}
?>