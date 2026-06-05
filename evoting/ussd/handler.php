<?php
// =============================================================
//  USSD HANDLER
//  evoting/ussd/handler.php
//  This is the callback URL for Africa's Talking USSD
// =============================================================
require_once '../includes/config.php';

// ---- Africa's Talking credentials ----
define('AT_USERNAME', 'sandbox');
define('AT_API_KEY',  'YOUR_API_KEY_HERE'); // Replace with your real API key

// ---- Get POST data from Africa's Talking ----
$session_id  = $_POST['sessionId']  ?? '';
$phone       = $_POST['phoneNumber'] ?? '';
$network     = $_POST['networkCode'] ?? '';
$input       = $_POST['text']        ?? '';

// Clean phone number (remove + sign)
$phone = preg_replace('/[^0-9]/', '', $phone);

// Split input by * to get menu navigation steps
$steps = $input === '' ? [] : explode('*', $input);
$level = count($steps);

// ---- Session state from DB ----
// Get or create USSD session
$stmt = $conn->prepare(
    "SELECT * FROM ussd_sessions WHERE session_code = ? AND status = 'active'"
);
$stmt->bind_param('s', $session_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
    // Create new session
    $stmt = $conn->prepare(
        "INSERT INTO ussd_sessions (msisdn, session_code, status, started_at)
         VALUES (?, ?, 'active', NOW())"
    );
    $stmt->bind_param('ss', $phone, $session_id);
    $stmt->execute();
    $stmt->close();
}

// ---- Helper: end session ----
function endSession($session_id, $conn) {
    $stmt = $conn->prepare(
        "UPDATE ussd_sessions SET status='completed', ended_at=NOW()
         WHERE session_code=?"
    );
    $stmt->bind_param('s', $session_id);
    $stmt->execute();
    $stmt->close();
}

// ---- Helper: get voter by national ID ----
function getVoterByNationalId($national_id, $conn) {
    $stmt = $conn->prepare(
        "SELECT * FROM voters WHERE national_id = ? AND deleted_at IS NULL"
    );
    $stmt->bind_param('s', $national_id);
    $stmt->execute();
    $voter = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $voter;
}

// ---- Helper: get active elections ----
function getActiveElections($conn) {
    return $conn->query(
        "SELECT election_id, election_name FROM elections
         WHERE status = 'active' AND deleted_at IS NULL
         ORDER BY election_name"
    )->fetch_all(MYSQLI_ASSOC);
}

// ---- Helper: get positions for election ----
function getPositions($election_id, $conn) {
    return $conn->query(
        "SELECT position_id, position_name FROM positions
         WHERE election_id = $election_id
         ORDER BY position_name"
    )->fetch_all(MYSQLI_ASSOC);
}

// ---- Helper: get approved candidates for position ----
function getCandidates($position_id, $conn) {
    return $conn->query(
        "SELECT c.candidate_id, v.first_name, v.last_name
         FROM candidates c
         JOIN voters v ON v.voter_id = c.voter_id
         WHERE c.position_id = $position_id
           AND c.status = 'approved'
           AND c.deleted_at IS NULL
         ORDER BY v.last_name"
    )->fetch_all(MYSQLI_ASSOC);
}

// ---- Helper: check already voted ----
function alreadyVoted($voter_id, $position_id, $conn) {
    $stmt = $conn->prepare(
        "SELECT vote_id FROM votes WHERE voter_id = ? AND position_id = ?"
    );
    $stmt->bind_param('ii', $voter_id, $position_id);
    $stmt->execute();
    $stmt->store_result();
    $voted = $stmt->num_rows > 0;
    $stmt->close();
    return $voted;
}

// =============================================================
//  USSD MENU FLOW
// =============================================================

$response = '';

// ---- LEVEL 0: Welcome ----
if ($input === '') {
    $response = "CON Welcome to E-Voting System\n";
    $response .= "--------------------------------\n";
    $response .= "Enter your National ID:";
}

// ---- LEVEL 1: National ID entered ----
elseif ($level === 1) {
    $national_id = trim($steps[0]);
    $voter = getVoterByNationalId($national_id, $conn);

    if (!$voter) {
        endSession($session_id, $conn);
        $response = "END ❌ National ID not found.\nPlease register on the web portal first.";
    } elseif ($voter['status'] === 'suspended') {
        endSession($session_id, $conn);
        $response = "END ❌ Your account is suspended.\nContact the administrator.";
    } else {
        $response = "CON Enter your Password:";
    }
}

