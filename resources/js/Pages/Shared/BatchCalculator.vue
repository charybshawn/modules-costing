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
          <dd class="text-xs text-gray-500 dark:text-gray-400">at {{ sheet.fill_size_g }}g each</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Ingredient cost</dt>
          <dd class="text-lg font-semibold text-gray-900 dark:text-white tabular-nums">${{ (recipeCost * units).toFixed(2) }}</dd>
          <dd v-if="costIsEstimate" class="text-xs text-amber-600 dark:text-amber-400">Estimate</dd>
        </div>
      </dl>

      <div>
        <div class="flex items-center justify-between gap-3">
          <h3 class="text-sm font-medium text-gray-900 dark:text-white">Recipe for this batch</h3>
          <IconButton label="Print recipe" :class="iconActionClass" @click="print">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
          </IconButton>
        </div>
        <!-- What the print button prints: this sheet and nothing else. -->
        <div id="batch-recipe-print" class="mt-3">
          <RecipeSheet :sheet="sheet" :units="units" :title="`${sheet.name}: ${units} units (${batchLabel})`" />
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import IconButton from '@/Components/IconButton.vue'
import RecipeSheet, { type RecipeSheetData } from './RecipeSheet.vue'
import { iconActionClass, inputClass } from './formClasses'

const props = defineProps<{
  // One unit's production sheet (prep, mix, pack), scaled here to the batch.
  sheet: RecipeSheetData
  // One unit's ingredient cost.
  recipeCost: number
  costIsEstimate: boolean
  preferredBatchSize: number | null
}>()

// Minimised until asked for.
const open = ref(false)

// Starts from the recipe's usual batch (else a production run's default 20).
const batchSize = ref<number | ''>(props.preferredBatchSize ?? 20)
const batches = ref<number | ''>(1)

const units = computed(() => Math.max(0, Math.floor(Number(batchSize.value) || 0) * Math.floor(Number(batches.value) || 0)))
const batchLabel = computed(() => `${Number(batches.value) || 0} × ${Number(batchSize.value) || 0}`)

// The recipe weighs more than a unit is filled with; the extra fills more units.
const filledUnits = computed(() => (props.sheet.fill_size_g && props.sheet.fill_size_g > 0 && props.sheet.recipe_grams > 0
  ? Math.floor((units.value * props.sheet.recipe_grams) / props.sheet.fill_size_g)
  : null))

const print = () => window.print()
</script>

<style>
/* The print button prints only the recipe sheet -- not the page, the
   calculator's inputs or the admin layout around it. */
@media print {
  /* Only while this printout is on the page -- Inertia keeps a visited
     page's styles loaded, so an unscoped rule would blank other pages'
     printouts. Drop everything that isn't the printout or one of its
     containers... */
  body:has(#batch-recipe-print) *:not(:has(#batch-recipe-print)):not(#batch-recipe-print):not(#batch-recipe-print *) {
    display: none !important;
  }

  /* ...and flatten those containers so the printout starts at the top
     of the page with no layout padding, cards or backgrounds. */
  body:has(#batch-recipe-print) *:has(#batch-recipe-print) {
    margin: 0 !important;
    padding: 0 !important;
    max-width: none !important;
    border: 0 !important;
    box-shadow: none !important;
    background: none !important;
  }

  #batch-recipe-print,
  #batch-recipe-print * {
    color: #000 !important;
  }

  #batch-recipe-print {
    font-size: 12pt;
    line-height: 1.6;
  }

  @page {
    margin: 12mm;
  }
}
</style>
