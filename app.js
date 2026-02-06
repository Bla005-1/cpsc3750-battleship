const grid = document.getElementById('grid')
const statusText = document.getElementById('status')
const shotsText = document.getElementById('shots')
const hitsText = document.getElementById('hits')
const missesText = document.getElementById('misses')
const sunkText = document.getElementById('sunk')
const newGameButton = document.getElementById('new-game')
const restartGameButton = document.getElementById('restart-game')

const ROWS = 10
const COLS = 10
const letters = 'ABCDEFGHIJ'

let gameLocked = false
const cells = {}

function createGrid() {
  for (let r = 0; r < ROWS; r++) {
    for (let c = 0; c < COLS; c++) {
      const cell = document.createElement('button')
      const id = `${letters[r]}${c + 1}`

      cell.textContent = id
      cell.dataset.cell = id
      cell.classList.add('cell')
      cell.setAttribute('aria-label', `Fire at ${id}`)

      cell.addEventListener('click', () => fire(cell))

      grid.appendChild(cell)
      cells[id] = cell
    }
  }
}

function applyState(state, message) {
  // Clear board
  Object.values(cells).forEach(cell => {
    cell.classList.remove('hit', 'miss')
    cell.disabled = false
  })

  // Apply hits/misses
  state.computerBoard.hits.forEach(id => {
    const cell = cells[id]
    if (cell) {
      cell.classList.add('hit')
      cell.disabled = true
    }
  })

  state.computerBoard.misses.forEach(id => {
    const cell = cells[id]
    if (cell) {
      cell.classList.add('miss')
      cell.disabled = true
    }
  })

  // Stats from server state (client math on public board)
  const shots = state.computerBoard.hits.length + state.computerBoard.misses.length
  shotsText.textContent = shots
  hitsText.textContent = state.computerBoard.hits.length
  missesText.textContent = state.computerBoard.misses.length
  if (typeof state.fleetSize === 'number') {
    sunkText.textContent = `${state.computerSunk}/${state.fleetSize}`
  } else {
    sunkText.textContent = state.computerSunk || 0
  }

  gameLocked = state.winner !== null

  if (message) {
    statusText.textContent = message
  } else if (state.winner === 'player') {
    statusText.textContent = 'You win!'
  } else {
    statusText.textContent = 'Fire at will.'
  }

  if (gameLocked) {
    Object.values(cells).forEach(cell => (cell.disabled = true))
  }
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

      let message = ''
      if (data.playerShot) {
        if (data.playerShot.result === 'already-fired') {
          message = 'Already fired there.'
        } else if (data.playerShot.result === 'game-over') {
          message = 'Game over. Start a new match.'
        } else if (data.playerShot.shipSunk) {
          message = 'You sunk a ship!'
        } else if (data.playerShot.result === 'hit') {
          message = 'Hit!'
        } else if (data.playerShot.result === 'miss') {
          message = 'Miss!'
        }
      }

      if (data.winner === 'player' && data.playerShot?.result !== 'game-over') {
        message = message ? `${message} You win!` : 'You win!'
      }

      applyState(data, message)
    })
    .catch(() => {
      statusText.textContent = 'Server error. Try again.'
    })
}

function requestGameAction(action, message) {
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
    })
    .catch(() => {
      statusText.textContent = 'Server error. Try again.'
    })
}

newGameButton.addEventListener('click', () => {
  requestGameAction('new-game', 'New game ready. Fire at will.')
})

restartGameButton.addEventListener('click', () => {
  requestGameAction('restart-game', 'Board reset. Same ship layout.')
})

createGrid()
requestGameAction('state')