// ---- LEVEL 2: Password entered ----
elseif ($level === 2) {
    $national_id = trim($steps[0]);
    $password    = $steps[1];
    $voter       = getVoterByNationalId($national_id, $conn);

    if (!$voter || !verifyPassword($password, $voter['password'])) {
        endSession($session_id, $conn);
        $response = "END ❌ Invalid National ID or Password.\nPlease try again.";
    } else {
        // Update session with voter_id
        $stmt = $conn->prepare(
            "UPDATE ussd_sessions SET voter_id = ? WHERE session_code = ?"
        );
        $stmt->bind_param('is', $voter['voter_id'], $session_id);
        $stmt->execute();
        $stmt->close();

        // Log the login
        $uid  = $voter['voter_id'];
        $type = 'voter';
        $stat = 'success';
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'ussd';
        $log  = $conn->prepare(
            "INSERT INTO login_logs (user_id, user_type, ip_address, status)
             VALUES (?, ?, ?, ?)"
        );
        $log->bind_param('isss', $uid, $type, $ip, $stat);
        $log->execute();
        $log->close();

        // Show active elections
        $elections = getActiveElections($conn);

        if (empty($elections)) {
            endSession($session_id, $conn);
            $response = "END ℹ️ No active elections at the moment.\nCheck back later.";
        } else {
            $response = "CON Welcome, " . $voter['first_name'] . "!\n";
            $response .= "Select Election:\n";
            foreach ($elections as $i => $e) {
                $response .= ($i + 1) . ". " . $e['election_name'] . "\n";
            }
            $response .= "0. Exit";
        }
    }
}

// ---- LEVEL 3: Election selected ----
elseif ($level === 3) {
    $election_choice = (int)$steps[2];

    if ($election_choice === 0) {
        endSession($session_id, $conn);
        $response = "END Thank you for using E-Voting System. Goodbye!";
    } else {
        $elections = getActiveElections($conn);

        if (!isset($elections[$election_choice - 1])) {
            $response = "CON Invalid choice.\nPlease select a valid election number:";
            foreach ($elections as $i => $e) {
                $response .= ($i + 1) . ". " . $e['election_name'] . "\n";
            }
        } else {
            $election    = $elections[$election_choice - 1];
            $election_id = $election['election_id'];
            $positions   = getPositions($election_id, $conn);

            if (empty($positions)) {
                endSession($session_id, $conn);
                $response = "END ℹ️ No positions available for this election yet.";
            } else {
                $response = "CON " . $election['election_name'] . "\n";
                $response .= "Select Position:\n";
                foreach ($positions as $i => $p) {
                    $response .= ($i + 1) . ". " . $p['position_name'] . "\n";
                }
                $response .= "0. Back";
            }
        }
    }
}

// ---- LEVEL 4: Position selected ----
elseif ($level === 4) {
    $election_choice  = (int)$steps[2];
    $position_choice  = (int)$steps[3];

    $elections   = getActiveElections($conn);
    $election    = $elections[$election_choice - 1] ?? null;

    if ($position_choice === 0 || !$election) {
        // Back to elections
        $elections = getActiveElections($conn);
        $response  = "CON Select Election:\n";
        foreach ($elections as $i => $e) {
            $response .= ($i + 1) . ". " . $e['election_name'] . "\n";
        }
        $response .= "0. Exit";
    } else {
        $positions   = getPositions($election['election_id'], $conn);
        $position    = $positions[$position_choice - 1] ?? null;

        if (!$position) {
            $response = "CON Invalid choice. Select Position:\n";
            foreach ($positions as $i => $p) {
                $response .= ($i + 1) . ". " . $p['position_name'] . "\n";
            }
        } else {
            // Get voter from session
            $sess_stmt = $conn->prepare(
                "SELECT voter_id FROM ussd_sessions WHERE session_code = ?"
            );
            $sess_stmt->bind_param('s', $session_id);
            $sess_stmt->execute();
            $sess_data = $sess_stmt->get_result()->fetch_assoc();
            $sess_stmt->close();

            $voter_id = $sess_data['voter_id'] ?? 0;

            // Check already voted
            if (alreadyVoted($voter_id, $position['position_id'], $conn)) {
                $response = "CON ✅ You already voted for " . $position['position_name'] . ".\n";
                $response .= "Select another position or:\n";
                $positions = getPositions($election['election_id'], $conn);
                foreach ($positions as $i => $p) {
                    $response .= ($i + 1) . ". " . $p['position_name'] . "\n";
                }
                $response .= "0. Back";
            } else {
                $candidates = getCandidates($position['position_id'], $conn);

                if (empty($candidates)) {
                    $response = "END ℹ️ No approved candidates for " . $position['position_name'] . " yet.";
                } else {
                    $response = "CON " . $position['position_name'] . "\n";
                    $response .= "Select Candidate:\n";
                    foreach ($candidates as $i => $c) {
                        $response .= ($i + 1) . ". " . $c['first_name'] . " " . $c['last_name'] . "\n";
                    }
                    $response .= "0. Back";
                }
            }
        }
    }
}

