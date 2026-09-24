<template>
  <div>
    <p v-if="description" class="mb-4 text-sm text-gray-600 dark:text-gray-400">{{ description }}</p>

    <div v-if="rows.length" class="border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
      <div v-for="(row, index) in rows" :key="index" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2">
        <select
          v-model.number="row.ingredient_id"
          required
          :aria-label="`${noun} ${index + 1}`"
          class="flex-1 basis-full sm:basis-auto min-w-[10rem] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"
        >
          <option :value="null" disabled>Select {{ noun === 'Byproduct' ? 'a byproduct' : 'an ingredient' }}&hellip;</option>
          <option v-for="opt in available(row.ingredient_id)" :key="opt.id" :value="opt.id">
            {{ opt.name }}{{ noun === 'Byproduct' ? ` — ${opt.byproduct_name}` : '' }}
          </option>
        </select>
        <div class="flex flex-1 sm:flex-none items-center gap-2">
          <input
            v-model.number="row.quantity_per_jar"
            type="number"
            inputmode="decimal"
            min="0"
            step="0.01"
            required
            :aria-label="`Quantity per jar for ${noun.toLowerCase()} ${index + 1}`"
            class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"
          />
          <span class="text-xs text-gray-500 dark:text-gray-400 w-8">{{ unitFor(row.ingredient_id) }}</span>
        </div>
        <IconButton :label="`Remove ${noun.toLowerCase()}`" :class="dangerIconActionClass" @click="rows.splice(index, 1)">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </IconButton>
      </div>
    </div>
    <p v-else class="text-sm text-gray-500 dark:text-gray-400">None yet.</p>

    <button
      type="button"
      :disabled="rows.length >= pool.length"
      class="tap-target-touch inline-flex items-center mt-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 disabled:opacity-40"
      @click="rows.push({ ingredient_id: null, quantity_per_jar: null })"
    >
      + Add {{ noun }}
    </button>
    <p v-if="pool.length === 0 && emptyPoolHint" class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ emptyPoolHint }}</p>
  </div>
</template>

<script setup lang="ts">
import IconButton from '@/Components/IconButton.vue'
import type { RecipeRow } from './RecipeDetailsFields.vue'
import { dangerIconActionClass } from './formClasses'

export interface RecipeIngredientOption {
  id: number
  name: string
  unit_type: 'g' | 'unit'
  byproduct_name: string | null
}

const props = defineProps<{
  // The form's own array (form.ingredients / form.byproducts), edited in place.
  rows: RecipeRow[]
  // Ingredients offerable in this list.
  pool: RecipeIngredientOption[]
  noun: 'Ingredient' | 'Byproduct'
  description?: string
  emptyPoolHint?: string
}>()

const unitFor = (id: number | null) => (props.pool.find((i) => i.id === id)?.unit_type === 'unit' ? 'unit' : 'g')

// Ingredients already picked in another row aren't offered again.
const available = (currentValue: number | null) => {
  const chosen = new Set(props.rows.map((r) => r.ingredient_id).filter((id) => id !== null && id !== currentValue))
  return props.pool.filter((i) => !chosen.has(i.id))
}
</script>
