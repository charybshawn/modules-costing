<template>
  <div class="py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white flex items-center gap-3">
            Production Planner
            <span v-if="productionRun?.completed_at" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
              Completed {{ productionRun.completed_at }}
            </span>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" />
          </h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            <template v-if="productionRun?.completed_at">
              This run's inventory has already been deducted. Batch counts can no longer be edited.
            </template>
            <template v-else>
              Enter batches per flavour. The shopping list below updates automatically against current Inventory and Ingredient pricing.
            </template>
          </p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
          <Link :href="route('admin.costing.production-planner.runs')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            View All Runs
          </Link>
          <Link :href="route('admin.costing.recipes.index')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Recipes
          </Link>
        </div>
      </div>

      <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <!-- 1. Production Order -->
      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">1. Production Order</h2>
        <form @submit.prevent="submit" class="space-y-4">
          <FormErrorSummary :errors="form.errors" />

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Run Date *</label>
              <input v-model="form.run_date" type="date" required :disabled="isCompleted" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60" />
            </div>
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

            <div v-if="!isCompleted" class="flex justify-end gap-3">
              <button
                v-if="productionRun"
                type="button"
                @click="startCompleteRun"
                class="bg-green-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-green-700"
              >
                Complete Run &amp; Deduct Inventory
              </button>
              <button v-if="!productionRun" type="submit" :disabled="form.processing" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                <span v-if="form.processing">Saving...</span>
                <span v-else>Create Production Run</span>
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
        </form>
      </div>

      <!-- 2. Shopping list -- null (not rendered) for a completed run;
           purchasing decisions are moot for something already made. -->
      <div v-if="plan" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="p-6 pb-0 flex items-center justify-between">
          <h2 class="text-lg font-medium text-gray-900 dark:text-white">2. Shopping List</h2>
          <Link
            v-if="productionRun"
            :href="route('admin.costing.production-planner.purchase-order', productionRun.id)"
            class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
          >
            View Purchase Order &rarr;
          </Link>
        </div>
        <p class="px-6 pt-2 text-sm text-gray-500 dark:text-gray-400">Amber rows need purchasing. Green rows are covered by inventory.</p>

        <div class="overflow-x-auto mt-4">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ingredient</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Required</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">On Hand</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">To Purchase</th>
                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Units to Buy</th>
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
                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ row.units_to_buy || '—' }}</td>
                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">{{ row.best_source ?? '— no price this week' }}</td>
                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">${{ row.est_cost.toFixed(2) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-gray-50 dark:bg-gray-900">
                <td colspan="6" class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white text-right">Total Estimated Purchase Cost</td>
                <td class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-white">${{ plan.total_estimated_cost.toFixed(2) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { usePersistedForm } from '@/composables/usePersistedForm'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import SaveIndicator from '@/Components/Admin/SaveIndicator.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import { formatQuantity } from '../Shared/formatWeight'

defineOptions({ layout: AdminLayout })

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
  units_to_buy: number
  purchase_qty: number
  purchase_unit: string | null
  best_source: string | null
  effective_price: number | null
  est_cost: number
  prices_as_of: string | null
  needs_purchase: boolean
}

interface Plan {
  rows: PlanRow[]
  purchase_rows: PlanRow[]
  total_units: number
  total_estimated_cost: number
}

interface ProductionRun {
  id: number
  name: string | null
  batch_size: number
  run_date: string
  notes: string | null
  completed_at: string | null
  total_units: number
  batches: Array<{ recipe_id: number; recipe_name: string; batches: number; actual_units: number | null }>
}

interface Props {
  recipes: Recipe[]
  production_run: ProductionRun | null
  plan: Plan | null
}

const props = defineProps<Props>()

const productionRun = computed(() => props.production_run)
const isCompleted = computed(() => productionRun.value?.completed_at != null)
const completing = ref(false)

interface FormData {
  run_date: string
  name: string
  batch_size: number
  notes: string
  batches: Array<{ recipe_id: number; batches: number }>
}

const existingBatches = new Map((props.production_run?.batches ?? []).map((row) => [row.recipe_id, row.batches]))

const initialData: FormData = {
  run_date: props.production_run?.run_date ?? new Date().toISOString().slice(0, 10),
  name: props.production_run?.name ?? '',
  batch_size: props.production_run?.batch_size ?? 20,
  notes: props.production_run?.notes ?? '',
  batches: props.recipes.map((recipe) => ({ recipe_id: recipe.id, batches: existingBatches.get(recipe.id) ?? 0 })),
}

