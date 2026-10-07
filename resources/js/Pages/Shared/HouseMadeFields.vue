<template>
  <div class="space-y-6">
    <RecipeLinesFields
      :rows="form.components"
      :pool="pool"
      noun="Ingredient"
      description="What one prep batch is made from, before cooking."
    />
    <InputError :message="form.errors.components" />

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">One prep batch makes (g)</label>
      <input v-model.number="form.yield_g" type="number" inputmode="decimal" min="0" step="0.01" :class="inputClass" placeholder="e.g. 400" />
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Weigh it after cooking -- this is what the batch cost is spread over.</p>
      <InputError :message="form.errors.yield_g" />
    </div>

    <dl class="grid grid-cols-2 gap-4 border-t border-gray-200 dark:border-gray-700 pt-4">
      <div>
        <dt class="text-sm text-gray-500 dark:text-gray-400">Prep batch cost</dt>
        <dd class="text-lg font-semibold text-gray-900 dark:text-white tabular-nums">${{ cost.batch.toFixed(2) }}</dd>
      </div>
      <div>
        <dt class="text-sm text-gray-500 dark:text-gray-400">Cost per kg</dt>
        <dd class="text-lg font-semibold text-gray-900 dark:text-white tabular-nums">{{ cost.perKg === null ? '—' : `$${cost.perKg.toFixed(2)}` }}</dd>
      </div>
      <p v-if="cost.anyStale || cost.anyMissing" class="col-span-2 text-xs text-amber-600 dark:text-amber-500">
        Estimate only: {{ cost.anyMissing ? 'one or more ingredients have no logged price' : 'one or more ingredients are using a price that needs updating' }}.
      </p>
    </dl>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import InputError from '@/Components/InputError.vue'
import RecipeLinesFields from './RecipeLinesFields.vue'
import { inputClass } from './formClasses'

export interface ComponentOption {
  id: number
  name: string
  unit_type: 'g' | 'unit'
  byproduct_name: string | null
  status: 'ok' | 'no_price_this_week'
  effective_price: number | null
  stale_effective_price: number | null
}

const props = defineProps<{
  // The page's usePersistedForm -- components and yield_g bind straight in.
  form: any
  pool: ComponentOption[]
}>()

// Same effective-price math as a recipe's Costing Breakdown, spread over
// the cooked yield.
const cost = computed(() => {
  let batch = 0
  let anyStale = false
  let anyMissing = false

  for (const row of props.form.components as Array<{ ingredient_id: number | null; quantity_per_jar: number | null }>) {
    const option = props.pool.find((o) => o.id === row.ingredient_id)
    const quantity = row.quantity_per_jar ?? 0
    if (!option || !quantity) continue
    const price = option.status === 'ok' ? option.effective_price : option.stale_effective_price
    if (price === null) {
      anyMissing = true
      continue
    }
    if (option.status !== 'ok') anyStale = true
    batch += option.unit_type === 'unit' ? quantity * price : (quantity * price) / 1000
  }

  const yieldG = typeof props.form.yield_g === 'number' && props.form.yield_g > 0 ? props.form.yield_g : null
  return { batch, perKg: yieldG === null ? null : (batch / yieldG) * 1000, anyStale, anyMissing }
})
</script>
