<template>
  <div class="py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Production Runs</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Past and current production runs, newest first.</p>
        </div>
        <Link :href="route('admin.costing.kitchen-rentals.index')" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
          Rental Schedule
        </Link>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <DataTable
          :columns="columns"
          :items="rows"
          :actions="tableActions"
          searchable
          search-placeholder="Search runs..."
          empty-message="No production runs yet."
          empty-action-label="Plan one from the Rental Schedule"
          :empty-action-href="route('admin.costing.kitchen-rentals.index')"
          table-id="costing-production-runs"
          item-key="id"
          @action="handleAction"
        >
          <template #cell-name="{ item }">
            <span class="text-sm text-gray-900 dark:text-white">{{ item.name ?? '—' }}</span>
          </template>
          <template #cell-total_units="{ item }">
            <span class="text-sm text-gray-900 dark:text-white">{{ item.total_units }}</span>
          </template>
          <template #cell-status="{ item }">
            <span
              v-if="item.completed_at"
              class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300"
            >
              Completed
            </span>
            <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">
              Planned
            </span>
          </template>
        </DataTable>
      </div>
    </div>

    <ProductionPlanModal :production-run-id="openRunId" :auto-complete="autoComplete" @close="openRunId = null; autoComplete = false" @updated="router.reload({ only: ['runs'] })" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column, type Action } from '@/Components/Admin/DataTable.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import ProductionPlanModal from '../Shared/ProductionPlanModal.vue'

defineOptions({ layout: AdminLayout })

interface ProductionRunRow {
  id: number
  name: string | null
  run_date: string
  total_units: number
  completed_at: string | null
}

interface Props {
  runs: ProductionRunRow[]
}

const props = defineProps<Props>()

// A real 'status' field (rather than only a display-time computation) so
// the filterable column can pick it up like any other value.
const rows = computed(() => props.runs.map((run) => ({
  ...run,
  status: run.completed_at ? 'Completed' : 'Planned',
})))

const columns: Column[] = [
  { key: 'run_date', label: 'Run Date', sortable: true },
  { key: 'name', label: 'Name' },
  { key: 'total_units', label: 'Total Units', sortable: true },
  { key: 'status', label: 'Status', filterable: true },
]

const tableActions: Action[] = [
  { name: 'view', icon: 'edit', color: 'indigo', label: 'View / Edit' },
  // Row-level action, not a button inside the modal -- completing is a
  // separate, deliberate step from editing batches, not something that
  // should already be sitting there while a run is still being set up.
  { name: 'complete', icon: 'check', color: 'green', label: 'Complete Run & Deduct Inventory', show: (item) => !item.completed_at },
  { name: 'purchase-order', icon: 'view', color: 'gray', label: 'Purchase Order', href: (item) => route('admin.costing.production-planner.purchase-order', item.id) },
  // Hidden rather than left to 422 -- a completed run's inventory
  // deduction and cost snapshots are historical fact until its completion
  // is explicitly undone (see destroy()'s guard server-side, and the
  // "Undo Completion" action inside the modal).
  { name: 'delete', icon: 'delete', color: 'red', label: 'Delete', show: (item) => !item.completed_at },
]

// The run this modal is open for -- null means closed, same pattern as
// KitchenRentals/Index.vue and Inventory/Adjustments.vue, which open the
// same shared modal. autoComplete tells it to land on the actuals-
// confirmation step instead of the batches editor.
const openRunId = ref<number | null>(null)
const autoComplete = ref(false)

const handleAction = (action: string, item: ProductionRunRow) => {
  if (action === 'view') {
    autoComplete.value = false
    openRunId.value = item.id
  } else if (action === 'complete') {
    autoComplete.value = true
    openRunId.value = item.id
  } else if (action === 'delete') {
    if (confirm(`Delete production run "${item.name ?? item.run_date}"? This cannot be undone.`)) {
      router.delete(route('admin.costing.production-planner.destroy', item.id), { preserveScroll: true })
    }
  }
}
</script>
