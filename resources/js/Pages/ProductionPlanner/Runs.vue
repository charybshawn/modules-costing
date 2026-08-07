<template>
  <div class="py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Production Runs</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Past and current production runs, newest first.</p>
        </div>
        <Link :href="route('admin.costing.production-planner.create')" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
          Plan a New Run
        </Link>
      </div>

      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <DataTable
          :columns="columns"
          :items="rows"
          :actions="tableActions"
          searchable
          search-placeholder="Search runs..."
          empty-message="No production runs yet."
          empty-action-label="Plan your first run"
          :empty-action-href="route('admin.costing.production-planner.index')"
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
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column, type Action } from '@/Components/Admin/DataTable.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

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
  { name: 'view', icon: 'edit', color: 'indigo', label: 'View / Edit', href: (item) => route('admin.costing.production-planner.show', item.id) },
  { name: 'purchase-order', icon: 'view', color: 'gray', label: 'Purchase Order', href: (item) => route('admin.costing.production-planner.purchase-order', item.id) },
  // Hidden rather than left to 422 -- a completed run's inventory
  // deduction and cost snapshots are historical fact (see destroy()'s
  // guard server-side).
  { name: 'delete', icon: 'delete', color: 'red', label: 'Delete', show: (item) => !item.completed_at },
]

const handleAction = (action: string, item: ProductionRunRow) => {
  if (action === 'delete') {
    if (confirm(`Delete production run "${item.name ?? item.run_date}"? This cannot be undone.`)) {
      router.delete(route('admin.costing.production-planner.destroy', item.id), { preserveScroll: true })
    }
  }
}
</script>
