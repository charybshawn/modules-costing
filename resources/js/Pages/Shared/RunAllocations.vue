<template>
  <div v-if="rows.length" class="space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-400">
      Fill each flavour's batches into its sizes. Volume is what the batches make; each unit takes its size's fill weight out of it.
    </p>

    <div v-for="row in rows" :key="row.recipe_id" class="border border-gray-200 dark:border-gray-700 rounded-md">
      <div class="flex flex-wrap items-baseline justify-between gap-x-3 px-4 py-2 bg-gray-50 dark:bg-gray-700/50 rounded-t-md">
        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ row.recipe_name }}</span>
        <span class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
          {{ formatGrams(row.allocated_g) }}g of {{ formatGrams(row.volume_g) }}g filled
        </span>
      </div>

      <div v-if="row.sizes.length" class="divide-y divide-gray-200 dark:divide-gray-700">
        <div v-for="size in row.sizes" :key="size.id" class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-4 py-2">
          <label :for="`alloc-${size.id}`" class="text-sm text-gray-700 dark:text-gray-300 flex-1 min-w-[8rem]">
            {{ size.label }}
            <span v-if="size.label !== `${size.fill_size_g}g`" class="text-xs text-gray-500 dark:text-gray-400">({{ size.fill_size_g }}g)</span>
          </label>
          <button
            v-if="size.maxMore > 0 && !disabled"
            type="button"
            class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
            @click="setUnits(size.id, size.units + size.maxMore)"
          >
            fill remaining ({{ size.maxMore }} more)
          </button>
          <input
            :id="`alloc-${size.id}`"
            :value="size.units"
            type="number"
            inputmode="numeric"
            min="0"
            :disabled="disabled"
            class="w-24 sm:w-28 tap-target rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60"
            @input="setUnits(size.id, ($event.target as HTMLInputElement).value)"
          />
        </div>
      </div>
      <p v-else class="px-4 py-2 text-sm text-amber-600 dark:text-amber-500">This recipe has no sizes yet. Add one on the recipe.</p>

      <p v-if="row.message" class="px-4 py-2 text-sm text-red-600 dark:text-red-400 border-t border-gray-200 dark:border-gray-700">{{ row.message }}</p>
      <p v-else class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-200 dark:border-gray-700 tabular-nums">
        {{ formatGrams(Math.max(row.remaining_g, 0)) }}g left over
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { formatGrams, type RecipeAllocation } from './runAllocation'

const props = defineProps<{
  rows: RecipeAllocation[]
  // The form's own array, edited in place: one {recipe_size_id, units} per size.
  allocations: Array<{ recipe_size_id: number; units: number }>
  disabled?: boolean
}>()

const setUnits = (sizeId: number, value: number | string) => {
  const units = Math.max(0, Math.floor(Number(value) || 0))
  const existing = props.allocations.find((row) => row.recipe_size_id === sizeId)
  if (existing) existing.units = units
  else props.allocations.push({ recipe_size_id: sizeId, units })
}
</script>
