// Generador QR deliberadamente pequeño: modo byte, versión 6-L (hasta 134 bytes).
// Evita servicios externos y mantiene la URL de identificación dentro del equipo.
const gfExp = new Array(512)
const gfLog = new Array(256)
let x = 1
for (let i = 0; i < 255; i += 1) {
  gfExp[i] = x
  gfLog[x] = i
  x <<= 1
  if (x & 0x100) x ^= 0x11d
}
for (let i = 255; i < 512; i += 1) gfExp[i] = gfExp[i - 255]

const multiply = (a, b) => (a && b ? gfExp[gfLog[a] + gfLog[b]] : 0)

const generator = (degree) => {
  let polynomial = [1]
  for (let i = 0; i < degree; i += 1) {
    const next = new Array(polynomial.length + 1).fill(0)
    polynomial.forEach((value, index) => {
      next[index] ^= value
      next[index + 1] ^= multiply(value, gfExp[i])
    })
    polynomial = next
  }
  return polynomial
}

const errorCorrection = (data, degree) => {
  const result = [...data, ...new Array(degree).fill(0)]
  const divisor = generator(degree)
  for (let i = 0; i < data.length; i += 1) {
    const factor = result[i]
    if (!factor) continue
    divisor.forEach((value, offset) => { result[i + offset] ^= multiply(value, factor) })
  }
  return result.slice(-degree)
}

const pushBits = (bits, value, length) => {
  for (let i = length - 1; i >= 0; i -= 1) bits.push((value >>> i) & 1)
}

const makeCodewords = (text) => {
  const bytes = [...new TextEncoder().encode(text)]
  if (bytes.length > 134) throw new Error('La URL pública es demasiado extensa para la etiqueta QR.')
  const bits = []
  pushBits(bits, 4, 4)
  pushBits(bits, bytes.length, 8)
  bytes.forEach((byte) => pushBits(bits, byte, 8))
  for (let i = 0; i < Math.min(4, 1088 - bits.length); i += 1) bits.push(0)
  while (bits.length % 8) bits.push(0)
  const data = []
  for (let i = 0; i < bits.length; i += 8) data.push(parseInt(bits.slice(i, i + 8).join(''), 2))
  let useFirstPad = true
  while (data.length < 136) {
    data.push(useFirstPad ? 0xec : 0x11)
    useFirstPad = !useFirstPad
  }

  const blocks = [data.slice(0, 68), data.slice(68)]
  const ecc = blocks.map((block) => errorCorrection(block, 18))
  const words = []
  for (let i = 0; i < 68; i += 1) blocks.forEach((block) => words.push(block[i]))
  for (let i = 0; i < 18; i += 1) ecc.forEach((block) => words.push(block[i]))
  return words
}

const formatBits = () => {
  let value = 1 << 3 // corrección L, máscara 0
  let remainder = value << 10
  while (remainder.toString(2).length >= 11) remainder ^= 0x537 << (remainder.toString(2).length - 11)
  return ((value << 10) | remainder) ^ 0x5412
}

export const qrMatrix = (text) => {
  const size = 41
  const matrix = Array.from({ length: size }, () => Array(size).fill(null))
  const finder = (row, col) => {
    for (let r = -1; r <= 7; r += 1) for (let c = -1; c <= 7; c += 1) {
      if (row + r < 0 || row + r >= size || col + c < 0 || col + c >= size) continue
      matrix[row + r][col + c] = r >= 0 && r <= 6 && c >= 0 && c <= 6 && (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4))
    }
  }
  finder(0, 0); finder(size - 7, 0); finder(0, size - 7)
  for (let i = 8; i < size - 8; i += 1) {
    if (matrix[i][6] === null) matrix[i][6] = i % 2 === 0
    if (matrix[6][i] === null) matrix[6][i] = i % 2 === 0
  }
  for (const row of [6, 34]) for (const col of [6, 34]) {
    if (matrix[row][col] !== null) continue
    for (let r = -2; r <= 2; r += 1) for (let c = -2; c <= 2; c += 1) matrix[row + r][col + c] = Math.max(Math.abs(r), Math.abs(c)) !== 1
  }
  for (let i = 0; i < 15; i += 1) {
    const verticalRow = i < 6 ? i : i < 8 ? i + 1 : size - 15 + i
    const horizontalCol = i < 8 ? size - i - 1 : i < 9 ? 15 - i : 15 - i - 1
    matrix[verticalRow][8] = false
    matrix[8][horizontalCol] = false
  }
  matrix[size - 8][8] = true

  const bits = []
  makeCodewords(text).forEach((word) => pushBits(bits, word, 8))
  let bit = 0
  let upward = true
  for (let col = size - 1; col > 0; col -= 2) {
    if (col === 6) col -= 1
    for (let step = 0; step < size; step += 1) {
      const row = upward ? size - 1 - step : step
      for (let offset = 0; offset < 2; offset += 1) if (matrix[row][col - offset] === null) {
        const raw = bits[bit++] === 1
        matrix[row][col - offset] = (row + col - offset) % 2 === 0 ? !raw : raw
      }
    }
    upward = !upward
  }
  const format = formatBits()
  for (let i = 0; i < 15; i += 1) {
    const value = ((format >> i) & 1) === 1
    matrix[i < 6 ? i : i < 8 ? i + 1 : size - 15 + i][8] = value
    matrix[8][i < 8 ? size - i - 1 : i < 9 ? 15 - i : 15 - i - 1] = value
  }
  matrix[size - 8][8] = true
  return matrix
}
