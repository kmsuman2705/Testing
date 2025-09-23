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

// Ensure last 6 balls only
$last6 = explode(" ", $score['last6'] ?? "");
$last6 = array_filter($last6, fn($b)=>$b!=="");
if(count($last6)>6) $last6 = array_slice($last6, -6);
$score['last6'] = implode(" ", $last6);

// Overs correction
$oversParts = explode(".", $score['overs']);
$legalOvers = intval($oversParts[0]);
$balls = intval($oversParts[1] ?? 0);
$score['overs'] = $legalOvers . "." . $balls;

// Recalculate batsman SR
foreach(['batsman1','batsman2'] as $b){
    $runs = intval($score[$b]['runs'] ?? 0);
    $ballsB = intval($score[$b]['balls'] ?? 0);
    $score[$b]['sr'] = $ballsB>0 ? round(($runs/$ballsB)*100,1):0.0;
}

// Calculate CRR
$totalOvers = $legalOvers + ($balls/6);
$score['crr'] = $totalOvers>0 ? round($score['runs'] / $totalOvers,2):0.0;

// Calculate RRR
if(isset($score['target']) && $score['target']!==null && isset($score['targetOvers']) && $score['targetOvers']>0){
    $runsLeft = $score['target'] - $score['runs'] + 1;
    $totalBalls = intval($score['targetOvers'])*6;
    $ballsBowled = $legalOvers*6 + $balls;
    $ballsLeft = $totalBalls - $ballsBowled;
    $score['rrr'] = $ballsLeft>0 ? round($runsLeft/($ballsLeft/6),2):0.0;
} else {
    $score['rrr'] = null;
}

// Recalculate bowler economy
$bowlerOversParts = explode(".", $score['bowler']['overs']);
$bowlerLegalOvers = intval($bowlerOversParts[0]);
$bowlerBalls = intval($bowlerOversParts[1] ?? 0);
$totalBowlerOvers = $bowlerLegalOvers + ($bowlerBalls/6);
$score['bowler']['eco'] = $totalBowlerOvers>0 ? round($score['bowler']['runs'] / $totalBowlerOvers,2):0.0;

// Update main data
$currentData['score'] = $score;

// Save back to file
file_put_contents($file, json_encode($currentData, JSON_PRETTY_PRINT));

// Return success
echo json_encode(['success'=>true,'message'=>'Score updated successfully']);
exit;
?>
