<template>
  <div class="py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Rental Schedule</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Kitchen bookings imported from a FoodCorridor CSV export. Turn any slot into a production plan.
          </p>
        </div>
        <Link :href="route('admin.costing.production-planner.runs')" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
          View All Runs
        </Link>
      </div>

      <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <!-- Import -->
      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Import CSV</h2>
        <form @submit.prevent="submitImport" class="space-y-4">
          <FormErrorSummary :errors="importForm.errors" />
          <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <div class="flex-1">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">FoodCorridor bookings export (.csv)</label>
              <input
                type="file"
                accept=".csv,text/csv"
                @change="handleFileChange"
                class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-900/40 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/70"
              />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Re-importing an updated export safely updates existing slots instead of duplicating them.</p>
            </div>
            <button
              type="submit"
              :disabled="importForm.processing || !importForm.file"
              class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            >
              <span v-if="importForm.processing">Importing...</span>
              <span v-else>Import</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Slots -->
      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="flex items-center justify-end gap-2 px-6 pt-4">
          <label class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
            <input v-model="hidePast" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
            Hide Past Rental Dates
          </label>
        </div>
        <DataTable
          :columns="columns"
          :items="rows"
          :sort-field="sortField"
          :sort-direction="sortDirection"
          :actions="tableActions"
          searchable
          search-placeholder="Search rental slots..."
          empty-message="No rental slots imported yet. Import a FoodCorridor CSV export above to get started."
          table-id="costing-kitchen-rentals"
          item-key="id"
          @action="handleAction"
          @sort="handleSort"
        >
          <template #cell-starts_at="{ item }">
            <div class="text-sm text-gray-900 dark:text-white">{{ slotDate(item) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ slotTimeRange(item) }}</div>
            <span class="mt-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
              {{ slotDuration(item) }}
            </span>
          </template>
          <template #cell-equipment_names="{ item }">
            <div v-if="!item.equipment.length" class="text-xs text-gray-400 dark:text-gray-500">—</div>
            <div v-else class="space-y-0.5">
              <div v-for="eq in item.equipment" :key="eq.name" class="flex items-center justify-between gap-4 text-xs text-gray-600 dark:text-gray-300">
                <span>{{ eq.name }}</span>
                <span v-if="eq.starts_at !== item.starts_at || eq.ends_at !== item.ends_at" class="text-gray-400 dark:text-gray-500 whitespace-nowrap">
                  {{ equipmentTimeRange(eq) }}
                </span>
              </div>
            </div>
          </template>
          <template #cell-status="{ item }">
            <select
              :value="item.status ?? ''"
              @change="updateStatus(item, ($event.target as HTMLSelectElement).value)"
              :class="[statusColor(item.status), 'appearance-none rounded-full pl-2 pr-1.5 py-1 text-sm font-medium border-0 cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-400']"
            >
              <option v-if="!item.status" value="" disabled class="bg-white text-gray-900">—</option>
              <option v-for="opt in statusOptionsFor(item)" :key="opt" :value="opt" class="bg-white text-gray-900">{{ opt }}</option>
            </select>
          </template>
          <template #cell-booking_title="{ item }">
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.booking_title }}</div>
            <div v-if="item.venue_name" class="text-xs text-gray-500 dark:text-gray-400">{{ item.venue_name }}</div>
          </template>
          <template #cell-plan="{ item }">
            <span v-if="item.production_run_id" class="text-sm text-gray-900 dark:text-white">{{ item.production_run_name ?? '—' }}</span>
            <span v-else class="text-xs text-gray-500 dark:text-gray-400">Unplanned</span>
          </template>
        </DataTable>
      </div>
    </div>

    <ProductionPlanModal :production-run-id="openRunId" :auto-complete="autoComplete" @close="openRunId = null; autoComplete = false" @updated="router.reload({ only: ['rentals'] })" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import axios from 'axios'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column, type Action } from '@/Components/Admin/DataTable.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import ProductionPlanModal from '../Shared/ProductionPlanModal.vue'

defineOptions({ layout: AdminLayout })

interface EquipmentBooking {
  name: string
  starts_at: string
  ends_at: string
}

