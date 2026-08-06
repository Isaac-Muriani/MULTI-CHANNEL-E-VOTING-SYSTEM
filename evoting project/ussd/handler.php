<?php
// =============================================================
//  USSD HANDLER (State Machine Version)
//  evoting/ussd/handler.php
// =============================================================
require_once '../includes/config.php';

define('AT_USERNAME', 'sandbox');
define('AT_API_KEY',  'YOUR_API_KEY_HERE');

// ---- Get POST data ----
$session_id = $_POST['sessionId']   ?? '';
$phone      = $_POST['phoneNumber'] ?? '';
$input      = trim($_POST['text']   ?? '');

$phone = preg_replace('/[^0-9]/', '', $phone);

// ---- Get or create session ----
$stmt = $conn->prepare("SELECT * FROM ussd_sessions WHERE session_code = ? AND status = 'active'");
$stmt->bind_param('s', $session_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
    $stmt = $conn->prepare(
        "INSERT INTO ussd_sessions (msisdn, session_code, status, started_at)
         VALUES (?, ?, 'active', NOW())"
    );
    $stmt->bind_param('ss', $phone, $session_id);
    $stmt->execute();
    $stmt->close();
    $session = ['voter_id' => null, 'session_code' => $session_id];
}

// ---- Session state helpers ----
function getState($session_id, $conn) {
    $stmt = $conn->prepare("SELECT session_data FROM ussd_sessions WHERE session_code = ?");
    $stmt->bind_param('s', $session_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? json_decode($row['session_data'] ?? '{}', true) : [];
}

function setState($session_id, $data, $conn) {
    $json = json_encode($data);
    $stmt = $conn->prepare("UPDATE ussd_sessions SET session_data = ? WHERE session_code = ?");
    $stmt->bind_param('ss', $json, $session_id);
    $stmt->execute();
    $stmt->close();
}

function endSession($session_id, $conn) {
    $stmt = $conn->prepare("UPDATE ussd_sessions SET status='completed', ended_at=NOW() WHERE session_code=?");
    $stmt->bind_param('s', $session_id);
    $stmt->execute();
    $stmt->close();
}

// ---- Data helpers ----
function getVoterByNationalId($national_id, $conn) {
    $stmt = $conn->prepare("SELECT * FROM voters WHERE national_id = ? AND deleted_at IS NULL");
    $stmt->bind_param('s', $national_id);
    $stmt->execute();
    $voter = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $voter;
}

function getActiveElections($conn) {
    return $conn->query(
        "SELECT election_id, election_name FROM elections
         WHERE status = 'active' AND deleted_at IS NULL ORDER BY election_name"
    )->fetch_all(MYSQLI_ASSOC);
}

function getPositions($election_id, $conn) {
    return $conn->query(
        "SELECT position_id, position_name FROM positions
         WHERE election_id = $election_id ORDER BY position_name"
    )->fetch_all(MYSQLI_ASSOC);
}

function getCandidates($position_id, $conn) {
    // Get presidential candidates with their VP running mate names
    return $conn->query(
        "SELECT c.candidate_id,
                v.first_name, v.last_name,
                v2.first_name AS rm_first, v2.last_name AS rm_last,
                p.running_mate_enabled
         FROM candidates c
         JOIN voters v    ON v.voter_id    = c.voter_id
         JOIN positions p ON p.position_id = c.position_id
         LEFT JOIN candidates c2 ON c2.candidate_id = c.running_mate_id
         LEFT JOIN voters v2     ON v2.voter_id = c2.voter_id
         WHERE c.position_id = $position_id
           AND c.status = 'approved'
           AND c.deleted_at IS NULL
           AND c.is_running_mate = 0
         ORDER BY v.last_name"
    )->fetch_all(MYSQLI_ASSOC);
}

function alreadyVoted($voter_id, $position_id, $conn) {
    // Uses $conn passed explicitly — no global needed
    $result = $conn->query(
        "SELECT vote_id FROM votes
         WHERE voter_id = $voter_id AND position_id = $position_id
         LIMIT 1"
    );
    return $result && $result->num_rows > 0;
}

function showPositions($election_id, $voter_id, $conn) {
    $positions = getPositions($election_id, $conn);
    if (empty($positions)) return null;
    $menu = "CON Select Position:\n";
    foreach ($positions as $i => $p) {
        $voted = alreadyVoted($voter_id, $p['position_id'], $conn);
        $menu .= ($i + 1) . ". " . $p['position_name'] . ($voted ? " ✅" : "") . "\n";
    }
    $menu .= "0. Exit";
    return $menu;
}

// ---- Get current state ----
$state    = getState($session_id, $conn);
$step     = $state['step']     ?? 'national_id';
$voter_id = $state['voter_id'] ?? ($session['voter_id'] ?? 0);

// ---- Get last input (only the latest entry) ----
// Africa's Talking sends cumulative input e.g. "1234*pass*1*2"
// We take the LAST part as the current input
$parts        = $input === '' ? [] : explode('*', $input);
$current_input = end($parts); // Last entry only
if ($input === '') $current_input = '';

$response = '';

// =============================================================
//  STATE MACHINE
// =============================================================

switch ($step) {

    // ---- STEP 1: Welcome — ask National ID ----
    case 'national_id':
        if ($current_input === '') {
            $response = "CON Welcome to E-Voting System\n";
            $response .= "--------------------------------\n";
            $response .= "Enter your National ID:";
        } else {
            $national_id = trim($current_input);
            $voter = getVoterByNationalId($national_id, $conn);
            if (!$voter) {
                endSession($session_id, $conn);
                $response = "END ❌ National ID not found.\nPlease register on the web portal first.";
            } elseif ($voter['status'] === 'suspended') {
                endSession($session_id, $conn);
                $response = "END ❌ Your account is suspended. Contact admin.";
            } else {
                setState($session_id, [
                    'step'       => 'password',
                    'national_id'=> $national_id,
                    'voter_id'   => 0,
                ], $conn);
                $response = "CON Enter your PIN:";
            }
        }
        break;

    // ---- STEP 2: Verify PIN ----
    case 'password':
        $national_id = $state['national_id'] ?? '';
        $voter = getVoterByNationalId($national_id, $conn);
        if (!$voter || !verifyPassword($current_input, $voter['password'])) {
            endSession($session_id, $conn);
            $response = "END ❌ Invalid National ID or PIN.\nPlease try again.";
        } else {
            // Log login
            $uid = $voter['voter_id']; $type = 'voter'; $stat = 'success'; $ip = 'ussd';
            $log = $conn->prepare("INSERT INTO login_logs (user_id,user_type,ip_address,status) VALUES (?,?,?,?)");
            $log->bind_param('isss', $uid, $type, $ip, $stat);
            $log->execute(); $log->close();

            // Update session voter_id
            $conn->prepare("UPDATE ussd_sessions SET voter_id=? WHERE session_code=?")
                ->bind_param('is', $uid, $session_id);
            $stmt2 = $conn->prepare("UPDATE ussd_sessions SET voter_id=? WHERE session_code=?");
            $stmt2->bind_param('is', $uid, $session_id);
            $stmt2->execute(); $stmt2->close();

            $elections = getActiveElections($conn);
            if (empty($elections)) {
                endSession($session_id, $conn);
                $response = "END ℹ️ No active elections at the moment.";
            } else {
                setState($session_id, [
                    'step'     => 'select_election',
                    'voter_id' => $voter['voter_id'],
                    'national_id' => $national_id,
                ], $conn);
                $response = "CON Welcome, " . $voter['first_name'] . "!\n";
                $response .= "Select Election:\n";
                foreach ($elections as $i => $e) {
                    $response .= ($i + 1) . ". " . $e['election_name'] . "\n";
                }
                $response .= "0. Exit";
            }
        }
        break;

    // ---- STEP 3: Election selected ----
    case 'select_election':
        $voter_id  = $state['voter_id'];
        $choice    = (int)$current_input;
        $elections = getActiveElections($conn);

        if ($choice === 0) {
            endSession($session_id, $conn);
            $response = "END Thank you! Goodbye.";
        } elseif (!isset($elections[$choice - 1])) {
            $response = "CON Invalid choice.\nSelect Election:\n";
            foreach ($elections as $i => $e) {
                $response .= ($i + 1) . ". " . $e['election_name'] . "\n";
            }
            $response .= "0. Exit";
        } else {
            $election = $elections[$choice - 1];
            setState($session_id, [
                'step'        => 'select_position',
                'voter_id'    => $voter_id,
                'national_id' => $state['national_id'],
                'election_id' => $election['election_id'],
                'election_name'=> $election['election_name'],
            ], $conn);
            $menu = showPositions($election['election_id'], $voter_id, $conn);
            $response = $menu ?? "END ℹ️ No positions available for this election.";
        }
        break;

    // ---- STEP 4: Position selected ----
    case 'select_position':
        $voter_id    = $state['voter_id'];
        $election_id = $state['election_id'];
        $choice      = (int)$current_input;
        $positions   = getPositions($election_id, $conn);

        if ($choice === 0) {
            endSession($session_id, $conn);
            $response = "END Thank you! Goodbye.";
        } elseif (!isset($positions[$choice - 1])) {
            $response = showPositions($election_id, $voter_id, $conn) ?? "END Error.";
        } else {
            $position = $positions[$choice - 1];

            // Check if already voted for this position
            if (alreadyVoted($voter_id, $position['position_id'], $conn)) {
                // Stay on select_position step — don't change state
                // Just show positions again with already-voted marked
                $menu  = "CON ✅ Already voted for " . $position['position_name'] . ".\n";
                $menu .= "Choose another position:\n";
                foreach ($positions as $i => $p) {
                    $voted = alreadyVoted($voter_id, $p['position_id'], $conn);
                    $menu .= ($i + 1) . ". " . $p['position_name'] . ($voted ? " ✅" : "") . "\n";
                }
                $menu .= "0. Exit";
                $response = $menu;
                // IMPORTANT: Keep step as select_position so next input comes back here
                setState($session_id, array_merge($state, ['step' => 'select_position']), $conn);
            } else {
                // Show candidates for this position
                $candidates = getCandidates($position['position_id'], $conn);
                if (empty($candidates)) {
                    $response = "END ℹ️ No approved candidates for " . $position['position_name'] . ".";
                } else {
                    setState($session_id, array_merge($state, [
                        'step'          => 'select_candidate',
                        'position_id'   => $position['position_id'],
                        'position_name' => $position['position_name'],
                    ]), $conn);
                    $response = "CON " . $position['position_name'] . "\n";
                    $response .= "Select Candidate:\n";
                    foreach ($candidates as $i => $c) {
                        $name = $c['first_name'] . " " . $c['last_name'];
                        // Show VP name if running mate enabled
                        if (!empty($c['rm_first'])) {
                            $name .= " & " . $c['rm_first'] . " " . $c['rm_last'] . " (VP)";
                        }
                        $response .= ($i + 1) . ". " . $name . "\n";
                    }
                    $response .= "0. Back";
                }
            }
        }
        break;

    // ---- STEP 5: Candidate selected — confirm ----
    case 'select_candidate':
        $voter_id    = $state['voter_id'];
        $position_id = $state['position_id'];
        $choice      = (int)$current_input;
        $candidates  = getCandidates($position_id, $conn);

        if ($choice === 0) {
            // Go back to position list
            setState($session_id, array_merge($state, ['step' => 'select_position']), $conn);
            $response = showPositions($state['election_id'], $voter_id, $conn) ?? "END Error.";
        } elseif (!isset($candidates[$choice - 1])) {
            $response = "CON Invalid choice.\nSelect Candidate:\n";
            foreach ($candidates as $i => $c) {
                $response .= ($i + 1) . ". " . $c['first_name'] . " " . $c['last_name'] . "\n";
            }
            $response .= "0. Back";
        } else {
            $candidate = $candidates[$choice - 1];
            setState($session_id, array_merge($state, [
                'step'          => 'confirm_vote',
                'candidate_id'  => $candidate['candidate_id'],
                'candidate_name'=> $candidate['first_name'] . " " . $candidate['last_name'],
            ]), $conn);
            $response  = "CON Confirm your vote:\n";
            $response .= "Position: " . $state['position_name'] . "\n";
            $response .= "Candidate: " . $candidate['first_name'] . " " . $candidate['last_name'] . "\n";
            // Show VP if running mate
            if (!empty($candidate['rm_first'])) {
                $response .= "VP: " . $candidate['rm_first'] . " " . $candidate['rm_last'] . "\n";
            }
            $response .= "------------------------\n";
            $response .= "1. Confirm Vote ✅\n";
            $response .= "2. Cancel ❌";
        }
        break;

    // ---- STEP 6: Final confirmation ----
    case 'confirm_vote':
        $voter_id     = $state['voter_id'];
        $position_id  = $state['position_id'];
        $candidate_id = $state['candidate_id'];
        $confirm      = (int)$current_input;

        if ($confirm === 2) {
            endSession($session_id, $conn);
            $response = "END ❌ Vote cancelled. Thank you.";
        } elseif ($confirm === 1) {
            // Get USSD channel ID
            $channel    = $conn->query("SELECT channel_id FROM voting_channels WHERE channel_name='ussd'")->fetch_assoc();
            $channel_id = $channel['channel_id'] ?? 2;

            // Final duplicate check — works cross-channel (web + ussd)
            if (alreadyVoted($voter_id, $position_id, $conn)) {
                endSession($session_id, $conn);
                $response  = "END ❌ You have already voted for this position.\n";
                $response .= "Your vote via another channel has been recorded.";
            } else {
                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO votes (voter_id, candidate_id, position_id, channel_id)
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param('iiii', $voter_id, $candidate_id, $position_id, $channel_id);
                    $stmt->execute();
                    $stmt->close();

                    // Ask if they want to vote for another position
                    $positions   = getPositions($state['election_id'], $conn);
                    $has_unvoted = false;
                    foreach ($positions as $p) {
                        if (!alreadyVoted($voter_id, $p['position_id'], $conn)) {
                            $has_unvoted = true;
                            break;
                        }
                    }

                    if ($has_unvoted) {
                        setState($session_id, array_merge($state, ['step' => 'select_position']), $conn);
                        $menu  = "CON ✅ Vote cast successfully!\n";
                        $menu .= "Vote for another position?\n";
                        foreach ($positions as $i => $p) {
                            $voted = alreadyVoted($voter_id, $p['position_id'], $conn);
                            $menu .= ($i + 1) . ". " . $p['position_name'] . ($voted ? " ✅" : "") . "\n";
                        }
                        $menu .= "0. Done";
                        $response = $menu;
                    } else {
                        endSession($session_id, $conn);
                        $response  = "END ✅ Vote cast successfully!\n";
                        $response .= "You have voted for all positions.\n";
                        $response .= "Thank you for participating! 🗳️";
                    }

                } catch (mysqli_sql_exception $e) {
                    endSession($session_id, $conn);
                    $response = ($e->getCode() == 1062)
                        ? "END ❌ You have already voted for this position."
                        : "END ❌ Failed to cast vote. Please try again.";
                }
            }
        } else {
            $response  = "CON Invalid choice.\n";
            $response .= "1. Confirm Vote ✅\n";
            $response .= "2. Cancel ❌";
        }
        break;

    // ---- Fallback ----
    default:
        endSession($session_id, $conn);
        $response = "END Session expired. Please dial again.";
        break;
}

// ---- Send response ----
header('Content-Type: text/plain');
echo $response;
?>
