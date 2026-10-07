<template>
  <section class="rounded-lg border border-gray-200 dark:border-gray-700">
    <button
      type="button"
      class="tap-target-touch flex w-full items-center justify-between gap-3 px-4 py-3 text-left"
      :aria-expanded="open"
      aria-controls="batch-calculator-body"
      @click="open = !open"
    >
      <span>
        <span class="block text-base font-medium text-gray-900 dark:text-white">Batch calculator</span>
        <span class="block text-xs text-gray-500 dark:text-gray-400">How much of everything a batch takes</span>
      </span>
      <svg class="w-5 h-5 shrink-0 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <div v-if="open" id="batch-calculator-body" class="border-t border-gray-200 dark:border-gray-700 px-4 py-4 space-y-5">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label for="batch-calculator-size" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Units per batch</label>
          <input id="batch-calculator-size" v-model.number="batchSize" type="number" inputmode="numeric" min="1" step="1" :class="inputClass" />
        </div>
        <div>
          <label for="batch-calculator-batches" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Batches</label>
          <input id="batch-calculator-batches" v-model.number="batches" type="number" inputmode="numeric" min="1" step="1" :class="inputClass" />
        </div>
      </div>

      <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4">
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Planned units</dt>
          <dd class="text-lg font-semibold text-gray-900 dark:text-white tabular-nums">{{ units }}</dd>
        </div>
        <div v-if="filledUnits !== null">
          <dt class="text-sm text-gray-500 dark:text-gray-400">Fills about</dt>
          <dd class="text-lg font-semibold text-gray-900 dark:text-white tabular-nums">{{ filledUnits }} units</dd>
          <dd class="text-xs text-gray-500 dark:text-gray-400">at {{ fillG }}g each</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Ingredient cost</dt>
          <dd class="text-lg font-semibold text-gray-900 dark:text-white tabular-nums">${{ (recipeCost * units).toFixed(2) }}</dd>
          <dd v-if="costIsEstimate" class="text-xs text-amber-600 dark:text-amber-400">Estimate</dd>
        </div>
      </dl>

      <div v-if="prepPerUnit.length">
        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Prep first</h3>
        <ul class="mt-1 divide-y divide-gray-200 dark:divide-gray-700">
          <li v-for="prep in prepPerUnit" :key="prep.id" class="flex flex-wrap items-baseline justify-between gap-x-3 py-2 text-sm">
            <span class="text-gray-900 dark:text-white">{{ prep.name }}</span>
            <span class="tabular-nums text-gray-700 dark:text-gray-300">
              {{ formatQuantity(prep.quantity * units, 'g') }}
              <span v-if="prep.yield_g > 0" class="text-gray-500 dark:text-gray-400"> · {{ +((prep.quantity * units) / prep.yield_g).toFixed(2) }} prep batches</span>
            </span>
          </li>
        </ul>
      </div>

      <div>
        <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ prepPerUnit.length ? 'Raw ingredients' : 'Ingredients' }}</h3>
        <p v-if="prepPerUnit.length" class="text-xs text-gray-500 dark:text-gray-400">In-house ingredients are broken down into what they're made from.</p>
        <ul class="mt-1 divide-y divide-gray-200 dark:divide-gray-700">
          <li v-for="raw in rawPerUnit" :key="raw.id" class="flex items-baseline justify-between gap-3 py-2 text-sm">
            <span class="truncate text-gray-900 dark:text-white">{{ raw.name }}</span>
            <span class="shrink-0 tabular-nums text-gray-700 dark:text-gray-300">{{ formatQuantity(roundQuantity(raw.quantity * units, raw.unit_type), raw.unit_type) }}</span>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { inputClass } from './formClasses'
import { formatQuantity } from './formatWeight'

const props = defineProps<{
  rawPerUnit: Array<{ id: number; name: string; unit_type: 'g' | 'unit'; quantity: number }>
  prepPerUnit: Array<{ id: number; name: string; quantity: number; yield_g: number }>
  // One unit's recipe: its ingredient cost and gram weight.
  recipeCost: number
  recipeGrams: number
  costIsEstimate: boolean
  fillG: number | null
  preferredBatchSize: number | null
}>()

// Minimised until asked for.
const open = ref(false)

// Starts from the recipe's usual batch (else a production run's default 20).
const batchSize = ref<number | ''>(props.preferredBatchSize ?? 20)
const batches = ref<number | ''>(1)

const units = computed(() => Math.max(0, Math.floor(Number(batchSize.value) || 0) * Math.floor(Number(batches.value) || 0)))

// The recipe weighs more than a unit is filled with; the extra fills more units.
const filledUnits = computed(() => (props.fillG && props.fillG > 0 && props.recipeGrams > 0
  ? Math.floor((units.value * props.recipeGrams) / props.fillG)
  : null))

// Whole items for packaging; grams to two decimals.
const roundQuantity = (value: number, unitType: 'g' | 'unit') => (unitType === 'unit' ? Math.ceil(value) : value)
</script>
