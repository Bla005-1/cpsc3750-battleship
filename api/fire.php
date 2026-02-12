<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/game.php';
require_once __DIR__ . '/stats_store.php';

function stateWithGlobalStats($game, $extra = []) {
  $stats = readGlobalStats();

  return responseState($game) + [
    'globalStats' => $stats
  ] + $extra;
}

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
    ],
    'playerBoard' => [
      'ships' => generateShips(SHIP_SIZES),
      'hits' => [],
      'misses' => []
    ]
  ];
}

if (!isset($game['computerBoard']['ships'])) {
  $game['computerBoard'] = [
    'ships' => generateShips(SHIP_SIZES),
    'hits' => [],
    'misses' => []
  ];
}

if (!isset($game['playerBoard']['ships'])) {
  $game['playerBoard'] = [
    'ships' => generateShips(SHIP_SIZES),
    'hits' => [],
    'misses' => []
  ];
}

$game['computerBoard']['hits'] = $game['computerBoard']['hits'] ?? [];
$game['computerBoard']['misses'] = $game['computerBoard']['misses'] ?? [];
$game['playerBoard']['hits'] = $game['playerBoard']['hits'] ?? [];
$game['playerBoard']['misses'] = $game['playerBoard']['misses'] ?? [];
$game['winner'] = $game['winner'] ?? null;
$shotsTaken = count($game['computerBoard']['hits']) + count($game['computerBoard']['misses']);
$game['ammoMax'] = isset($game['ammoMax']) ? max(0, (int) $game['ammoMax']) : PLAYER_AMMO;
$game['ammoRemaining'] = isset($game['ammoRemaining'])
  ? max(0, (int) $game['ammoRemaining'])
  : max(0, $game['ammoMax'] - $shotsTaken);

if ($game['winner'] === null && $game['ammoRemaining'] <= 0) {
  $game['winner'] = 'computer';
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
      echo json_encode(stateWithGlobalStats($game));
      exit;

    case 'new-game':
      $_SESSION['game'] = createGame();
      echo json_encode(stateWithGlobalStats($_SESSION['game']));
      exit;

    case 'restart-game':
      $_SESSION['game'] = createGame($game['computerBoard']['ships'], $game['playerBoard']['ships']);
      echo json_encode(stateWithGlobalStats($_SESSION['game']));
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
  echo json_encode(stateWithGlobalStats($game, [
    'playerShot' => ['cell' => $cell, 'result' => 'game-over']
  ]));
  exit;
}

if (boardHasShot($game['computerBoard'], $cell)) {
  echo json_encode(stateWithGlobalStats($game, [
    'playerShot' => ['cell' => $cell, 'result' => 'already-fired']
  ]));
  exit;
}

$winnerBefore = $game['winner'];
$playerShot = applyShot($game['computerBoard'], $cell);
$game['ammoRemaining'] = max(0, $game['ammoRemaining'] - 1);

$sunk = countSunkShips($game['computerBoard']['ships']);
if ($sunk === count($game['computerBoard']['ships'])) {
  $game['winner'] = 'player';
} else if ($game['ammoRemaining'] === 0) {
  $game['winner'] = 'computer';
}

$computerShot = null;

if ($game['winner'] === null) {
  $computerCell = chooseComputerShot($game['playerBoard']);

  if ($computerCell === null) {
    $computerShot = [
      'cell' => null,
      'result' => 'no-moves'
    ];
  } else {
    $computerShot = applyShot($game['playerBoard'], $computerCell);
    $computerShot['cell'] = $computerCell;

    $playerSunk = countSunkShips($game['playerBoard']['ships']);
    if ($playerSunk === count($game['playerBoard']['ships'])) {
      $game['winner'] = 'computer';
    }
  }
}

$statsDelta = ['totalShotsFired' => 1];

if ($winnerBefore === null && $game['winner'] !== null) {
  $statsDelta['totalGamesPlayed'] = 1;
  if ($game['winner'] === 'player') {
    $statsDelta['totalWins'] = 1;
  } else if ($game['winner'] === 'computer') {
    $statsDelta['totalLosses'] = 1;
  }
}

try {
  $globalStats = updateGlobalStats($statsDelta);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Unable to persist global stats']);
  exit;
}

echo json_encode(responseState($game) + [
  'globalStats' => $globalStats,
  'playerShot' => [
    'cell' => $cell,
    'result' => $playerShot['result'],
    'shipSunk' => $playerShot['shipSunk']
  ],
  'computerShot' => $computerShot
]);
