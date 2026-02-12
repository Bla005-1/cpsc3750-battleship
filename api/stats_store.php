<?php

const GLOBAL_STATS_FILE = __DIR__ . '/stats.json';

function defaultGlobalStats() {
  return [
    'totalWins' => 0,
    'totalLosses' => 0,
    'totalShotsFired' => 0,
    'totalGamesPlayed' => 0
  ];
}

function normalizeGlobalStats($stats) {
  $defaults = defaultGlobalStats();
  $normalized = $defaults;

  if (!is_array($stats)) {
    return $normalized;
  }

  foreach ($defaults as $key => $defaultValue) {
    $value = $stats[$key] ?? $defaultValue;
    $normalized[$key] = is_numeric($value) ? max(0, (int) $value) : $defaultValue;
  }

  return $normalized;
}

function readGlobalStats() {
  if (!file_exists(GLOBAL_STATS_FILE)) {
    return defaultGlobalStats();
  }

  $handle = fopen(GLOBAL_STATS_FILE, 'r');
  if ($handle === false) {
    return defaultGlobalStats();
  }

  try {
    if (!flock($handle, LOCK_SH)) {
      return defaultGlobalStats();
    }

    $contents = stream_get_contents($handle);
    flock($handle, LOCK_UN);
  } finally {
    fclose($handle);
  }

  if ($contents === false || trim($contents) === '') {
    return defaultGlobalStats();
  }

  $decoded = json_decode($contents, true);

  return normalizeGlobalStats($decoded);
}

function updateGlobalStats($delta) {
  $delta = is_array($delta) ? $delta : [];
  $defaults = defaultGlobalStats();

  $handle = fopen(GLOBAL_STATS_FILE, 'c+');
  if ($handle === false) {
    throw new RuntimeException('Unable to open stats storage.');
  }

  try {
    if (!flock($handle, LOCK_EX)) {
      throw new RuntimeException('Unable to lock stats storage.');
    }

    $contents = stream_get_contents($handle);
    $stats = normalizeGlobalStats(json_decode($contents ?: '', true));

    foreach ($defaults as $key => $_) {
      if (!array_key_exists($key, $delta)) {
        continue;
      }
      $increment = is_numeric($delta[$key]) ? (int) $delta[$key] : 0;
      $stats[$key] = max(0, $stats[$key] + $increment);
    }

    rewind($handle);
    ftruncate($handle, 0);

    $json = json_encode($stats, JSON_PRETTY_PRINT);
    if ($json === false) {
      throw new RuntimeException('Unable to encode stats.');
    }

    if (fwrite($handle, $json) === false) {
      throw new RuntimeException('Unable to write stats.');
    }

    fflush($handle);
    flock($handle, LOCK_UN);
  } finally {
    fclose($handle);
  }

  return $stats;
}
