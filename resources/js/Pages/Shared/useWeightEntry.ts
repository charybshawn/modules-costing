import { computed, ref, watch } from 'vue'

/**
 * Drives the "Weight" input on the price-logging forms: a value + kg/g unit
 * toggle (so nobody has to hand-convert an invoice quoted in kg into
 * grams), plus an optional case breakdown (weight per each x eaches per
 * case, matching how wholesalers like GFS actually quote -- "2.27
 * Kilograms/Each, 2 Eaches/Case"). Always resolves to a single grams total
 * for storage; the case fields stay untouched (and the total just equals
 * the plain weight) for a normal single-item purchase.
 */
export function useWeightEntry(initialGrams: number | null = null, defaultUnit: 'kg' | 'g' = 'g') {
  const weightValue = ref<number | null>(initialGrams)
  const weightUnit = ref<'kg' | 'g'>(defaultUnit)
  const isCase = ref(false)
  const eachesPerCase = ref<number | null>(null)

  // Switching units converts the number in place so the actual quantity
  // doesn't silently change (2.27 kg -> 2270 g, not "2.27 g").
  watch(weightUnit, (newUnit, oldUnit) => {
    if (weightValue.value === null) return
    weightValue.value = oldUnit === 'kg' && newUnit === 'g'
      ? Math.round(weightValue.value * 1000 * 100) / 100
      : Math.round((weightValue.value / 1000) * 10000) / 10000
  })

  const totalGrams = computed(() => {
    if (weightValue.value === null) return null
    const grams = weightUnit.value === 'kg' ? weightValue.value * 1000 : weightValue.value
    const eaches = isCase.value && eachesPerCase.value ? eachesPerCase.value : 1
    return Math.round(grams * eaches * 100) / 100
  })

  return { weightValue, weightUnit, isCase, eachesPerCase, totalGrams }
}
