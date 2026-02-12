Folder name for htdocs
battleship

Localhost URL
http://localhost/battleship

Feature using JSON
Global statistics feature stores total wins, losses, shots, and games in stats.json

Loom Recording
https://www.loom.com/share/51251a50179f45318773e386a224ff59


Iteration 1: Game state moved fully to the server. Added New Game and Restart Same Board actions with explicit state transitions. Fixed reset and desync issues.

Iteration 2: Added a turn-based computer opponent. Server enforces turn order and fires back using basic hunt/target logic.

Iteration 3: Added persistent global statistics in `api/stats.json` with server-side file-locking updates. Totals are updated only for validated events (official player shots and first game-end transition).

### Archetecture Snapshot
Server (PHP)
- Owns all game state (ships, shots, turns, win status)
- Validates actions and enforces rules
- Handles computer opponent logic
- Owns persistent global stats (`totalWins`, `totalLosses`, `totalShotsFired`, `totalGamesPlayed`)
- Returns full game state after every request

Client (HTML / CSS / JS)
- Renders the UI only
- Sends user actions (fire, new game, restart)
- Re-renders entirely from server responses
- Does not store game logic or state
- Reads global stats from API responses / `api/stats.php` and never writes stats directly

State Transitions
- New Game: full reset, new ships
- Restart Same Board: reset shots, keep ships
- Fire: process player shot, computer shot, check win
