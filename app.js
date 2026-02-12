const grid = document.getElementById('grid')
const playerGrid = document.getElementById('player-grid')
const statusText = document.getElementById('status')
const shotsText = document.getElementById('shots')
const hitsText = document.getElementById('hits')
const missesText = document.getElementById('misses')
const sunkText = document.getElementById('sunk')
const playerSunkText = document.getElementById('player-sunk')
const incomingShotsText = document.getElementById('incoming-shots')
const incomingHitsText = document.getElementById('incoming-hits')
const incomingMissesText = document.getElementById('incoming-misses')
const ammoRemainingText = document.getElementById('ammo-remaining')
const globalWinsText = document.getElementById('global-wins')
const globalLossesText = document.getElementById('global-losses')
const globalShotsText = document.getElementById('global-shots')
const globalGamesText = document.getElementById('global-games')
const newGameButton = document.getElementById('new-game')
const restartGameButton = document.getElementById('restart-game')

const ROWS = 10
const COLS = 10
const letters = 'ABCDEFGHIJ'

let gameLocked = false
const computerCells = {}
const playerCells = {}

function applyGlobalStats(globalStats) {
  if (!globalStats) return
  if (globalWinsText) globalWinsText.textContent = globalStats.totalWins ?? 0
  if (globalLossesText) globalLossesText.textContent = globalStats.totalLosses ?? 0
  if (globalShotsText) globalShotsText.textContent = globalStats.totalShotsFired ?? 0
  if (globalGamesText) globalGamesText.textContent = globalStats.totalGamesPlayed ?? 0
}

function fetchGlobalStats() {
  return fetch('api/stats.php')
    .then(res => res.json())
    .then(data => {
      if (data.ok) {
        applyGlobalStats(data.globalStats)
      }
    })
    .catch(() => {})
}

function createGrid(targetGrid, cellMap, enableFire) {
  for (let r = 0; r < ROWS; r++) {
    for (let c = 0; c < COLS; c++) {
      const cell = document.createElement('button')
      const id = `${letters[r]}${c + 1}`

      cell.textContent = id
      cell.dataset.cell = id
      cell.classList.add('cell')
      if (enableFire) {
        cell.setAttribute('aria-label', `Fire at ${id}`)
        cell.addEventListener('click', () => fire(cell))
      } else {
        cell.classList.add('player-cell')
        cell.disabled = true
        cell.setAttribute('aria-label', `Player cell ${id}`)
      }

      targetGrid.appendChild(cell)
      cellMap[id] = cell
    }
  }
}

function applyState(state, message) {
  // Clear board
  Object.values(computerCells).forEach(cell => {
    cell.classList.remove('hit', 'miss')
    cell.disabled = false
  })

  Object.values(playerCells).forEach(cell => {
    cell.classList.remove('hit', 'miss', 'ship')
    cell.disabled = true
  })

  // Apply hits/misses
  state.computerBoard.hits.forEach(id => {
    const cell = computerCells[id]
    if (cell) {
      cell.classList.add('hit')
      cell.disabled = true
    }
  })

  state.computerBoard.misses.forEach(id => {
    const cell = computerCells[id]
    if (cell) {
      cell.classList.add('miss')
      cell.disabled = true
    }
  })

  if (state.playerBoard?.ships) {
    state.playerBoard.ships.forEach(ship => {
      ship.cells.forEach(id => {
        const cell = playerCells[id]
        if (cell) {
          cell.classList.add('ship')
        }
      })
    })
  }

  if (state.playerBoard?.hits) {
    state.playerBoard.hits.forEach(id => {
      const cell = playerCells[id]
      if (cell) {
        cell.classList.add('hit')
      }
    })
  }

  if (state.playerBoard?.misses) {
    state.playerBoard.misses.forEach(id => {
      const cell = playerCells[id]
      if (cell) {
        cell.classList.add('miss')
      }
    })
  }

  // Stats from server state (client math on public board)
  const shots = state.computerBoard.hits.length + state.computerBoard.misses.length
  shotsText.textContent = shots
  hitsText.textContent = state.computerBoard.hits.length
  missesText.textContent = state.computerBoard.misses.length
  if (typeof state.fleetSize === 'number') {
    sunkText.textContent = `${state.computerSunk}/${state.fleetSize}`
    if (playerSunkText) {
      playerSunkText.textContent = `${state.playerSunk}/${state.fleetSize}`
    }
  } else {
    sunkText.textContent = state.computerSunk || 0
    if (playerSunkText) {
      playerSunkText.textContent = state.playerSunk || 0
    }
  }

  if (state.playerBoard) {
    const incomingShots = state.playerBoard.hits.length + state.playerBoard.misses.length
    if (incomingShotsText) incomingShotsText.textContent = incomingShots
    if (incomingHitsText) incomingHitsText.textContent = state.playerBoard.hits.length
    if (incomingMissesText) incomingMissesText.textContent = state.playerBoard.misses.length
  }
  if (ammoRemainingText) {
    const remaining = state.ammoRemaining ?? 0
    const max = state.ammoMax ?? 0
    ammoRemainingText.textContent = `${remaining}/${max}`
  }

  gameLocked = state.winner !== null

  if (message) {
    statusText.textContent = message
  } else if (state.winner === 'player') {
    statusText.textContent = 'You win!'
  } else if (state.winner === 'computer') {
    if ((state.ammoRemaining ?? 0) === 0) {
      statusText.textContent = 'Out of ammo. Computer wins!'
    } else {
      statusText.textContent = 'Computer wins!'
    }
  } else {
    statusText.textContent = 'Fire at will.'
  }

  if (gameLocked) {
    Object.values(computerCells).forEach(cell => (cell.disabled = true))
  }

  applyGlobalStats(state.globalStats)
}

