<template>
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Inventory Adjustment History</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Every change to stock on hand -- recounts, manual adjustments, and automatic production-run deductions.
          </p>
        </div>
        <Link :href="route('admin.costing.inventory.index')" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
          &larr; Back to Inventory
        </Link>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <DataTable
          :columns="columns"
          :items="rows"
          :sort-field="sortField"
          :sort-direction="sortDirection"
          searchable
          search-placeholder="Search adjustments..."
          empty-message="No inventory adjustments logged yet."
          table-id="costing-inventory-adjustments"
          item-key="id"
          @sort="handleSort"
        >
          <template #cell-reason="{ item }">
            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', reasonColor(item.reason)]">
              {{ reasonLabel(item.reason) }}
            </span>
          </template>

          <template #cell-delta="{ item }">
            <span :class="['text-sm font-medium', item.delta > 0 ? 'text-green-700 dark:text-green-400' : item.delta < 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-500 dark:text-gray-400']">
              {{ item.delta > 0 ? '+' : '' }}{{ formatQuantity(item.delta, item.unit_type) }}
            </span>
          </template>

          <template #cell-change="{ item }">
            <span class="text-sm text-gray-700 dark:text-gray-300">
              {{ formatQuantity(item.on_hand_before, item.unit_type) }} &rarr; {{ formatQuantity(item.on_hand_after, item.unit_type) }}
            </span>
          </template>

          <template #cell-notes="{ item }">
            <span class="text-sm text-gray-700 dark:text-gray-300">
              {{ item.notes ?? '—' }}
              <button
                v-if="item.production_run_name"
                type="button"
                @click="openRunId = item.production_run_id"
                class="block text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
              >
                Run: {{ item.production_run_name }}
              </button>
            </span>
          </template>

          <template #cell-who="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.user_name ?? 'System' }}</span>
          </template>
        </DataTable>
      </div>
    </div>

    <ProductionPlanModal :production-run-id="openRunId" @close="openRunId = null" @updated="router.reload({ only: ['adjustments'] })" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column } from '@/Components/Admin/DataTable.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import ProductionPlanModal from '../Shared/ProductionPlanModal.vue'
import { formatQuantity } from '../Shared/formatWeight'

defineOptions({ layout: AdminLayout })

interface AdjustmentRow {
  id: number
  ingredient_name: string
  unit_type: 'g' | 'unit'
  source: string
  reason: 'recount' | 'received' | 'correction' | 'production_run' | 'production_run_reversal'
  delta: number
  on_hand_before: number
  on_hand_after: number
  notes: string | null
  user_name: string | null
  production_run_id: number | null
  production_run_name: string | null
  created_at: string
}

interface Props {
  adjustments: AdjustmentRow[]
}

const props = defineProps<Props>()

// DataTable only renders the sort-arrow UI and emits @sort on click -- it
// doesn't reorder items itself (same gap found and fixed on the Rental
// Schedule page), so that's done here.
const sortField = ref('created_at')
const sortDirection = ref<'asc' | 'desc'>('desc')

const handleSort = (field: string) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
}

const rows = computed(() => {
  const field = sortField.value as keyof AdjustmentRow
  const dir = sortDirection.value === 'asc' ? 1 : -1
  return [...props.adjustments].sort((a, b) => String(a[field] ?? '').localeCompare(String(b[field] ?? '')) * dir)
})

const REASON_LABELS: Record<AdjustmentRow['reason'], string> = {
  recount: 'Recount',
  received: 'Received',
  correction: 'Correction',
  production_run: 'Production Run',
  production_run_reversal: 'Run Undone',
}

const REASON_COLORS: Record<AdjustmentRow['reason'], string> = {
  recount: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
  received: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
  correction: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  production_run: 'bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200',
  production_run_reversal: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300',
}

const reasonLabel = (reason: AdjustmentRow['reason']) => REASON_LABELS[reason] ?? reason
const reasonColor = (reason: AdjustmentRow['reason']) => REASON_COLORS[reason] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'

// Modal state for the "Run: X" link above -- opens the same shared
// production-plan editor used from Rental Schedule and All Runs.
const openRunId = ref<number | null>(null)

const columns: Column[] = [
  { key: 'created_at', label: 'When', sortable: true },
  { key: 'ingredient_name', label: 'Ingredient', sortable: true, filterable: true },
  { key: 'source', label: 'Source', filterable: true },
  { key: 'reason', label: 'Reason', filterable: true },
  { key: 'delta', label: 'Change' },
  { key: 'change', label: 'Before → After' },
  { key: 'notes', label: 'Notes' },
  { key: 'who', label: 'By' },
]
</script>
