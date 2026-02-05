<?php
session_start();
header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/
$GRID_SIZE = 10;
$SHIP_SIZES = [5, 3, 2];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function cellToCoords($cell) {
  $row = ord($cell[0]) - ord('A');
  $col = intval(substr($cell, 1)) - 1;
  return [$row, $col];
}

function coordsToCell($row, $col) {
  return chr($row + ord('A')) . ($col + 1);
}

function randomShipPlacement($size, $occupied) {
  $horizontal = rand(0, 1) === 0;

  while (true) {
    if ($horizontal) {
      $row = rand(0, 9);
      $col = rand(0, 10 - $size);
      $cells = [];
      for ($i = 0; $i < $size; $i++) {
        $cell = coordsToCell($row, $col + $i);
        if (in_array($cell, $occupied)) continue 2;
        $cells[] = $cell;
      }
      return $cells;
    } else {
      $row = rand(0, 10 - $size);
      $col = rand(0, 9);
      $cells = [];
      for ($i = 0; $i < $size; $i++) {
        $cell = coordsToCell($row + $i, $col);
        if (in_array($cell, $occupied)) continue 2;
        $cells[] = $cell;
      }
      return $cells;
    }
  }
}

function generateShips($shipSizes) {
  $ships = [];
  $occupied = [];

  foreach ($shipSizes as $size) {
    $cells = randomShipPlacement($size, $occupied);
    $occupied = array_merge($occupied, $cells);
    $ships[] = [
      'size' => $size,
      'cells' => $cells,
      'hits' => []
    ];
  }

  return $ships;
}

function initGame($shipSizes, $existingShips = null) {
  if ($existingShips === null) {
    $ships = generateShips($shipSizes);
  } else {
    $ships = $existingShips;
    foreach ($ships as &$ship) {
      $ship['hits'] = [];
    }
  }

  return [
    'ships' => $ships,
    'shots' => []
  ];
}

/*
|--------------------------------------------------------------------------
| INIT GAME (first request only)
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['game'])) {
  $_SESSION['game'] = initGame($SHIP_SIZES);
}

/*
|--------------------------------------------------------------------------
| HANDLE REQUEST
|--------------------------------------------------------------------------
*/
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
  $input = [];
}

if (isset($input['action'])) {
  $action = $input['action'];

  if ($action === 'new-game') {
    $_SESSION['game'] = initGame($SHIP_SIZES);
    echo json_encode(['ok' => true, 'action' => 'new-game']);
    exit;
  }

  if ($action === 'restart-game') {
    if (!isset($_SESSION['game'])) {
      $_SESSION['game'] = initGame($SHIP_SIZES);
    } else {
      $_SESSION['game'] = initGame($SHIP_SIZES, $_SESSION['game']['ships']);
    }
    echo json_encode(['ok' => true, 'action' => 'restart-game']);
    exit;
  }

  echo json_encode(['error' => 'Unknown action']);
  exit;
}

if (!isset($input['cell'])) {
  echo json_encode(['error' => 'Invalid request']);
  exit;
}

$cell = strtoupper($input['cell']);
$game = &$_SESSION['game'];

if (in_array($cell, $game['shots'])) {
  echo json_encode([
    'result' => 'already-fired',
    'shipSunk' => false,
    'gameOver' => false
  ]);
  exit;
}

$game['shots'][] = $cell;
$result = 'miss';
$shipSunk = false;

foreach ($game['ships'] as &$ship) {
  if (in_array($cell, $ship['cells'])) {
    $ship['hits'][] = $cell;
    $result = 'hit';

    if (count($ship['hits']) === count($ship['cells'])) {
      $shipSunk = true;
    }
    break;
  }
}

/*
|--------------------------------------------------------------------------
| GAME OVER CHECK
|--------------------------------------------------------------------------
*/
$allSunk = true;
foreach ($game['ships'] as $ship) {
  if (count($ship['hits']) < count($ship['cells'])) {
    $allSunk = false;
    break;
  }
}

echo json_encode([
  'result' => $result,
  'shipSunk' => $shipSunk,
  'gameOver' => $allSunk
]);