interface KitchenRentalRow {
  id: number
  booking_title: string
  space_name: string
  venue_name: string | null
  status: string | null
  equipment: EquipmentBooking[]
  starts_at: string
  ends_at: string
  production_run_id: number | null
  production_run_name: string | null
  production_run_completed: boolean
}

interface Props {
  rentals: KitchenRentalRow[]
}

const props = defineProps<Props>()

// 'plan' and 'equipment_names' aren't real prop fields -- computed here so
// DataTable's search (which reads item[column.key] directly) can actually
// match against the linked run's name / equipment list instead of always
// missing, or in equipment's case, stringifying to "[object Object]" now
// that each item carries its own time range alongside its name.
const unsortedRows = computed(() => props.rentals.map((rental) => ({
  ...rental,
  plan: rental.production_run_name ?? 'Unplanned',
  equipment_names: rental.equipment.map((e) => e.name).join(', '),
})))

// DataTable only renders the sort-arrow UI and emits @sort on click --
// it doesn't reorder items itself, so that's done here (same pattern as
// Ingredients/Index.vue). starts_at's 'Y-m-d H:i' format sorts correctly
// as a plain string comparison, no date parsing needed.
const sortField = ref('starts_at')
const sortDirection = ref<'asc' | 'desc'>('desc')

const handleSort = (field: string) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
}

// starts_at/ends_at arrive as 'Y-m-d H:i' -- split once here rather than
// repeating the same date on both ends of the range, which is redundant
// for same-day slots (the vast majority) and just clutter otherwise.
const slotDate = (item: KitchenRentalRow) => item.starts_at.split(' ')[0]

const formatTimeRange = (startsAt: string, endsAt: string) => {
  const [startDate, startTime] = startsAt.split(' ')
  const [endDate, endTime] = endsAt.split(' ')
  return endDate === startDate ? `${startTime} – ${endTime}` : `${startTime} – ${endTime} (${endDate})`
}

const slotTimeRange = (item: KitchenRentalRow) => formatTimeRange(item.starts_at, item.ends_at)
const equipmentTimeRange = (eq: EquipmentBooking) => formatTimeRange(eq.starts_at, eq.ends_at)

// Parsed as plain wall-clock components via Date.UTC, not `new Date(string)`
// -- these are business hours with no timezone attached, so going through
// local-timezone parsing would risk an off-by-DST-hour duration.
const parseSlotDateTime = (value: string) => {
  const [datePart, timePart] = value.split(' ')
  const [y, mo, d] = datePart.split('-').map(Number)
  const [h, mi] = timePart.split(':').map(Number)
  return Date.UTC(y, mo - 1, d, h, mi)
}

const hidePast = ref(false)

// "now" built from the same local Y/M/D/H/mi -> Date.UTC composition as
// parseSlotDateTime above, so this comparison stays apples-to-apples
// instead of drifting by the local UTC offset.
const nowAsWallClockUtc = () => {
  const d = new Date()
  return Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(), d.getHours(), d.getMinutes())
}

const isPast = (item: KitchenRentalRow) => parseSlotDateTime(item.starts_at) < nowAsWallClockUtc()

const rows = computed(() => {
  const field = sortField.value as keyof (typeof unsortedRows.value)[number]
  const dir = sortDirection.value === 'asc' ? 1 : -1
  const source = hidePast.value ? unsortedRows.value.filter((row) => !isPast(row)) : unsortedRows.value
  return [...source].sort((a, b) => String(a[field] ?? '').localeCompare(String(b[field] ?? '')) * dir)
})

const slotDuration = (item: KitchenRentalRow) => {
  const minutes = Math.round((parseSlotDateTime(item.ends_at) - parseSlotDateTime(item.starts_at)) / 60000)
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  return mins === 0 ? `${hours}h` : `${hours}h ${mins}m`
}

// Mirrors Cultpantry\Costing\Models\KitchenRental::STATUSES -- FoodCorridor's
// own booking-status vocabulary, kept hardcoded here rather than passed as a
// prop since it's a fixed set, the same way unit_type's ['g', 'unit'] is
// hardcoded on both sides elsewhere in this module.
const STATUS_OPTIONS = ['Approved', 'Submitted', 'Billed', 'Cancelled', 'Declined']

