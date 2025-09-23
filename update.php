<?php
// update.php
header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['score'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$score = $data['score'];

// File path
$file = 'score.json';

// Load existing data or initialize default
if (file_exists($file)) {
    $currentData = json_decode(file_get_contents($file), true);
} else {
    $currentData = ['score' => [
        'teamA' => 'Team A',
        'teamB' => 'Team B',
        'runs' => 0,
        'wickets' => 0,
        'overs' => '0.0',
        'last6' => '',
        'batsman1' => ['name' => '', 'runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'sr' => 0.0],
        'batsman2' => ['name' => '', 'runs' => 0, 'balls' => 0, 'fours' => 0, 'sixes' => 0, 'sr' => 0.0],
        'bowler' => ['name' => '', 'overs' => '0.0', 'runs' => 0, 'wickets' => 0, 'eco' => 0.0],
        'target' => null,
        'targetWickets' => null,
        'targetOvers' => null,
        'crr' => 0.0,
        'rrr' => null
    ]];
}

// Helper: Ensure last 6 balls only
$last6 = explode(" ", $score['last6'] ?? "");
$last6 = array_filter($last6, fn($b) => $b !== "");
if (count($last6) > 6) $last6 = array_slice($last6, -6);
$score['last6'] = implode(" ", $last6);

// Normalize overs input to legal balls
$oversParts = explode(".", $score['overs']);
$legalOvers = intval($oversParts[0]);
$balls = intval($oversParts[1] ?? 0);

// Adjust balls if greater than 5 (legal max per over)
if ($balls > 5) {
    $legalOvers += intdiv($balls, 6);
    $balls = $balls % 6;
}
$score['overs'] = $legalOvers . "." . $balls;

// Similarly for bowler overs
$bowlerOversParts = explode(".", $score['bowler']['overs']);
$bowlerLegalOvers = intval($bowlerOversParts[0]);
$bowlerBalls = intval($bowlerOversParts[1] ?? 0);
if ($bowlerBalls > 5) {
    $bowlerLegalOvers += intdiv($bowlerBalls, 6);
    $bowlerBalls = $bowlerBalls % 6;
}
$score['bowler']['overs'] = $bowlerLegalOvers . "." . $bowlerBalls;

// Process last 6 balls to detect extras and runs by batsman or extras
// Balls meaning:
// W = wicket, NB = no ball, WD = wide, LB = leg bye, B = bye, 0-6 normal runs
// For no ball (NB) and wide (WD), runs are extras, batsman runs not incremented
// For leg bye (LB) and bye (B), runs are extras, batsman runs not incremented
// For normal runs (0-6), runs counted to batsman and total
// We will trust frontend to send correct total runs and wicket count
// But for batting stats, we apply logic:

// This code assumes frontend updates batsman runs correctly on no-ball 4 or 6 (like hitting a 6 on no ball counts)
// But for the sake of syncing, let's correct batsman runs and balls on extras

// Calculate batsman balls and runs correctly:
// If last ball is NB or WD - ball does not count as legal ball (balls faced not incremented)
// For LB and B - ball counts as legal ball, but runs not to batsman

// We will compare last 6 balls and correct batsman balls faced accordingly

// First, count balls faced by batsman from last6 balls, ignoring NB and WD (not legal deliveries)

// Get batsman runs and balls from input
$batsman1 = $score['batsman1'];
$batsman2 = $score['batsman2'];

// Calculate batsman1 and batsman2 balls faced (from input) but verify using last6 array
// To keep it simple, trust frontend balls and runs, but do minor corrections for no-ball and extras:

// Check last ball for no-ball or wide to decide if ball faced is to be incremented or not

// Helper function to check if a ball is extra without ball count
function isExtraBall($ball) {
    $ball = strtoupper($ball);
    return $ball === "NB" || $ball === "WD";
}

// Helper function to check if a ball is leg bye or bye
function isLegByeOrBye($ball) {
    $ball = strtoupper($ball);
    return $ball === "LB" || $ball === "B";
}

// Correct balls faced for batsmen: balls faced does NOT increment on NB or WD deliveries
// So if balls faced is given including extras, we subtract the extras deliveries count

// Count how many extras deliveries in last 6 balls
$extraDeliveriesCount = 0;
foreach ($last6 as $ball) {
    if (isExtraBall($ball)) {
        $extraDeliveriesCount++;
    }
}

// Total balls faced in input might include extras, so let's correct balls count by subtracting extras balls from total balls faced
// But since we have 2 batsmen, it's difficult to separate balls faced by each from last6 alone

// We'll assume input balls count is correct from frontend, so we trust batsman balls, runs, fours, sixes as is.

// Recalculate Strike Rate for batsmen
foreach (['batsman1', 'batsman2'] as $b) {
    $runs = intval($score[$b]['runs'] ?? 0);
    $ballsB = intval($score[$b]['balls'] ?? 0);
    $score[$b]['sr'] = $ballsB > 0 ? round(($runs / $ballsB) * 100, 1) : 0.0;
}

// Calculate Current Run Rate (CRR)
$totalOvers = $legalOvers + ($balls / 6);
$score['crr'] = $totalOvers > 0 ? round($score['runs'] / $totalOvers, 2) : 0.0;

// Calculate Required Run Rate (RRR)
if (isset($score['target']) && $score['target'] !== null && isset($score['targetOvers']) && $score['targetOvers'] > 0) {
    $runsLeft = $score['target'] - $score['runs'] + 1;
    $totalBalls = intval($score['targetOvers']) * 6;
    $ballsBowled = $legalOvers * 6 + $balls;
    $ballsLeft = $totalBalls - $ballsBowled;
    $score['rrr'] = $ballsLeft > 0 ? round($runsLeft / ($ballsLeft / 6), 2) : 0.0;
} else {
    $score['rrr'] = null;
}

// Calculate bowler economy rate
$totalBowlerOvers = $bowlerLegalOvers + ($bowlerBalls / 6);
$score['bowler']['eco'] = $totalBowlerOvers > 0 ? round($score['bowler']['runs'] / $totalBowlerOvers, 2) : 0.0;

// Update main data
$currentData['score'] = $score;

// Save to file
file_put_contents($file, json_encode($currentData, JSON_PRETTY_PRINT));

// Return success
echo json_encode(['success' => true, 'message' => 'Score updated successfully']);
exit;
?>
