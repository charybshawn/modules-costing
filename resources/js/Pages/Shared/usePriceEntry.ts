import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { useWeightEntry } from './useWeightEntry'
import { useIngredientSources } from './useIngredientSources'

export interface PriceEntryFormData {
  ingredient_id: number | ''
  package_size_id: number | ''
  purchased_at: string
  qty: number | null
  priced_as_case: boolean
  total_price: number | null
  sku: string
  notes: string
}

export type PriceEntryIngredient = { id: number; name: string; unit_type: 'g' | 'unit' }

/**
 * The stateful half of the Log a Price / Edit Price Entry forms: the
 * ingredient's sources (and inline "add a source"), the kg/g + case weight
 * entry resolving into form.qty, and priced-as-case syncing.
 *
 * Called once per page, not inside PriceEntryFields: ResponsiveFormSections
 * renders each section's slot in both its desktop and mobile trees, so
 * state living in the fields component would exist twice and drift apart.
 * Returned reactive, so PriceEntryFields can bind `entry.weightValue` etc.
 * with refs unwrapped.
 */
export function usePriceEntry(
  form: any,
  ingredients: () => PriceEntryIngredient[],
  weight: { initialGrams: number | null; defaultUnit: 'kg' | 'g'; editingEntryId?: number },
) {
  const selectedIngredient = computed(() => ingredients().find((i) => i.id === form.ingredient_id) ?? null)
  const isGramBased = computed(() => selectedIngredient.value?.unit_type !== 'unit')

  const { sources, addSource, addSourceSaving, addSourceError } = useIngredientSources(computed(() => form.ingredient_id))
  const selectedSource = computed(() => sources.value.find((s) => s.id === form.package_size_id) ?? null)

  // Picking either option pre-fills qty from the selected source's
  // registered size -- still a plain number afterward, editable like any
  // other qty entry (e.g. if the actual invoiced qty differs slightly).
  const applyPricedAsCase = (pricedAsCase: boolean) => {
    form.priced_as_case = pricedAsCase
    if (selectedSource.value) {
      form.qty = pricedAsCase
        ? selectedSource.value.package_size * selectedSource.value.units_per_case
        : selectedSource.value.package_size
    }
  }

  // Switching to a different ingredient invalidates whatever source was
  // picked for the previous one -- but only on a real change, not on mount,
  // so a cloned/preselected/saved package_size_id survives the load.
  watch(
    () => form.ingredient_id,
    () => {
      form.package_size_id = ''
    },
  )

  const addingSource = ref(false)
  const newSourceProvider = ref('')
  const newSourceBrand = ref('')
  const newSourceSize = ref<number | null>(null)
  const newSourceUnitsPerCase = ref<number>(1)

  const startAddSource = () => {
    addingSource.value = true
    newSourceProvider.value = ''
    newSourceBrand.value = ''
    newSourceSize.value = null
    newSourceUnitsPerCase.value = 1
  }

  const cancelAddSource = () => {
    addingSource.value = false
  }

  const saveNewSource = async () => {
    if (!newSourceProvider.value || !newSourceSize.value || !form.ingredient_id) return
    const id = await addSource(form.ingredient_id, newSourceProvider.value, newSourceBrand.value || null, newSourceSize.value, newSourceUnitsPerCase.value || 1)
    if (id !== null) {
      form.package_size_id = id
      addingSource.value = false
    }
  }

  // Weight input for gram-based ingredients -- kg/g toggle plus an optional
  // case breakdown, resolving to a single grams total stored in form.qty.
  const { weightValue, weightUnit, isCase, eachesPerCase, totalGrams } = useWeightEntry(
    isGramBased.value ? weight.initialGrams : null,
    weight.defaultUnit,
  )

  watch(totalGrams, (total) => {
    if (isGramBased.value && total !== null) {
      form.qty = total
    }
  })

  // What this entry works out to ($/kg, or $/unit), live as it's typed,
  // checked against the ingredient's usual price so a slipped unit
  // (2.27 g typed for a 2.27 kg bag) shows up before it's saved.
  const otherPrices = ref<number[]>([])
  watch(
    () => form.ingredient_id,
    async (id) => {
      otherPrices.value = []
      if (!id) return
      try {
        const { data } = await axios.get(route('admin.costing.ingredients.price-options', id))
        otherPrices.value = (data.options as Array<{ price_history_entry_id: number | null; price_per_unit: number | null }>)
          .filter((o) => o.price_per_unit !== null && o.price_per_unit > 0 && o.price_history_entry_id !== weight.editingEntryId)
          .map((o) => Number(o.price_per_unit))
      } catch {
        // No comparison then -- the live price still shows.
      }
    },
    { immediate: true },
  )

  const livePrice = computed<number | null>(() => {
    const total = Number(form.total_price)
    const qty = Number(form.qty)
    if (!total || !qty || total <= 0 || qty <= 0) return null
    return isGramBased.value ? (total / qty) * 1000 : total / qty
  })

  // The median of the ingredient's other sources' latest prices.
  const usualPrice = computed<number | null>(() => {
    const prices = [...otherPrices.value].sort((a, b) => a - b)
    if (!prices.length) return null
    const mid = Math.floor(prices.length / 2)
    return prices.length % 2 ? prices[mid] : (prices[mid - 1] + prices[mid]) / 2
  })

  // More than 5x off the usual price either way -- almost always a unit or
  // decimal slip rather than a real price change.
  const priceRatio = computed<number | null>(() =>
    livePrice.value !== null && usualPrice.value !== null ? livePrice.value / usualPrice.value : null)
  const priceLooksOff = computed(() => priceRatio.value !== null && (priceRatio.value > 5 || priceRatio.value < 0.2))

  // Gram-based ingredients express "priced as a case" through the weight
  // entry's own "This was a case" checkbox rather than the one-package /
  // whole-case radios (unit-type ingredients only); the form autosaves, so
  // keep it in sync live.
  watch(isCase, (value) => {
    if (isGramBased.value) {
      form.priced_as_case = value
    }
  })

  return reactive({
    isGramBased,
    sources,
    selectedSource,
    applyPricedAsCase,
    addingSource,
    newSourceProvider,
    newSourceBrand,
    newSourceSize,
    newSourceUnitsPerCase,
    startAddSource,
    cancelAddSource,
    saveNewSource,
    addSourceSaving,
    addSourceError,
    weightValue,
    weightUnit,
    isCase,
    eachesPerCase,
    livePrice,
    usualPrice,
    priceRatio,
    priceLooksOff,
  })
}

export type PriceEntryState = ReturnType<typeof usePriceEntry>
