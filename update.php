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
        'last6'=>'',
        'batsman1'=>['name'=>'','runs'=>0,'balls'=>0,'fours'=>0,'sixes'=>0,'sr'=>0.0],
        'batsman2'=>['name'=>'','runs'=>0,'balls'=>0,'fours'=>0,'sixes'=>0,'sr'=>0.0],
        'bowler'=>['name'=>'','overs'=>'0.0','runs'=>0,'wickets'=>0,'eco'=>0.0],
        'target'=>null,'targetWickets'=>null,'targetOvers'=>null,
        'crr'=>0.0,'rrr'=>null
    ]];
}

// Process last6 balls - keep only last 6 balls
$last6Balls = preg_split('/\s+/', trim($score['last6'] ?? ''));
$last6Balls = array_filter($last6Balls, fn($b) => $b !== '');
if (count($last6Balls) > 6) {
    $last6Balls = array_slice($last6Balls, -6);
}
$score['last6'] = implode(" ", $last6Balls);

// Parse overs properly for total legal balls
function parseOvers($overs) {
    $parts = explode('.', $overs);
    $o = intval($parts[0]);
    $b = intval($parts[1] ?? 0);
    if ($b > 5) $b = 5; // max 5 balls in an over
    return [$o, $b];
}

// Calculate total balls from overs string "x.y"
list($legalOvers, $balls) = parseOvers($score['overs']);
$totalBalls = $legalOvers * 6 + $balls;

// Recalculate batsmen SR (strike rate)
foreach (['batsman1', 'batsman2'] as $bat) {
    $runs = intval($score[$bat]['runs'] ?? 0);
    $ballsB = intval($score[$bat]['balls'] ?? 0);
    $score[$bat]['sr'] = $ballsB > 0 ? round(($runs / $ballsB) * 100, 1) : 0.0;
}

// Calculate CRR (Current Run Rate)
$totalOversDecimal = $legalOvers + ($balls / 6);
$score['crr'] = $totalOversDecimal > 0 ? round($score['runs'] / $totalOversDecimal, 2) : 0.0;

// Calculate RRR (Required Run Rate) if target set
if (isset($score['target']) && $score['target'] !== null && isset($score['targetOvers']) && $score['targetOvers'] > 0) {
    $runsLeft = $score['target'] - $score['runs'] + 1;
    $targetOversInt = intval($score['targetOvers']);
    $totalTargetBalls = $targetOversInt * 6;
    $ballsLeft = $totalTargetBalls - $totalBalls;
    $score['rrr'] = $ballsLeft > 0 ? round($runsLeft / ($ballsLeft / 6), 2) : 0.0;
} else {
    $score['rrr'] = null;
}

// Calculate bowler economy
list($bowlerOvers, $bowlerBalls) = parseOvers($score['bowler']['overs']);
$totalBowlerOversDecimal = $bowlerOvers + ($bowlerBalls / 6);
$bowlerRuns = intval($score['bowler']['runs'] ?? 0);
$score['bowler']['eco'] = $totalBowlerOversDecimal > 0 ? round($bowlerRuns / $totalBowlerOversDecimal, 2) : 0.0;

// Save the updated score data back to file
$currentData['score'] = $score;
file_put_contents($file, json_encode($currentData, JSON_PRETTY_PRINT));

// Return success response
echo json_encode(['success' => true, 'message' => 'Score updated successfully']);
exit;
?>
