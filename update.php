<?php
// update.php
header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['score'])) {
    echo json_encode(['success'=>false, 'message'=>'Invalid data']);
    exit;
}

$score = $data['score'];

// File path
$file = 'score.json';

// Load existing data
if(file_exists($file)){
    $currentData = json_decode(file_get_contents($file), true);
} else {
    $currentData = ['score'=>[
        'teamA'=>'Team A','teamB'=>'Team B','runs'=>0,'wickets'=>0,'overs'=>'0.0',
        'last6'=>'','batsman1'=>['name'=>'','runs'=>0,'balls'=>0,'fours'=>0,'sixes'=>0,'sr'=>0.0],
        'batsman2'=>['name'=>'','runs'=>0,'balls'=>0,'fours'=>0,'sixes'=>0,'sr'=>0.0],
        'bowler'=>['name'=>'','overs'=>'0.0','runs'=>0,'wickets'=>0,'eco'=>0.0],
        'target'=>null,'targetWickets'=>null,'targetOvers'=>null,
        'crr'=>0.0,'rrr'=>null
    ]];
}

// Update data
$currentData['score'] = $score;

// Ensure only last 6 balls are kept
$last6 = explode(" ", $score['last6'] ?? "");
$last6 = array_filter($last6, function($b){ return $b !== ""; });
if(count($last6)>6){
    $last6 = array_slice($last6, -6);
}
$currentData['score']['last6'] = implode(" ", $last6);

// Overs correction: legal overs
$oversParts = explode(".", $score['overs']);
$legalOvers = intval($oversParts[0]);
$balls = intval($oversParts[1] ?? 0);
$currentData['score']['overs'] = $legalOvers . "." . $balls;

// Recalculate batsman SR
foreach(['batsman1','batsman2'] as $b){
    $r = intval($score[$b]['runs'] ?? 0);
    $bals = intval($score[$b]['balls'] ?? 0);
    $currentData['score'][$b]['sr'] = $bals > 0 ? round(($r / $bals) * 100, 1) : 0.0;
}

// Calculate CRR (Current Run Rate)
$totalOvers = $legalOvers + ($balls / 6);
$currentData['score']['crr'] = $totalOvers > 0 ? round($score['runs'] / $totalOvers, 2) : 0.0;

// Calculate RRR (Required Run Rate) if target exists
if (isset($score['target']) && $score['target'] !== null && $score['targetOvers']) {
    $runsLeft = $score['target'] - $score['runs'] + 1; // +1 needed to win
    $totalBalls = intval($score['targetOvers']) * 6;
    $ballsBowled = $legalOvers * 6 + $balls;
    $ballsLeft = $totalBalls - $ballsBowled;
    if ($ballsLeft > 0) {
        $rrr = round($runsLeft / ($ballsLeft / 6), 2);
    } else {
        $rrr = 0.0;
    }
    $currentData['score']['rrr'] = $rrr;
} else {
    $currentData['score']['rrr'] = null;
}

// Automatic strike swap on over start
// If previous ball‐part was non‑zero then new over started when balls becomes 0
// But this logic depends on what the input overs are. Using simple check:
if ($balls == 0 && intval($oversParts[1] ?? 0) != 0) {
    $tmp = $currentData['score']['batsman1'];
    $currentData['score']['batsman1'] = $currentData['score']['batsman2'];
    $currentData['score']['batsman2'] = $tmp;
}

// Save back to file
file_put_contents($file, json_encode($currentData, JSON_PRETTY_PRINT));

// Return success
echo json_encode(['success'=>true, 'message'=>'Score updated successfully']);
exit;
?>

