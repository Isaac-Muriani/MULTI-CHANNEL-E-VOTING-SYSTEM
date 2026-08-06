<?php
// =============================================================
// FILE: includes/otp.php
// =============================================================

// 1. Set Timezone (Hii ni muhimu ili muda wa PHP na wa Database usitofautiane)
date_default_timezone_set('Africa/Dar_es_Salaam');

// 2. Define muda wa OTP kuishi (Dakika 5)
if (!defined('OTP_EXPIRY')) {
    define('OTP_EXPIRY', 5); 
}

/**
 * Function ya kutengeneza OTP na kuisave kwenye Database
 */
function generateOTP($voter_id, $conn) {
    // Tengeneza namba 6 za random (mt_rand(100000, 999999) inahakikisha namba 6 kila wakati)
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $expires_at = date("Y-m-d H:i:s", strtotime("+".OTP_EXPIRY." minutes"));

    // Futa OTP za zamani za huyu voter kwanza
    $stmt = $conn->prepare("DELETE FROM otp_codes WHERE voter_id = ?");
    $stmt->bind_param("i", $voter_id);
    $stmt->execute();
    $stmt->close();

    // Hifadhi OTP mpya
    $stmt = $conn->prepare("INSERT INTO otp_codes (voter_id, otp_code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $voter_id, $otp, $expires_at);
    
    if ($stmt->execute()) {
        $stmt->close();
        return $otp;
    } else {
        return false;
    }
}

/**
 * Function ya kutuma SMS kupitia INFOBIP
 */
function sendOTP($phone, $otp) {
    $baseUrl = "https://w4klqr.api.infobip.com"; 
    $apiKey = "App d2ead1fd9c75ab413bcb8916c6a9fd5f-8b0e12e1-71be-4871-9946-c95224fe3405";

    // Safisha namba ya simu
    $phone = preg_replace('/[^0-9]/', '', $phone); // Ondoa alama yoyote (+, -, space)
    
    // Hakikisha namba inaanza na 255
    if (strpos($phone, '0') === 0) {
        $phone = '255' . substr($phone, 1);
    } elseif (strpos($phone, '7') === 0 || strpos($phone, '6') === 0) {
        $phone = '255' . $phone;
    }

    $message = "Code yako ya uhakiki wa kura ni: " . $otp . ". Itumie ndani ya dakika " . OTP_EXPIRY;

    $data = array(
        "messages" => array(
            array(
                "from" => "E-VOTING", // Unaweza kubadili jina la mtumaji hapa
                "destinations" => array(
                    array("to" => $phone)
                ),
                "text" => $message
            )
        )
    );

    $payload = json_encode($data);

    $ch = curl_init($baseUrl . "/sms/2/text/advanced");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    
    // Ni vizuri kuacha SSL iwe true kwenye live server (Isipokuwa kama unatester kwenye localhost isiyo na SSL)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => "cURL Error: " . $err];
    }

    if ($httpCode == 200 || $httpCode == 201) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => $response];
    }
}

/**
 * Function ya kuhakiki kama OTP ni sahihi
 */
function verifyOTP($voter_id, $otp_entered, $conn) {
    // Tunatumia NOW() ya MySQL, hakikisha MySQL timezone ipo sawa na PHP
    $stmt = $conn->prepare("SELECT id FROM otp_codes WHERE voter_id = ? AND otp_code = ? AND expires_at > NOW() LIMIT 1");
    $stmt->bind_param("is", $voter_id, $otp_entered);
    $stmt->execute();
    $result = $stmt->get_result();
    $isValid = $result->num_rows > 0;
    $stmt->close();

    if ($isValid) {
        // Futa OTP isitumike tena (Single use policy)
        $stmt = $conn->prepare("DELETE FROM otp_codes WHERE voter_id = ?");
        $stmt->bind_param("i", $voter_id);
        $stmt->execute();
        $stmt->close();
    }

    return $isValid;
}
?>