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

let shots = 0
let hits = 0
let misses = 0
let sunk = 0
let gameLocked = false

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
    }
  }
}

function fire(button) {
  if (gameLocked) {
    return
  }

  const cell = button.dataset.cell

  fetch('api/fire.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ cell })
  })
    .then(res => res.json())
    .then(data => {
      if (data.result === 'hit') {
        button.classList.add('hit')
        statusText.textContent = 'Hit!'
        hits += 1
      } else if (data.result === 'miss') {
        button.classList.add('miss')
        statusText.textContent = 'Miss!'
        misses += 1
      } else {
        statusText.textContent = 'Already fired there'
        return
      }

      shots += 1
      updateStats()
      button.disabled = true

      if (data.shipSunk) {
        statusText.textContent = 'You sunk a ship!'
        sunk += 1
        updateStats()
      }

      if (data.gameOver) {
        statusText.textContent = 'You win!'
        endGame()
      }
    })
}

function endGame() {
  gameLocked = true
  document.querySelectorAll('.cell').forEach(cell => {
    cell.disabled = true
  })
}

function resetBoard(message) {
  shots = 0
  hits = 0
  misses = 0
  sunk = 0
  gameLocked = false
  updateStats()
  statusText.textContent = message

  document.querySelectorAll('.cell').forEach(cell => {
    cell.disabled = false
    cell.classList.remove('hit', 'miss')
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
      resetBoard(message)
    })
    .catch(() => {
      statusText.textContent = 'Server error. Try again.'
    })
}

function updateStats() {
  shotsText.textContent = shots
  hitsText.textContent = hits
  missesText.textContent = misses
  sunkText.textContent = sunk
}

newGameButton.addEventListener('click', () => {
  requestGameAction('new-game', 'New game ready. Fire at will.')
})

restartGameButton.addEventListener('click', () => {
  requestGameAction('restart-game', 'Board reset. Same ship layout.')
})

createGrid()
