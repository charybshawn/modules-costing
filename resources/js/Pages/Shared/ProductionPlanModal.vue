<template>
  <Modal :show="productionRunId !== null" max-width="2xl" @close="$emit('close')">
    <div v-if="productionRunId !== null" class="p-6 max-h-[85vh] overflow-y-auto">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-3">
            {{ productionRun?.name ?? 'Production Plan' }}
            <!-- Read-only -- the run date is dictated by its rental slot
                 (set once at creation from KitchenRentalController::
                 createRun()), not something to re-edit here. -->
            <span v-if="productionRun" class="text-sm font-normal text-gray-500 dark:text-gray-400">{{ productionRun.run_date }}</span>
            <span v-if="isCompleted" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
              Completed {{ productionRun?.completed_at }}
            </span>
            <span v-if="saving || completing || undoing" class="text-xs font-normal text-gray-400 dark:text-gray-500">Saving...</span>
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            <template v-if="isCompleted">
              This run's inventory has already been deducted. Batch counts can no longer be edited.
            </template>
            <template v-else>
              Enter batches per flavour. The shopping list below updates automatically against current Inventory and Ingredient pricing.
            </template>
          </p>
        </div>
        <button type="button" @click="$emit('close')" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>

      <div v-if="loading" class="mt-6 text-sm text-gray-500 dark:text-gray-400">Loading...</div>

      <template v-else-if="productionRun">
        <FormErrorSummary :errors="errors" class="mt-4" />

        <div class="mt-6 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Run Name</label>
              <input v-model="form.name" type="text" :disabled="isCompleted" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60" placeholder="e.g. Weekly batch" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Batch Size *</label>
              <input v-model.number="form.batch_size" type="number" min="1" required :disabled="isCompleted" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60" />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Units one batch yields, for every flavour in this run.</p>
            </div>
          </div>

          <template v-if="!confirmingComplete">
            <div v-if="!isCompleted" class="border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
              <div v-for="row in form.batches" :key="row.recipe_id" class="flex items-center justify-between px-4 py-2 gap-4">
                <span class="text-sm text-gray-700 dark:text-gray-300 flex-1">{{ recipeName(row.recipe_id) }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 w-24 text-right">{{ rowUnits(row) }} units</span>
                <input v-model.number="row.batches" type="number" min="0" class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              </div>
              <div class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-700/50">
                <span class="text-sm font-semibold text-gray-900 dark:text-white">Total Units</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ totalUnits }}</span>
              </div>
            </div>

            <!-- Completed: planned vs. actual, not an editable batches
                 table -- the plan is fixed history at this point, and what
                 matters now is how actual production compared to it. -->
            <div v-else class="overflow-x-auto -mx-6">
              <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                  <tr>
                    <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Flavour</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Planned</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actual</th>
                    <th class="px-6 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Change</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="row in completedRows" :key="row.recipe_id">
                    <td class="px-6 py-2 text-sm text-gray-900 dark:text-white">{{ row.recipe_name }}</td>
                    <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 text-right">{{ row.planned }}</td>
                    <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white text-right">{{ row.actual }}</td>
                    <td class="px-6 py-2 text-sm font-medium text-right" :class="changeClass(row.change_percent)">{{ formatChange(row.change_percent) }}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-gray-50 dark:bg-gray-700/50">
                    <td class="px-6 py-2 text-sm font-semibold text-gray-900 dark:text-white">Total Units</td>
                    <td class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white text-right">{{ totalPlanned }}</td>
                    <td class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white text-right">{{ displayTotalUnits }}</td>
                    <td class="px-6 py-2 text-sm font-semibold text-right" :class="changeClass(totalChangePercent)">{{ formatChange(totalChangePercent) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="flex justify-end gap-3">
              <button
                v-if="isCompleted"
                type="button"
                @click="undoCompletion"
                :disabled="undoing"
                class="bg-red-50 dark:bg-red-900/20 py-2 px-4 border border-red-300 dark:border-red-700 rounded-md shadow-sm text-sm font-medium text-red-700 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-900/40 disabled:opacity-50"
              >
                <span v-if="undoing">Undoing...</span>
                <span v-else>Undo Completion (Reverse Inventory)</span>
              </button>
              <button v-else type="button" @click="saveChanges" :disabled="saving" class="bg-white dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50">
                <span v-if="saving">Saving...</span>
                <span v-else>Save Changes</span>
              </button>
            </div>
          </template>

          <!-- Actual units produced -- confirmed here, at completion, since
               that's the earliest point it's actually known. Ingredient
               deduction above always uses the plan, regardless of these. -->
          <div v-else class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Confirm actual units produced per flavour -- defaults to the plan. Inventory is always deducted using the planned quantities, not these.
            </p>
            <div class="border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
              <div v-for="row in confirmingComplete" :key="row.recipe_id" class="flex items-center justify-between px-4 py-2 gap-4">
                <span class="text-sm text-gray-700 dark:text-gray-300 flex-1">{{ row.recipe_name }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 w-28 text-right">planned {{ row.planned_units }}</span>
                <input v-model.number="row.actual_units" type="number" min="0" class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              </div>
            </div>
            <div class="flex justify-end gap-3">
              <button type="button" @click="confirmingComplete = null" :disabled="completing" class="bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50">
                Cancel
              </button>
              <button type="button" @click="completeRun" :disabled="completing" class="bg-green-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                <span v-if="completing">Completing...</span>
                <span v-else>Confirm Completion</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Shopping list -- null (not rendered) for a completed run;
             purchasing decisions are moot for something already made. -->
        <div v-if="plan" class="mt-6 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
          <div class="p-4 pb-0 flex items-center justify-between">
            <h3 class="text-base font-medium text-gray-900 dark:text-white">Shopping List</h3>
            <Link
              :href="route('admin.costing.production-planner.purchase-order', productionRun.id)"
              class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
            >
              View Purchase Order &rarr;
            </Link>
          </div>
          <p class="px-4 pt-2 text-sm text-gray-500 dark:text-gray-400">Amber rows need purchasing. Green rows are covered by inventory.</p>

          <div class="overflow-x-auto mt-4">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ingredient</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Required</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">On Hand</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">To Purchase</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Best Source</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Est. Cost</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="row in plan.rows" :key="row.ingredient_id" :class="row.needs_purchase ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-green-50 dark:bg-green-900/10'">
                  <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ row.ingredient_name }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ formatQuantity(row.required, row.unit_type) }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ formatQuantity(row.on_hand, row.unit_type) }}</td>
                  <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-white">{{ formatQuantity(row.to_purchase, row.unit_type) }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ row.best_source ?? '— no price this week' }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">${{ row.est_cost.toFixed(2) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="bg-gray-50 dark:bg-gray-900">
                  <td colspan="5" class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white text-right">Total Estimated Purchase Cost</td>
                  <td class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white">${{ plan.total_estimated_cost.toFixed(2) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </template>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import { formatQuantity } from './formatWeight'

interface Recipe {
  id: number
  name: string
}

interface PlanRow {
  ingredient_id: number
  ingredient_name: string
  unit_type: 'g' | 'unit'
  required: number
  on_hand: number
  to_purchase: number
  best_source: string | null
  est_cost: number
  needs_purchase: boolean
}

interface Plan {
  rows: PlanRow[]
  total_estimated_cost: number
}

interface RunBatch {
  recipe_id: number
  recipe_name: string
  batches: number
  actual_units: number | null
}

interface ProductionRunData {
  id: number
  name: string | null
  batch_size: number
  run_date: string
  notes: string | null
  completed_at: string | null
  total_units: number
  batches: RunBatch[]
}

interface Props {
  productionRunId: number | null
  // Set when this modal was opened via the "Complete Run" row action
  // (Runs.vue / KitchenRentals/Index.vue) rather than "View / Edit" --
  // there's no in-modal path to start completion anymore, so this is the
  // only way in, and it should land straight on the actuals-confirmation
  // step rather than the batches editor.
  autoComplete?: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{ close: []; updated: [] }>()

const loading = ref(false)
const recipes = ref<Recipe[]>([])
const productionRun = ref<ProductionRunData | null>(null)
const plan = ref<Plan | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const completing = ref(false)
const undoing = ref(false)

const isCompleted = computed(() => productionRun.value?.completed_at != null)

interface FormBatch {
  recipe_id: number
  batches: number
}

const form = reactive({
  run_date: '',
  name: '',
  batch_size: 20,
  notes: '',
  batches: [] as FormBatch[],
})

const resetFormFromRun = () => {
  if (!productionRun.value) return
  const existing = new Map(productionRun.value.batches.map((row) => [row.recipe_id, row.batches]))
  form.run_date = productionRun.value.run_date
  form.name = productionRun.value.name ?? ''
  form.batch_size = productionRun.value.batch_size
  form.notes = productionRun.value.notes ?? ''
  form.batches = recipes.value.map((recipe) => ({ recipe_id: recipe.id, batches: existing.get(recipe.id) ?? 0 }))
}

const fetchRun = async (id: number) => {
  loading.value = true
  try {
    const { data } = await axios.get(route('admin.costing.production-planner.show', id))
    recipes.value = data.recipes
    productionRun.value = data.production_run
    plan.value = data.plan
    resetFormFromRun()
    if (props.autoComplete && !isCompleted.value) {
      startCompleteRun()
    }
  } finally {
    loading.value = false
  }
}

interface ConfirmingRow {
  recipe_id: number
  recipe_name: string
  planned_units: number
  actual_units: number
}

// Declared before the watcher below, not after -- watch(..., { immediate:
// true }) runs its callback synchronously during setup(), so referencing a
// const declared later in this same script would throw (temporal dead
// zone), not just read as undefined.
const confirmingComplete = ref<ConfirmingRow[] | null>(null)

// Re-fetches from scratch every time a different run is opened -- this
// component stays mounted across opens (toggled via the productionRunId
// prop, same as StockAdjustModal toggles via its ingredient prop), so
// nothing here can be initialized once at setup the way a full page's
// props could.
watch(
  () => props.productionRunId,
  (id) => {
    confirmingComplete.value = null
    errors.value = {}
    if (id !== null) {
      fetchRun(id)
    } else {
      productionRun.value = null
      plan.value = null
    }
  },
  { immediate: true },
)

const recipeName = (id: number) => recipes.value.find((r) => r.id === id)?.name ?? '—'
const rowUnits = (row: { batches: number }) => (Number(form.batch_size) || 0) * (Number(row.batches) || 0)
const totalUnits = computed(() => form.batches.reduce((sum, row) => sum + rowUnits(row), 0))
const displayTotalUnits = computed(() => (isCompleted.value && productionRun.value ? productionRun.value.total_units : totalUnits.value))

interface CompletedRow {
  recipe_id: number
  recipe_name: string
  planned: number
  actual: number
  change_percent: number
}

const completedRows = computed<CompletedRow[]>(() => {
  const run = productionRun.value
  if (!run) return []

  return run.batches
    .map((row) => {
      const planned = run.batch_size * row.batches
      return { recipe_id: row.recipe_id, recipe_name: row.recipe_name, planned, actual: row.actual_units ?? planned }
    })
    .filter((row) => row.planned > 0)
    .map((row) => ({ ...row, change_percent: ((row.actual - row.planned) / row.planned) * 100 }))
})

const totalPlanned = computed(() => completedRows.value.reduce((sum, row) => sum + row.planned, 0))
const totalChangePercent = computed(() => (totalPlanned.value > 0 ? ((displayTotalUnits.value - totalPlanned.value) / totalPlanned.value) * 100 : 0))

const changeClass = (percent: number): string => {
  if (percent > 0) return 'text-green-600 dark:text-green-400'
  if (percent < 0) return 'text-red-600 dark:text-red-400'
  return 'text-gray-500 dark:text-gray-400'
}

const formatChange = (percent: number): string => (percent === 0 ? '—' : `${percent > 0 ? '+' : ''}${percent.toFixed(1)}%`)

const saveChanges = () => {
  if (!productionRun.value) return
  const runId = productionRun.value.id
  saving.value = true
  errors.value = {}

  router.put(route('admin.costing.production-planner.update', runId), { ...form }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      fetchRun(runId)
      emit('updated')
    },
    onError: (e) => { errors.value = e },
    onFinish: () => { saving.value = false },
  })
}

const startCompleteRun = () => {
  confirmingComplete.value = form.batches
    .filter((row) => rowUnits(row) > 0)
    .map((row) => ({
      recipe_id: row.recipe_id,
      recipe_name: recipeName(row.recipe_id),
      planned_units: rowUnits(row),
      actual_units: rowUnits(row),
    }))
}

const completeRun = () => {
  if (!productionRun.value || !confirmingComplete.value) return

  const runId = productionRun.value.id
  const actuals = confirmingComplete.value.map((row) => ({ recipe_id: row.recipe_id, actual_units: row.actual_units }))
  completing.value = true

  // Save first, complete second -- completing without saving would deduct
  // whatever batch counts were last persisted, which can differ if the
  // displayed form has unsaved edits.
  router.put(route('admin.costing.production-planner.update', runId), { ...form }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      router.post(route('admin.costing.production-planner.complete', runId), { actuals }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          confirmingComplete.value = null
          fetchRun(runId)
          emit('updated')
        },
        onFinish: () => { completing.value = false },
      })
    },
    onError: () => { completing.value = false },
  })
}

const undoCompletion = () => {
  if (!productionRun.value) return
  if (!confirm("Undo this run's completion? This restores the deducted inventory and clears its cost snapshot -- the run goes back to Planned and can be completed again later.")) return

  const runId = productionRun.value.id
  undoing.value = true

  router.post(route('admin.costing.production-planner.uncomplete', runId), {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      fetchRun(runId)
      emit('updated')
    },
    onFinish: () => { undoing.value = false },
  })
}
</script>
