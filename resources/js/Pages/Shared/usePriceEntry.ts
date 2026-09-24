import { computed, reactive, ref, watch } from 'vue'
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
  weight: { initialGrams: number | null; defaultUnit: 'kg' | 'g' },
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
  })
}

export type PriceEntryState = ReturnType<typeof usePriceEntry>
