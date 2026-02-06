<?php

const GRID_SIZE = 10;
const SHIP_SIZES = [5, 3, 2];

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function coordsToCell($row, $col) {
  return chr($row + ord('A')) . ($col + 1);
}

function normalizeCell($cell) {
  $cell = strtoupper(trim($cell));

  if (!preg_match('/^([A-Z])(\d{1,2})$/', $cell, $matches)) {
    return null;
  }

  $row = ord($matches[1]) - ord('A');
  $col = intval($matches[2], 10) - 1;

  if ($row < 0 || $row >= GRID_SIZE || $col < 0 || $col >= GRID_SIZE) {
    return null;
  }

  return coordsToCell($row, $col);
}

function randomShipPlacement($size, $occupied) {
  while (true) {
    $horizontal = rand(0, 1) === 0;

    if ($horizontal) {
      $row = rand(0, GRID_SIZE - 1);
      $col = rand(0, GRID_SIZE - $size);
      $cells = [];
      for ($i = 0; $i < $size; $i++) {
        $cell = coordsToCell($row, $col + $i);
        if (in_array($cell, $occupied, true)) continue 2;
        $cells[] = $cell;
      }
      return $cells;
    }

    $row = rand(0, GRID_SIZE - $size);
    $col = rand(0, GRID_SIZE - 1);
    $cells = [];
    for ($i = 0; $i < $size; $i++) {
      $cell = coordsToCell($row + $i, $col);
      if (in_array($cell, $occupied, true)) continue 2;
      $cells[] = $cell;
    }
    return $cells;
  }
}

function generateShips($sizes) {
  $ships = [];
  $occupied = [];

  foreach ($sizes as $size) {
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

function cloneShipsForRestart($ships) {
  $cloned = [];

  foreach ($ships as $ship) {
    $cloned[] = [
      'size' => $ship['size'],
      'cells' => $ship['cells'],
      'hits' => []
    ];
  }

  return $cloned;
}

function createGame($reuseShips = null) {
  $ships = $reuseShips ? cloneShipsForRestart($reuseShips) : generateShips(SHIP_SIZES);

  return [
    'winner' => null,
    'computerBoard' => [
      'ships' => $ships,
      'hits' => [],
      'misses' => []
    ]
  ];
}

function countSunkShips($ships) {
  $sunk = 0;

  foreach ($ships as $ship) {
    if (count($ship['hits']) >= count($ship['cells'])) {
      $sunk++;
    }
  }

  return $sunk;
}

function publicBoard($board) {
  return [
    'hits' => $board['hits'],
    'misses' => $board['misses']
  ];
}

function responseState($game) {
  $sunk = countSunkShips($game['computerBoard']['ships']);

  /*
   | Public response contract (consumed by app.js):
   | ok: boolean
   | winner: 'player' | null
   | computerBoard: { hits: string[], misses: string[] }
   | computerSunk: number
   | fleetSize: number
   | playerShot?: { cell: string, result: string, shipSunk?: boolean }
   */
  return [
    'ok' => true,
    'winner' => $game['winner'],
    'computerBoard' => publicBoard($game['computerBoard']),
    'computerSunk' => $sunk,
    'fleetSize' => count($game['computerBoard']['ships'])
  ];
}
