/**
 * Auto-scaling display formatters for mass/volume quantities -- e.g. a
 * gram value at or above 1000 displays as kg instead of an unwieldy
 * "20000.00g". Built generically over a shared unit ladder so weight and
 * volume can't drift into two different rounding/threshold behaviors.
 *
 * Only weight is actually used anywhere in this module today (every
 * ingredient is 'g' or 'unit', never volume-based) -- formatVolume exists
 * ahead of need, for whenever a volume-based ingredient type shows up.
 */

interface UnitRung {
  unit: string
  factor: number
}

const WEIGHT_LADDER: UnitRung[] = [
  { unit: 'g', factor: 1 },
  { unit: 'kg', factor: 1000 },
]

const VOLUME_LADDER: UnitRung[] = [
  { unit: 'ml', factor: 1 },
  { unit: 'L', factor: 1000 },
  { unit: 'kL', factor: 1_000_000 },
]

function formatScaled(baseValue: number, ladder: UnitRung[], decimals: number): string {
  let rung = ladder[0]
  for (const candidate of ladder) {
    if (Math.abs(baseValue) >= candidate.factor) {
      rung = candidate
    }
  }

  const scaled = baseValue / rung.factor
  const rounded = Math.round(scaled * 10 ** decimals) / 10 ** decimals

  // Trims "20.00" -> "20" and "1.50" -> "1.5" -- a rounded, still-precise
  // number reads cleaner without trailing zeros most values won't have.
  const formatted = Number.isInteger(rounded)
    ? String(rounded)
    : rounded.toFixed(decimals).replace(/0+$/, '').replace(/\.$/, '')

  return `${formatted}${rung.unit}`
}

export function formatWeight(grams: number, decimals = 2): string {
  return formatScaled(grams, WEIGHT_LADDER, decimals)
}

export function formatVolume(milliliters: number, decimals = 2): string {
  return formatScaled(milliliters, VOLUME_LADDER, decimals)
}

/**
 * The common case in this module: a quantity that's either a gram-based
 * weight (auto-scaled) or a plain unit count (e.g. "3 unit(s)"), decided by
 * the ingredient's unit_type -- replaces the repeated
 * `{{ value.toFixed(2) }} {{ unit_type === 'unit' ? 'unit(s)' : 'g' }}`
 * pattern with one call that already knows which branch applies.
 */
export function formatQuantity(value: number, unitType: 'g' | 'unit'): string {
  return unitType === 'unit' ? `${value} unit(s)` : formatWeight(value)
}