const STATUS_COLORS: Record<string, string> = {
  Approved: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
  Billed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
  Submitted: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  Declined: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
  Cancelled: 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
}

const statusColor = (status: string | null) => STATUS_COLORS[status ?? ''] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'

// Guards against a status value that predates this fixed list (or a future
// FoodCorridor export using something new) silently disappearing from the
// select -- it stays selectable even though it isn't one of the "normal"
// options underneath it.
const statusOptionsFor = (item: KitchenRentalRow) =>
  item.status && !STATUS_OPTIONS.includes(item.status) ? [item.status, ...STATUS_OPTIONS] : STATUS_OPTIONS

const updateStatus = (item: KitchenRentalRow, status: string) => {
  router.post(
    route('admin.costing.kitchen-rentals.update-status', item.id),
    { status },
    {
      preserveScroll: true,
      // A legacy status from an older CSV format (injected into this row's
      // options by statusOptionsFor above so it's still visible/selectable)
      // isn't a member of KitchenRental::STATUSES, so re-submitting it 422s
      // server-side -- without this, that failure was silently swallowed.
      onError: (errors) => alert(errors.status ?? 'Could not update status.'),
    },
  )
}

const columns: Column[] = [
  { key: 'booking_title', label: 'Booking', sortable: true, filterable: true },
  { key: 'starts_at', label: 'Booked Time', sortable: true },
  { key: 'space_name', label: 'Space', sortable: true, filterable: true },
  { key: 'equipment_names', label: 'Equipment' },
  { key: 'status', label: 'Status', sortable: true, filterable: true },
  { key: 'plan', label: 'Production Plan', sortable: true },
]

// A plain useForm (not usePersistedForm) -- a File object can't be
// serialized to localStorage, and a draft file selection wouldn't survive
// a reload anyway.
const importForm = useForm<{ file: File | null }>({ file: null })

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  importForm.file = target.files?.[0] ?? null
}

const submitImport = () => {
  importForm.post(route('admin.costing.kitchen-rentals.import'), {
    forceFormData: true,
    onSuccess: () => { importForm.reset() },
  })
}

const tableActions: Action[] = [
  {
    name: 'create-plan',
    icon: 'link',
    color: 'indigo',
    label: 'Create Plan',
    show: (item) => !item.production_run_id,
  },
  {
    name: 'view-plan',
    icon: 'view',
    color: 'gray',
    label: 'View Plan',
    show: (item) => !!item.production_run_id,
  },
  // Row-level action, not a button inside the modal -- completing is a
  // separate, deliberate step from editing batches, not something that
  // should already be sitting there while a run is still being set up.
  {
    name: 'complete-plan',
    icon: 'check',
    color: 'green',
    label: 'Complete Run & Deduct Inventory',
    show: (item) => !!item.production_run_id && !item.production_run_completed,
  },
  {
    name: 'unlink-plan',
    icon: 'cancel',
    color: 'red',
    label: 'Unlink Run',
    show: (item) => !!item.production_run_id,
  },
]

// The run this modal is open for -- null means closed. Production plans
// belong to their rental now, so both creating and viewing one happens
// right here rather than navigating to a separate page. autoComplete tells
// it to land on the actuals-confirmation step instead of the batches
// editor.
const openRunId = ref<number | null>(null)
const autoComplete = ref(false)

const handleAction = async (action: string, item: KitchenRentalRow) => {
  if (action === 'create-plan') {
    // axios, not router.post -- this needs the new run's id back directly
    // to open the modal, not an Inertia page redirect.
    const { data } = await axios.post(route('admin.costing.kitchen-rentals.create-run', item.id))
    router.reload({ only: ['rentals'] })
    autoComplete.value = false
    openRunId.value = data.production_run_id
  } else if (action === 'view-plan') {
    autoComplete.value = false
    openRunId.value = item.production_run_id
  } else if (action === 'complete-plan') {
    autoComplete.value = true
    openRunId.value = item.production_run_id
  } else if (action === 'unlink-plan') {
    if (confirm(`Unlink "${item.booking_title}" from its production run? The run itself is not affected.`)) {
      router.post(route('admin.costing.kitchen-rentals.detach-run', item.id), {}, { preserveScroll: true })
    }
  }
}
</script>
