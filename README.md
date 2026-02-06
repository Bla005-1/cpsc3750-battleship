Iteration 1: Game state moved fully to the server. Added New Game and Restart Same Board actions with explicit state transitions. Fixed reset and desync issues.

Iteration 2: Added a turn-based computer opponent. Server enforces turn order and fires back using basic hunt/target logic.

### Archetecture Snapshot
Server (PHP)
- Owns all game state (ships, shots, turns, win status)
- Validates actions and enforces rules
- Handles computer opponent logic
- Returns full game state after every request

Client (HTML / CSS / JS)
- Renders the UI only
- Sends user actions (fire, new game, restart)
- Re-renders entirely from server responses
- Does not store game logic or state

State Transitions
- New Game: full reset, new ships
- Restart Same Board: reset shots, keep ships
- Fire: process player shot, computer shot, check win