<?php

const GRID_SIZE = 10;
const SHIP_SIZES = [5, 3, 2];
const PLAYER_AMMO = 30;

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
function coordsToCell($row, $col) {
  return chr($row + ord('A')) . ($col + 1);
}

function cellToCoords($cell) {
  $cell = normalizeCell($cell);
  if ($cell === null) {
    return null;
  }

  $row = ord($cell[0]) - ord('A');
  $col = intval(substr($cell, 1), 10) - 1;

  return [$row, $col];
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

function createGame($reuseComputerShips = null, $reusePlayerShips = null) {
  $computerShips = $reuseComputerShips ? cloneShipsForRestart($reuseComputerShips) : generateShips(SHIP_SIZES);
  $playerShips = $reusePlayerShips ? cloneShipsForRestart($reusePlayerShips) : generateShips(SHIP_SIZES);

  return [
    'winner' => null,
    'ammoMax' => PLAYER_AMMO,
    'ammoRemaining' => PLAYER_AMMO,
    'computerBoard' => [
      'ships' => $computerShips,
      'hits' => [],
      'misses' => []
    ],
    'playerBoard' => [
      'ships' => $playerShips,
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

function playerBoardState($board) {
  return [
    'ships' => $board['ships'],
    'hits' => $board['hits'],
    'misses' => $board['misses']
  ];
}

function boardHasShot($board, $cell) {
  return in_array($cell, $board['hits'], true) || in_array($cell, $board['misses'], true);
}

function applyShot(&$board, $cell) {
  $result = 'miss';
  $shipSunk = false;

  foreach ($board['ships'] as &$ship) {
    if (in_array($cell, $ship['cells'], true)) {
      if (!in_array($cell, $ship['hits'], true)) {
        $ship['hits'][] = $cell;
      }
      $board['hits'][] = $cell;
      $result = 'hit';

      if (count($ship['hits']) === count($ship['cells'])) {
        $shipSunk = true;
      }
      break;
    }
  }
  unset($ship);

  if ($result === 'miss') {
    $board['misses'][] = $cell;
  }

  return [
    'result' => $result,
    'shipSunk' => $shipSunk
  ];
}

function remainingShots($board) {
  $shots = [];

  for ($row = 0; $row < GRID_SIZE; $row++) {
    for ($col = 0; $col < GRID_SIZE; $col++) {
      $cell = coordsToCell($row, $col);
      if (!boardHasShot($board, $cell)) {
        $shots[] = $cell;
      }
    }
  }

  return $shots;
}

function neighborCells($cell) {
  $coords = cellToCoords($cell);
  if ($coords === null) {
    return [];
  }

  [$row, $col] = $coords;
  $candidates = [
    [$row - 1, $col],
    [$row + 1, $col],
    [$row, $col - 1],
    [$row, $col + 1]
  ];

  $neighbors = [];
  foreach ($candidates as $candidate) {
    [$r, $c] = $candidate;
    if ($r >= 0 && $r < GRID_SIZE && $c >= 0 && $c < GRID_SIZE) {
      $neighbors[] = coordsToCell($r, $c);
    }
  }

  return $neighbors;
}

function targetShots($board) {
  $targets = [];

  foreach ($board['ships'] as $ship) {
    if (count($ship['hits']) === 0 || count($ship['hits']) === count($ship['cells'])) {
      continue;
    }

    foreach ($ship['hits'] as $hitCell) {
      foreach (neighborCells($hitCell) as $neighbor) {
        if (!boardHasShot($board, $neighbor)) {
          $targets[$neighbor] = true;
        }
      }
    }
  }

  return array_keys($targets);
}

function chooseComputerShot($board) {
  $targets = targetShots($board);
  $choices = count($targets) > 0 ? $targets : remainingShots($board);

  if (count($choices) === 0) {
    return null;
  }

  return $choices[array_rand($choices)];
}

function responseState($game) {
  $sunk = countSunkShips($game['computerBoard']['ships']);
  $playerSunk = countSunkShips($game['playerBoard']['ships']);

  /*
   | Public response contract (consumed by app.js):
   | ok: boolean
   | winner: 'player' | 'computer' | null
   | computerBoard: { hits: string[], misses: string[] }
   | playerBoard: { ships: array, hits: string[], misses: string[] }
   | computerSunk: number
   | playerSunk: number
   | fleetSize: number
   | ammoMax: number
   | ammoRemaining: number
   | playerShot?: { cell: string, result: string, shipSunk?: boolean }
   | computerShot?: { cell: string, result: string, shipSunk?: boolean }
   */
  return [
    'ok' => true,
    'winner' => $game['winner'],
    'computerBoard' => publicBoard($game['computerBoard']),
    'playerBoard' => playerBoardState($game['playerBoard']),
    'computerSunk' => $sunk,
    'playerSunk' => $playerSunk,
    'fleetSize' => count($game['computerBoard']['ships']),
    'ammoMax' => $game['ammoMax'],
    'ammoRemaining' => $game['ammoRemaining']
  ];
}
