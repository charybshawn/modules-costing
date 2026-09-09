<template>
  <div class="py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Recipes</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Flavours and their ingredient weights per jar.</p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
          <Link :href="route('admin.costing.recipes.grid')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Grid View
          </Link>
          <Link :href="route('admin.costing.production-planner.runs')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Production Runs
          </Link>
          <Link :href="route('admin.costing.recipes.costing')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Costing
          </Link>
          <Link :href="route('admin.costing.recipes.create')" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Recipe
          </Link>
        </div>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <BulkActionsBar :count="selectedIds.length" singular="recipe" plural="recipes" @clear="selectedIds = []">
          <button type="button" @click="bulkAction('activate')" class="px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700">
            Activate
          </button>
          <button type="button" @click="bulkAction('deactivate')" class="px-3 py-1.5 text-xs font-medium bg-yellow-600 text-white rounded-md hover:bg-yellow-700">
            Deactivate
          </button>
          <button type="button" @click="bulkAction('delete')" class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-md hover:bg-red-700">
            Delete
          </button>
        </BulkActionsBar>
        <DataTable
          :columns="columns"
          :items="recipes"
          :actions="tableActions"
          selectable
          v-model:selected-ids="selectedIds"
          searchable
          search-placeholder="Search recipes..."
          empty-message="No recipes yet."
          empty-action-label="Add your first recipe"
          :empty-action-href="route('admin.costing.recipes.create')"
          table-id="costing-recipes"
          item-key="id"
          @action="handleAction"
        >
          <template #cell-name="{ item }">
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</span>
            <span
              v-if="!item.is_active"
              class="ml-1.5 inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-500 dark:text-gray-400"
            >
              Inactive
            </span>
          </template>

          <template #cell-ingredients_count="{ item }">
            <span class="text-sm text-gray-900 dark:text-white">{{ item.ingredients_count }} ingredient(s)</span>
          </template>

          <template #cell-max_producible_units="{ item }">
            <span class="inline-flex items-center gap-1.5">
              <span class="text-sm text-gray-900 dark:text-white">{{ item.max_producible_units }} jar{{ item.max_producible_units !== 1 ? 's' : '' }}</span>
              <span
                v-if="isBelowThreshold(item)"
                class="w-1.5 h-1.5 rounded-full bg-amber-500 dark:bg-amber-400 flex-shrink-0"
                :title="`Current ingredient stock can only produce ${item.max_producible_units} jar(s) -- below the minimum of ${item.min_stock_threshold} -- needs reordering`"
              ></span>
            </span>
          </template>
        </DataTable>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column, type Action } from '@/Components/Admin/DataTable.vue'
import BulkActionsBar from '../Shared/BulkActionsBar.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

defineOptions({ layout: AdminLayout })

interface Recipe {
  id: number
  name: string
  notes: string | null
  ingredients_count: number
  min_stock_threshold: number | null
  is_active: boolean
  max_producible_units: number
}

interface Props {
  recipes: Recipe[]
}

defineProps<Props>()

// Only flagged when a threshold is actually set -- matches the same
// "null disables it" convention the old Ingredient::low_stock_threshold used.
const isBelowThreshold = (item: Recipe): boolean =>
  item.min_stock_threshold !== null && item.max_producible_units < item.min_stock_threshold

const columns: Column[] = [
  { key: 'name', label: 'Flavour', sortable: true },
  { key: 'ingredients_count', label: 'Ingredients' },
  { key: 'max_producible_units', label: 'Can Produce', hideable: true },
  { key: 'notes', label: 'Notes', hideable: true },
]

const tableActions: Action[] = [
  { name: 'edit', icon: 'edit', color: 'indigo', label: 'Edit', href: (item) => route('admin.costing.recipes.edit', item.id) },
  { name: 'delete', icon: 'delete', color: 'red', label: 'Delete' },
]

const handleAction = (action: string, item: Recipe) => {
  if (action === 'delete') {
    if (confirm(`Delete "${item.name}"? This also removes it from any production runs.`)) {
      router.delete(route('admin.costing.recipes.destroy', item.id), { preserveScroll: true })
    }
  }
}

const selectedIds = ref<number[]>([])

const bulkAction = (action: 'delete' | 'activate' | 'deactivate') => {
  if (selectedIds.value.length === 0) return
  const verb = action === 'delete' ? 'delete' : action
  if (!confirm(`${verb.charAt(0).toUpperCase() + verb.slice(1)} ${selectedIds.value.length} recipe${selectedIds.value.length === 1 ? '' : 's'}?`)) return

  router.post(route('admin.costing.recipes.bulk-action'), { action, ids: selectedIds.value }, {
    preserveScroll: true,
    onSuccess: () => { selectedIds.value = [] },
  })
}
</script>
