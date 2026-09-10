<template>
  <Modal :show="productionRunId !== null" max-width="2xl" @close="$emit('close')">
    <div v-if="productionRunId !== null" class="p-4 sm:p-6">
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-3">
            {{ productionRun?.name ?? 'Production Plan' }}
            <span v-if="productionRun" :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', typeBadgeClass(productionRun.type)]">
              {{ typeLabel(productionRun.type) }}
            </span>
            <span v-if="isCompleted" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
              Completed {{ productionRun?.completed_at }}
            </span>
            <span v-if="saving || completing || undoing" class="text-xs font-normal text-gray-400 dark:text-gray-500">Saving...</span>
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            <template v-if="isCompleted">
              This run's inventory has already been deducted. Batch counts can no longer be edited.
            </template>
            <template v-else-if="showBatches">
              Enter batches per flavour. The shopping list below updates automatically against current Inventory and Ingredient pricing.
            </template>
            <template v-else>
              No batch counts for this session type -- just a date and notes.
            </template>
          </p>
        </div>
        <IconButton type="button" @click="$emit('close')" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300"
            label="Close"
          >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </IconButton>
      </div>

      <div v-if="loading" class="mt-6 text-sm text-gray-500 dark:text-gray-400">Loading...</div>

      <template v-else-if="productionRun">
        <FormErrorSummary :errors="errors" class="mt-4" />

        <div class="mt-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date *</label>
            <input v-model="form.run_date" type="date" required :disabled="isCompleted" class="mt-1 block w-full sm:w-64 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60" />
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-if="showBatchSize">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Batch Size *</label>
              <input v-model.number="form.batch_size" type="number" inputmode="numeric" min="1" required :disabled="isCompleted" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60" />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Units one batch yields, for every flavour in this run.</p>
            </div>
            <div :class="showBatchSize ? '' : 'md:col-span-2'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
              <textarea v-model="form.notes" rows="2" :disabled="isCompleted" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60" :placeholder="notesPlaceholder" />
            </div>
          </div>

          <!-- Rental attachment -- optional and secondary, never the same
               visual weight as the run's own name/date, but still its own
               bordered section rather than floating text so it reads as a
               deliberate part of the form. Only meaningful for a real
               production run; prep/R&D sessions don't book kitchen space
               the same way. -->
          <div v-if="productionRun.type === 'production'" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/30 px-4 py-3">
            <div v-if="productionRun.rental" class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <svg class="w-4 h-4 flex-shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                <span>Booked: <span class="font-medium text-gray-900 dark:text-white">{{ productionRun.rental.booking_title }}</span> ({{ productionRun.rental.starts_at }})</span>
              </div>
              <button v-if="!isCompleted" type="button" @click="detachRental" :disabled="rentalBusy" class="tap-target-touch flex-shrink-0 py-1 px-3 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-600 disabled:opacity-50">
                Detach
              </button>
            </div>
            <template v-else-if="!isCompleted">
              <button v-if="!showRentalPicker" type="button" @click="openRentalPicker" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                No rental slot attached -- Attach one
              </button>
              <div v-else>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Rental Slot</label>
                <div class="flex flex-wrap items-center gap-2">
                  <select v-model="selectedRentalId" class="flex-1 min-w-[12rem] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm">
                    <option :value="null" disabled>Select a rental slot...</option>
                    <option v-for="rental in unattachedRentals" :key="rental.id" :value="rental.id">{{ rental.booking_title }} ({{ rental.starts_at }})</option>
                  </select>
                  <button type="button" @click="attachRental" :disabled="!selectedRentalId || rentalBusy" class="tap-target-touch py-1.5 px-4 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                    Attach
                  </button>
                  <button type="button" @click="showRentalPicker = false" class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    Cancel
                  </button>
                </div>
                <p v-if="!loadingRentals && unattachedRentals.length === 0" class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">No unbooked rental slots available.</p>
              </div>
            </template>
          </div>

          <template v-if="!confirmingComplete">
            <div v-if="!isCompleted && showBatches" class="border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
              <div v-for="row in form.batches" :key="row.recipe_id" class="flex flex-wrap items-center justify-between px-4 py-2 gap-x-3 gap-y-2">
                <span class="text-sm text-gray-700 dark:text-gray-300 flex-1 min-w-[8rem]">{{ recipeName(row.recipe_id) }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 text-right">{{ rowUnits(row) }} units</span>
                <input v-model.number="row.batches" type="number" inputmode="numeric" min="0" class="w-24 sm:w-28 tap-target rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              </div>
              <div class="flex items-center justify-between px-4 py-2 bg-gray-50 dark:bg-gray-700/50">
                <span class="text-sm font-semibold text-gray-900 dark:text-white">Total Units</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ totalUnits }}</span>
              </div>
            </div>

            <!-- Completed: planned vs. actual, not an editable batches
                 table -- the plan is fixed history at this point, and what
                 matters now is how actual production compared to it. Not
                 shown at all for a prep/R&D run that's still in progress
                 (showBatches false) -- there's no batches concept to
                 summarize either way. -->
            <div v-else-if="isCompleted" class="overflow-x-auto -mx-6">
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

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
              <button
                v-if="isCompleted"
                type="button"
                @click="undoCompletion"
                :disabled="undoing"
                class="tap-target-touch bg-red-50 dark:bg-red-900/20 py-2 px-4 border border-red-300 dark:border-red-700 rounded-md shadow-sm text-sm font-medium text-red-700 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-900/40 disabled:opacity-50"
              >
                <span v-if="undoing">Undoing...</span>
                <span v-else>Undo Completion (Reverse Inventory)</span>
              </button>
              <button v-else type="button" @click="saveChanges" :disabled="saving" class="tap-target-touch bg-white dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50">
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
              <div v-for="row in confirmingComplete" :key="row.recipe_id" class="flex flex-wrap items-center justify-between px-4 py-2 gap-x-3 gap-y-2">
                <span class="text-sm text-gray-700 dark:text-gray-300 flex-1 min-w-[8rem]">{{ row.recipe_name }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400 text-right">planned {{ row.planned_units }}</span>
                <input v-model.number="row.actual_units" type="number" inputmode="numeric" min="0" class="w-24 sm:w-28 tap-target rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              </div>
            </div>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
              <button type="button" @click="confirmingComplete = null" :disabled="completing" class="tap-target-touch bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50">
                Cancel
              </button>
              <button type="button" @click="completeRun" :disabled="completing" class="tap-target-touch bg-green-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                <span v-if="completing">Completing...</span>
                <span v-else>Confirm Completion</span>
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import IconButton from '@/Components/IconButton.vue'

interface Recipe {
  id: number
  name: string
}

interface RunBatch {
  recipe_id: number
  recipe_name: string
  batches: number
  actual_units: number | null
}

interface RentalRef {
  id: number
  booking_title: string
  starts_at: string
}

interface ProductionRunData {
  id: number
  name: string | null
  type: string
  batch_size: number
  run_date: string
  notes: string | null
  completed_at: string | null
  total_units: number
  batches: RunBatch[]
  rental: RentalRef | null
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
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const completing = ref(false)
const undoing = ref(false)

const isCompleted = computed(() => productionRun.value?.completed_at != null)

// 'prep' has no batches concept at all; 'development' keeps the recipe list
// (tagged with batches: 0 to mean "worked on this") but not the batch-size
// field, since batch_size only ever multiplies against real batch counts.
const showBatches = computed(() => productionRun.value?.type !== 'prep')
const showBatchSize = computed(() => productionRun.value?.type === 'production')

const runTypes = [
  { value: 'production', label: 'Production' },
  { value: 'prep', label: 'Ingredient Prep' },
  { value: 'development', label: 'R&D' },
]
const typeLabel = (type: string) => runTypes.find((t) => t.value === type)?.label ?? type
const typeBadgeClass = (type: string): string => {
  if (type === 'prep') return 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
  if (type === 'development') return 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'
  return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'
}
const notesPlaceholder = computed(() => {
  if (productionRun.value?.type === 'prep') return 'What was prepped, e.g. caramelized 10lbs of onions'
  if (productionRun.value?.type === 'development') return 'What was explored, tasting notes, next steps'
  return ''
})

const unattachedRentals = ref<RentalRef[]>([])
const loadingRentals = ref(false)
const showRentalPicker = ref(false)
const selectedRentalId = ref<number | null>(null)
const rentalBusy = ref(false)

const openRentalPicker = async () => {
  showRentalPicker.value = true
  loadingRentals.value = true
  try {
    const { data } = await axios.get(route('admin.costing.production-planner.unattached-rentals'))
    unattachedRentals.value = data.rentals
  } finally {
    loadingRentals.value = false
  }
}

const attachRental = () => {
  if (!productionRun.value || !selectedRentalId.value) return
  const runId = productionRun.value.id
  rentalBusy.value = true
  router.post(route('admin.costing.production-planner.attach-rental', runId), { kitchen_rental_id: selectedRentalId.value }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      showRentalPicker.value = false
      selectedRentalId.value = null
      fetchRun(runId)
      emit('updated')
    },
    onFinish: () => { rentalBusy.value = false },
  })
}

