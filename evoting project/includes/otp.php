<?php
// =============================================================
//  OTP HELPER
//  evoting/includes/otp.php
//  Sends and verifies OTP via Africa's Talking SMS API
// =============================================================

define('AT_USERNAME', 'sandbox');
define('AT_API_KEY',  'YOUR_API_KEY_HERE'); // Replace with your real API key
define('AT_SENDER',   'EVoting');           // Sender name (may not work in sandbox)
define('OTP_EXPIRY',  10);                  // OTP expires in 10 minutes

/**
 * Generate a 6-digit OTP and store it in the database
 */
function generateOTP($voter_id, $conn) {
    $otp     = rand(100000, 999999);
    $expires = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY . ' minutes'));

    // Delete any existing OTP for this voter
    $conn->query("DELETE FROM otp_codes WHERE voter_id = $voter_id");

    // Insert new OTP
    $stmt = $conn->prepare(
        "INSERT INTO otp_codes (voter_id, otp_code, expires_at) VALUES (?, ?, ?)"
    );
    $stmt->bind_param('iss', $voter_id, $otp, $expires);
    $stmt->execute();
    $stmt->close();

    return $otp;
}

/**
 * Send OTP via Africa's Talking SMS
 */
function sendOTP($phone, $otp) {
    $message = "Your E-Voting OTP is: $otp\nValid for " . OTP_EXPIRY . " minutes.\nDo not share this code with anyone.";

    // Format phone number for Tanzania (+255)
    $phone = formatPhone($phone);

    $data = http_build_query([
        'username' => AT_USERNAME,
        'to'       => $phone,
        'message'  => $message,
        'from'     => AT_SENDER,
    ]);

    $ch = curl_init('https://api.sandbox.africastalking.com/version1/messaging');
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     [
        'apiKey: ' . AT_API_KEY,
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) return ['success' => false, 'error' => $err];

    $result = json_decode($response, true);
    $status = $result['SMSMessageData']['Recipients'][0]['status'] ?? 'error';

    return [
        'success' => $status === 'Success',
        'response' => $result,
    ];
}

/**
 * Verify OTP entered by voter
 */
function verifyOTP($voter_id, $otp_entered, $conn) {
    $now  = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "SELECT otp_id FROM otp_codes
         WHERE voter_id = ? AND otp_code = ? AND expires_at > ? AND used = 0"
    );
    $stmt->bind_param('iss', $voter_id, $otp_entered, $now);
    $stmt->execute();
    $stmt->store_result();
    $valid = $stmt->num_rows > 0;
    $stmt->close();

    if ($valid) {
        // Mark OTP as used
        $conn->query(
            "UPDATE otp_codes SET used = 1 WHERE voter_id = $voter_id"
        );
    }

    return $valid;
}

/**
 * Format phone number to international format
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Tanzania: 07XX -> +25507XX
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        $phone = '+255' . substr($phone, 1);
    }
    // Already has country code
    elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '255') {
        $phone = '+' . $phone;
    }
    // Already formatted
    elseif (substr($phone, 0, 1) !== '+') {
        $phone = '+' . $phone;
    }

    return $phone;
}
?>
