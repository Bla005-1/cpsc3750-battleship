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

/*
|--------------------------------------------------------------------------
| INIT GAME (first request only)
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['game'])) {
  $ships = [];
  $occupied = [];

  foreach ($SHIP_SIZES as $size) {
    $cells = randomShipPlacement($size, $occupied);
    $occupied = array_merge($occupied, $cells);
    $ships[] = [
      'size' => $size,
      'cells' => $cells,
      'hits' => []
    ];
  }

  $_SESSION['game'] = [
    'ships' => $ships,
    'shots' => []
  ];
}

/*
|--------------------------------------------------------------------------
| HANDLE REQUEST
|--------------------------------------------------------------------------
*/
$input = json_decode(file_get_contents('php://input'), true);

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
