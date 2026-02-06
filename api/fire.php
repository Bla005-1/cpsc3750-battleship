<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/game.php';

/*
|--------------------------------------------------------------------------
| INIT (once per session)
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['game'])) {
  $_SESSION['game'] = createGame();
}

$game = &$_SESSION['game'];

if (!isset($game['computerBoard']['ships']) && isset($game['playerBoard']['ships'])) {
  $game = [
    'winner' => $game['winner'] ?? null,
    'computerBoard' => [
      'ships' => $game['playerBoard']['ships'],
      'hits' => $game['computerBoard']['hits'] ?? [],
      'misses' => $game['computerBoard']['misses'] ?? []
    ]
  ];
}
$input = json_decode(file_get_contents('php://input'), true) ?? [];

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/
if (isset($input['action'])) {
  switch ($input['action']) {
    case 'state':
      echo json_encode(responseState($game));
      exit;

    case 'new-game':
      $_SESSION['game'] = createGame();
      echo json_encode(responseState($_SESSION['game']));
      exit;

    case 'restart-game':
      $_SESSION['game'] = createGame($game['computerBoard']['ships']);
      echo json_encode(responseState($_SESSION['game']));
      exit;

    default:
      echo json_encode(['ok' => false, 'error' => 'Unknown action']);
      exit;
  }
}

/*
|--------------------------------------------------------------------------
| FIRE (player only, no AI yet)
|--------------------------------------------------------------------------
*/
if (!isset($input['cell'])) {
  echo json_encode(['ok' => false, 'error' => 'Invalid request']);
  exit;
}

$cell = normalizeCell($input['cell']);

if (!$cell) {
  echo json_encode(['ok' => false, 'error' => 'Invalid coordinate']);
  exit;
}

if ($game['winner'] !== null) {
  echo json_encode(responseState($game) + [
    'playerShot' => ['cell' => $cell, 'result' => 'game-over']
  ]);
  exit;
}

if (in_array($cell, $game['computerBoard']['hits'], true) ||
    in_array($cell, $game['computerBoard']['misses'], true)) {
  echo json_encode(responseState($game) + [
    'playerShot' => ['cell' => $cell, 'result' => 'already-fired']
  ]);
  exit;
}

$result = 'miss';
$shipSunk = false;

foreach ($game['computerBoard']['ships'] as &$ship) {
  if (in_array($cell, $ship['cells'])) {
    $ship['hits'][] = $cell;
    $game['computerBoard']['hits'][] = $cell;
    $result = 'hit';

    if (count($ship['hits']) === count($ship['cells'])) {
      $shipSunk = true;
    }
    break;
  }
}
unset($ship);

if ($result === 'miss') {
  $game['computerBoard']['misses'][] = $cell;
}

$sunk = countSunkShips($game['computerBoard']['ships']);
if ($sunk === count($game['computerBoard']['ships'])) {
  $game['winner'] = 'player';
}

echo json_encode(responseState($game) + [
  'playerShot' => [
    'cell' => $cell,
    'result' => $result,
    'shipSunk' => $shipSunk
  ]
]);
