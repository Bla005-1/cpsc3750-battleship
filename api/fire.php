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
      $_SESSION['game'] = createGame($game['computerBoard']['ships'], $game['playerBoard']['ships']);
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

if (boardHasShot($game['computerBoard'], $cell)) {
  echo json_encode(responseState($game) + [
    'playerShot' => ['cell' => $cell, 'result' => 'already-fired']
  ]);
  exit;
}

$playerShot = applyShot($game['computerBoard'], $cell);

$sunk = countSunkShips($game['computerBoard']['ships']);
if ($sunk === count($game['computerBoard']['ships'])) {
  $game['winner'] = 'player';
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

echo json_encode(responseState($game) + [
  'playerShot' => [
    'cell' => $cell,
    'result' => $playerShot['result'],
    'shipSunk' => $playerShot['shipSunk']
  ],
  'computerShot' => $computerShot
]);
