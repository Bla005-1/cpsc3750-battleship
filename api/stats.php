<?php
header('Content-Type: application/json');

require_once __DIR__ . '/stats_store.php';

try {
  echo json_encode([
    'ok' => true,
    'globalStats' => readGlobalStats()
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Unable to load global stats'
  ]);
}