const detachRental = () => {
  if (!productionRun.value) return
  const runId = productionRun.value.id
  rentalBusy.value = true
  router.post(route('admin.costing.production-planner.detach-rental', runId), {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      fetchRun(runId)
      emit('updated')
    },
    onFinish: () => { rentalBusy.value = false },
  })
}

interface FormBatch {
  recipe_id: number
  batches: number
}

// 'name' is intentionally not part of this form -- it's set once at
// creation from the batch code and has no editable field here (see
// ProductionPlannerController::update(), which never writes it), so it
// can't be inadvertently changed. It still displays read-only in the
// header above.
const form = reactive({
  run_date: '',
  batch_size: 20,
  notes: '',
  batches: [] as FormBatch[],
})

const resetFormFromRun = () => {
  if (!productionRun.value) return
  const existing = new Map(productionRun.value.batches.map((row) => [row.recipe_id, row.batches]))
  form.run_date = productionRun.value.run_date
  form.batch_size = productionRun.value.batch_size
  form.notes = productionRun.value.notes ?? ''
  // A prep run has no batches concept at all -- skip populating pivot rows
  // for every active recipe just because the picker list exists.
  form.batches = showBatches.value
    ? recipes.value.map((recipe) => ({ recipe_id: recipe.id, batches: existing.get(recipe.id) ?? 0 }))
    : []
}

const fetchRun = async (id: number) => {
  loading.value = true
  try {
    const { data } = await axios.get(route('admin.costing.production-planner.show', id))
    recipes.value = data.recipes
    productionRun.value = data.production_run
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
    showRentalPicker.value = false
    selectedRentalId.value = null
    if (id !== null) {
      fetchRun(id)
    } else {
      productionRun.value = null
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