// Scoped per-run (not a fixed key) -- otherwise navigating between
// different runs' pages (see the Runs list) could restore one run's
// unsaved localStorage draft into a different run's form, since
// usePersistedForm only guards against restoring an unmodified match,
// not against cross-entity collisions on a shared key.
// Autosave only applies when editing an existing, not-yet-completed run --
// a brand-new run (production_run null) would need to be created on first
// autosave and switch targets, and a completed run's inputs are already
// disabled (its batch counts are historical fact, see the backend guard).
const form = usePersistedForm<FormData>(initialData, {
  key: props.production_run ? `costing-production-planner-${props.production_run.id}` : 'costing-production-planner-new',
  initialData,
  autosave: props.production_run && !isCompleted.value
    ? {
        url: route('admin.costing.production-planner.update', props.production_run.id),
        requiredFields: ['run_date', 'batch_size'],
        // A background save landing after completed_at is set would 422 --
        // completeRun() below does its own explicit save-then-complete.
        enabled: () => !completing.value,
      }
    : undefined,
})

const recipeName = (id: number) => props.recipes.find((r) => r.id === id)?.name ?? '—'

const rowUnits = (row: { batches: number }) => (Number(form.batch_size) || 0) * (Number(row.batches) || 0)

const totalUnits = computed(() => form.batches.reduce((sum, row) => sum + rowUnits(row), 0))

const displayTotalUnits = computed(() => (isCompleted.value && props.production_run ? props.production_run.total_units : totalUnits.value))

interface CompletedRow {
  recipe_id: number
  recipe_name: string
  planned: number
  actual: number
  change_percent: number
}

// Planned vs. actual per flavour, completed-run only -- recipes with no
// planned batches are excluded (same reasoning as startCompleteRun's
// filter below: every recipe in the system gets a pivot row, most at 0).
const completedRows = computed<CompletedRow[]>(() => {
  const run = props.production_run
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

// Green/red reads as "produced more/less than planned," which for a food
// business is a real quality signal (yield vs. shrinkage), not just a
// neutral delta -- not the usual "up is bad for costs" dashboard framing.
const changeClass = (percent: number): string => {
  if (percent > 0) return 'text-green-600 dark:text-green-400'
  if (percent < 0) return 'text-red-600 dark:text-red-400'
  return 'text-gray-500 dark:text-gray-400'
}

const formatChange = (percent: number): string => (percent === 0 ? '—' : `${percent > 0 ? '+' : ''}${percent.toFixed(1)}%`)

const submit = () => {
  // Only reachable via the submit button below, which is gated to create
  // mode (v-if="!productionRun") -- once a run exists, autosave owns
  // persistence and no button triggers this, so there's no update branch.
  if (props.production_run) return
  form.post(route('admin.costing.production-planner.store'))
}

interface ConfirmingRow {
  recipe_id: number
  recipe_name: string
  planned_units: number
  actual_units: number
}

const confirmingComplete = ref<ConfirmingRow[] | null>(null)

// Only flavours actually in this run (planned units > 0) -- every recipe
// in the system gets a row in form.batches (most at 0), which would
// otherwise flood this step with irrelevant zero-unit inputs.
const startCompleteRun = () => {
  if (!props.production_run) return
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
  if (!props.production_run || !confirmingComplete.value) return

  const runId = props.production_run.id
  const actuals = confirmingComplete.value.map((row) => ({ recipe_id: row.recipe_id, actual_units: row.actual_units }))
  completing.value = true
  form.cancelAutosave()

  // Save first, complete second -- completing without saving would deduct
  // whatever batch counts were last persisted, which can differ if the
  // user edited within the autosave debounce window. The save also clears
  // the localStorage draft (wrapped form.put), so nothing stale gets
  // restored over the completed run afterwards.
  form.put(`${route('admin.costing.production-planner.update', runId)}?stay=1`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      router.post(route('admin.costing.production-planner.complete', runId), { actuals }, {
        onSuccess: () => { confirmingComplete.value = null },
        onFinish: () => { completing.value = false },
      })
    },
    onError: () => { completing.value = false },
  })
}
</script>
