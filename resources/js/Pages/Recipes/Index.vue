<template>
  <div class="pb-36 md:pt-6 md:pb-6">
    <div>
      <CostingModuleNav />
      <AdminMobileHeader title="Recipes" />

      <div class="md:max-w-5xl md:mx-auto md:px-6 lg:px-8">
      <div class="hidden md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Recipes</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Flavours and their ingredient weights per jar.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="route('admin.costing.recipes.grid')" class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Grid View
          </Link>
          <Link :href="route('admin.costing.production-planner.runs')" class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Production Runs
          </Link>
          <Link :href="route('admin.costing.recipes.costing')" class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Costing
          </Link>
          <Link :href="route('admin.costing.recipes.create')" class="tap-target-touch inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Recipe
          </Link>
        </div>
      </div>

      <!-- Mobile: icon shelf over the headline stats (host dashboard/index
           pattern) in place of the old row of buttons. -->
      <ActionShelf class="md:hidden" overlay="always">
        <ShelfAction icon="plus" label="Add recipe" :href="route('admin.costing.recipes.create')" />
        <ShelfAction icon="chart" label="Recipe costing" :href="route('admin.costing.recipes.costing')" />
        <ShelfAction icon="clipboard" label="Grid view" :href="route('admin.costing.recipes.grid')" />
      </ActionShelf>
      <StatHero
        class="md:hidden -mt-4"
        :headline="{ label: 'Recipes', value: props.recipes.length }"
        :stats="[
          { label: 'Active', value: activeCount },
          { label: 'Below minimum stock', value: belowMinimumCount, tone: belowMinimumCount > 0 ? 'warning' : 'default' },
        ]"
      />
      <hr class="md:hidden border-gray-200 dark:border-gray-700" />

      <!-- No overflow-hidden: it breaks DataTable's sticky toolbar by
           pinning it inside this box instead of the viewport. -->
      <div class="bg-white dark:bg-gray-800 md:shadow-sm md:rounded-lg">
        <BulkActionsBar :count="selectedIds.length" singular="recipe" plural="recipes" @clear="selectedIds = []">
          <button type="button" @click="bulkAction('activate')" class="tap-target-touch px-3 py-1.5 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700">
            Activate
          </button>
          <button type="button" @click="bulkAction('deactivate')" class="tap-target-touch px-3 py-1.5 text-xs font-medium bg-yellow-600 text-white rounded-md hover:bg-yellow-700">
            Deactivate
          </button>
          <button type="button" @click="bulkAction('delete')" class="tap-target-touch px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-md hover:bg-red-700">
            Delete
          </button>
        </BulkActionsBar>
        <DataTable
          :columns="columns"
          :items="recipes"
          selectable
          v-model:selected-ids="selectedIds"
          searchable
          search-placeholder="Search recipes..."
          empty-message="No recipes yet."
          empty-action-label="Add your first recipe"
          :empty-action-href="route('admin.costing.recipes.create')"
          table-id="costing-recipes"
          item-key="id"
          mobile-row-style="line"
          :row-href="(item) => route('admin.costing.recipes.show', item.id)"
        >
          <!-- Single-line mobile row (same as Ingredients): flavour + how
               many jars stock can make, with the below-minimum dot; tap
               opens Show, which has edit and delete. -->
          <template #mobile-card="{ item }">
            <div class="flex items-center gap-3 min-w-0">
              <span class="min-w-0 flex-1 inline-flex items-center gap-1.5 truncate text-sm font-medium text-gray-900 dark:text-white">
                <span class="truncate">{{ item.name }}</span>
                <span v-if="!item.is_active" class="shrink-0 text-xs font-normal text-gray-500 dark:text-gray-400">Inactive</span>
                <span v-if="isBelowThreshold(item)" class="w-1.5 h-1.5 rounded-full bg-amber-500 dark:bg-amber-400 flex-shrink-0"></span>
              </span>
              <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ item.max_producible_units }} jar{{ item.max_producible_units !== 1 ? 's' : '' }}</span>
            </div>
          </template>

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
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ActionShelf from '@/Components/Admin/ActionShelf.vue'
import ShelfAction from '@/Components/Admin/ShelfAction.vue'
import StatHero from '@/Components/Admin/StatHero.vue'
import DataTable, { type Column } from '@/Components/Admin/DataTable.vue'
import BulkActionsBar from '../Shared/BulkActionsBar.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

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

const props = defineProps<Props>()
const { confirmDialog } = useConfirmDialog()

// Only flagged when a threshold is actually set -- matches the same
// "null disables it" convention the old Ingredient::low_stock_threshold used.
const isBelowThreshold = (item: Recipe): boolean =>
  item.min_stock_threshold !== null && item.max_producible_units < item.min_stock_threshold

const activeCount = computed(() => props.recipes.filter((r) => r.is_active).length)
const belowMinimumCount = computed(() => props.recipes.filter(isBelowThreshold).length)

const columns: Column[] = [
  { key: 'name', label: 'Flavour', sortable: true },
  { key: 'ingredients_count', label: 'Ingredients' },
  { key: 'max_producible_units', label: 'Can Produce', hideable: true },
  { key: 'notes', label: 'Notes', hideable: true },
]

// No per-row actions menu: the row opens Show (row-href), which has edit
// and delete; bulk actions (BulkActionsBar) still apply to selections.
const selectedIds = ref<number[]>([])

const bulkAction = async (action: 'delete' | 'activate' | 'deactivate') => {
  if (selectedIds.value.length === 0) return
  const verb = action.charAt(0).toUpperCase() + action.slice(1)
  const count = `${selectedIds.value.length} recipe${selectedIds.value.length === 1 ? '' : 's'}`
  if (!(await confirmDialog({
    title: `${verb} Recipes`,
    message: action === 'delete' ? `Delete ${count}? This also removes them from any production runs.` : `${verb} ${count}?`,
    confirmLabel: verb,
    variant: action === 'delete' ? 'danger' : 'warning',
  }))) return

  router.post(route('admin.costing.recipes.bulk-action'), { action, ids: selectedIds.value }, {
    preserveScroll: true,
    onSuccess: () => { selectedIds.value = [] },
  })
}
</script>