function fire(button) {
  if (gameLocked) return

  fetch('api/fire.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ cell: button.dataset.cell })
  })
    .then(res => res.json())
    .then(data => {
      if (!data.ok) {
        statusText.textContent = data.error || 'Invalid move'
        return
      }

      const messages = []
      if (data.playerShot) {
        if (data.playerShot.result === 'already-fired') {
          messages.push('Already fired there.')
        } else if (data.playerShot.result === 'game-over') {
          messages.push('Game over. Start a new match.')
        } else if (data.playerShot.shipSunk) {
          messages.push('You sunk a ship!')
        } else if (data.playerShot.result === 'hit') {
          messages.push('Hit!')
        } else if (data.playerShot.result === 'miss') {
          messages.push('Miss!')
        }
      }

      if (data.computerShot?.cell && data.computerShot?.result) {
        if (data.computerShot.shipSunk) {
          messages.push(`Computer sunk one of your ships at ${data.computerShot.cell}.`)
        } else if (data.computerShot.result === 'hit') {
          messages.push(`Computer hit at ${data.computerShot.cell}.`)
        } else if (data.computerShot.result === 'miss') {
          messages.push(`Computer missed at ${data.computerShot.cell}.`)
        }
      }

      if (data.winner === 'player' && data.playerShot?.result !== 'game-over') {
        messages.push('You win!')
      } else if (data.winner === 'computer') {
        if ((data.ammoRemaining ?? 0) === 0) {
          messages.push('Out of ammo. Computer wins!')
        } else {
          messages.push('Computer wins!')
        }
      }

      applyState(data, messages.join(' '))
    })
    .catch(() => {
      statusText.textContent = 'Server error. Try again.'
    })
}

function requestGameAction(action, message, shouldRefreshGlobalStats = false) {
  fetch('api/fire.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action })
  })
    .then(res => res.json())
    .then(data => {
      if (!data.ok) {
        statusText.textContent = data.error || 'Unable to reset game'
        return
      }
      applyState(data, message)
      if (shouldRefreshGlobalStats) {
        fetchGlobalStats()
      }
    })
    .catch(() => {
      statusText.textContent = 'Server error. Try again.'
    })
}

newGameButton.addEventListener('click', () => {
  requestGameAction('new-game', 'New game ready. Fire at will.', true)
})

restartGameButton.addEventListener('click', () => {
  requestGameAction('restart-game', 'Board reset. Same ship layout.', true)
})

createGrid(grid, computerCells, true)
createGrid(playerGrid, playerCells, false)
fetchGlobalStats()
requestGameAction('state')