// ---- LEVEL 5: Candidate selected (confirm) ----
elseif ($level === 5) {
    $election_choice  = (int)$steps[2];
    $position_choice  = (int)$steps[3];
    $candidate_choice = (int)$steps[4];

    $elections  = getActiveElections($conn);
    $election   = $elections[$election_choice - 1] ?? null;
    $positions  = $election ? getPositions($election['election_id'], $conn) : [];
    $position   = $positions[$position_choice - 1] ?? null;

    if ($candidate_choice === 0 || !$position) {
        // Back to positions
        $response = "CON Select Position:\n";
        foreach ($positions as $i => $p) {
            $response .= ($i + 1) . ". " . $p['position_name'] . "\n";
        }
        $response .= "0. Back";
    } else {
        $candidates = getCandidates($position['position_id'], $conn);
        $candidate  = $candidates[$candidate_choice - 1] ?? null;

        if (!$candidate) {
            $response = "CON Invalid choice. Select Candidate:\n";
            foreach ($candidates as $i => $c) {
                $response .= ($i + 1) . ". " . $c['first_name'] . " " . $c['last_name'] . "\n";
            }
        } else {
            $response  = "CON Confirm your vote:\n";
            $response .= "Position: " . $position['position_name'] . "\n";
            $response .= "Candidate: " . $candidate['first_name'] . " " . $candidate['last_name'] . "\n";
            $response .= "------------------------\n";
            $response .= "1. Confirm Vote ✅\n";
            $response .= "2. Cancel ❌";
        }
    }
}

// ---- LEVEL 6: Final confirmation ----
elseif ($level === 6) {
    $confirm          = (int)$steps[5];
    $election_choice  = (int)$steps[2];
    $position_choice  = (int)$steps[3];
    $candidate_choice = (int)$steps[4];

    if ($confirm === 2) {
        endSession($session_id, $conn);
        $response = "END ❌ Vote cancelled.\nThank you for using E-Voting System.";
    } elseif ($confirm === 1) {
        $elections  = getActiveElections($conn);
        $election   = $elections[$election_choice - 1] ?? null;
        $positions  = $election ? getPositions($election['election_id'], $conn) : [];
        $position   = $positions[$position_choice - 1] ?? null;
        $candidates = $position ? getCandidates($position['position_id'], $conn) : [];
        $candidate  = $candidates[$candidate_choice - 1] ?? null;

        // Get voter from session
        $sess_stmt = $conn->prepare(
            "SELECT voter_id FROM ussd_sessions WHERE session_code = ?"
        );
        $sess_stmt->bind_param('s', $session_id);
        $sess_stmt->execute();
        $sess_data = $sess_stmt->get_result()->fetch_assoc();
        $sess_stmt->close();

        $voter_id   = $sess_data['voter_id'] ?? 0;
        $position_id  = $position['position_id']  ?? 0;
        $candidate_id = $candidate['candidate_id'] ?? 0;

        // Get USSD channel ID
        $channel = $conn->query(
            "SELECT channel_id FROM voting_channels WHERE channel_name = 'ussd'"
        )->fetch_assoc();
        $channel_id = $channel['channel_id'] ?? 2;

        // Double check not already voted
        if (alreadyVoted($voter_id, $position_id, $conn)) {
            endSession($session_id, $conn);
            $response = "END ❌ You have already voted for this position.";
        } elseif (!$candidate || !$position || $voter_id === 0) {
            endSession($session_id, $conn);
            $response = "END ❌ Invalid vote. Please try again.";
        } else {
            // Cast vote
            $stmt = $conn->prepare(
                "INSERT INTO votes (voter_id, candidate_id, position_id, channel_id)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param('iiii', $voter_id, $candidate_id, $position_id, $channel_id);

            if ($stmt->execute()) {
                endSession($session_id, $conn);
                $response  = "END ✅ Vote cast successfully!\n";
                $response .= "Position: " . $position['position_name'] . "\n";
                $response .= "Candidate: " . $candidate['first_name'] . " " . $candidate['last_name'] . "\n";
                $response .= "Thank you for voting! 🗳️";
            } else {
                endSession($session_id, $conn);
                $response = "END ❌ Failed to cast vote. Please try again.";
            }
            $stmt->close();
        }
    } else {
        $response = "CON Invalid choice.\n1. Confirm Vote\n2. Cancel";
    }
}

// ---- Fallback ----
else {
    endSession($session_id, $conn);
    $response = "END Session expired. Please dial again.";
}

// ---- Send response to Africa's Talking ----
header('Content-Type: text/plain');
echo $response;
?>
