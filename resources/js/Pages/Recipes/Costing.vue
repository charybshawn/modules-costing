<template>
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Recipe Costing</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Food Cost % per flavour -- ingredient cost, an optional contingency buffer, prorated by actual fill size, against your sell price.
          </p>
        </div>
        <div class="mt-4 md:mt-0 flex gap-2">
          <Link :href="route('admin.costing.recipes.cost-history')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Cost History
          </Link>
          <Link :href="route('admin.costing.recipes.index')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Recipes
          </Link>
        </div>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <DataTable
          :columns="columns"
          :items="recipes"
          :actions="tableActions"
          searchable
          search-placeholder="Search recipes..."
          empty-message="No recipes yet."
          table-id="costing-recipe-costing"
          item-key="id"
          @action="handleAction"
        >
          <template #cell-name="{ item }">
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</div>
            <div v-if="item.any_stale || item.any_missing" class="mt-0.5 inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-300">
              estimate -- {{ item.any_missing ? 'missing price(s)' : 'stale price(s)' }}
            </div>
          </template>

          <template #cell-yield_grams="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.yield_grams }} g</span>
          </template>

          <template #cell-raw_cost="{ item }">
            <span class="text-sm text-gray-900 dark:text-white">${{ item.raw_cost.toFixed(2) }}</span>
          </template>

          <template #cell-cost_buffer_percent="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.cost_buffer_percent !== null ? `${item.cost_buffer_percent}%` : '—' }}</span>
          </template>

          <template #cell-buffered_cost="{ item }">
            <span class="text-sm text-gray-900 dark:text-white">${{ item.buffered_cost.toFixed(2) }}</span>
          </template>

          <template #cell-fill_size_g="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.fill_size_g !== null ? `${item.fill_size_g} g` : 'theoretical' }}</span>
          </template>

          <template #cell-sell_price="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.sell_price !== null ? `$${item.sell_price.toFixed(2)}` : '—' }}</span>
          </template>

          <template #cell-food_cost_percent="{ item }">
            <span v-if="item.food_cost_percent !== null" class="text-sm font-medium" :class="foodCostClass(item.food_cost_percent)">
              {{ item.food_cost_percent.toFixed(2) }}%
            </span>
            <span v-else class="text-sm text-gray-400 italic">no sell price</span>
          </template>
        </DataTable>
      </div>

      <!-- Edit Costing modal -->
      <Modal :show="editingRecipe !== null" max-width="md" @close="closeEditModal">
        <form v-if="editingRecipe" @submit.prevent="submitEdit" class="p-6">
          <h2 class="text-lg font-medium text-gray-900">Edit Costing -- {{ editingRecipe.name }}</h2>

          <FormErrorSummary :errors="editForm.errors" class="mt-4" />

          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Sell Price ($)</label>
            <input v-model.number="editForm.sell_price" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
          </div>

          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Fill Size (g)</label>
            <input v-model.number="editForm.fill_size_g" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            <p class="mt-1 text-xs text-gray-500">Actual measured fill weight per jar, from production. Leave blank to use the theoretical ingredient-weight total instead.</p>
          </div>

          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Cost Buffer (%)</label>
            <input v-model.number="editForm.cost_buffer_percent" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            <p class="mt-1 text-xs text-gray-500">Contingency added on top of ingredient cost, e.g. for price drift or over-portioning. Leave blank for none.</p>
          </div>

          <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200">
            <button type="button" @click="closeEditModal" class="bg-gray-200 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
              Cancel
            </button>
            <button type="submit" :disabled="editForm.processing" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
              <span v-if="editForm.processing">Saving...</span>
              <span v-else>Save</span>
            </button>
          </div>
        </form>
      </Modal>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column, type Action } from '@/Components/Admin/DataTable.vue'
import Modal from '@/Components/Modal.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

defineOptions({ layout: AdminLayout })

interface RecipeCostRow {
  id: number
  name: string
  sell_price: number | null
  fill_size_g: number | null
  cost_buffer_percent: number | null
  raw_cost: number
  yield_grams: number
  buffered_cost: number
  actual_cost_per_jar: number
  food_cost_percent: number | null
  any_stale: boolean
  any_missing: boolean
}

interface Props {
  recipes: RecipeCostRow[]
}

defineProps<Props>()

const columns: Column[] = [
  { key: 'name', label: 'Flavour', sortable: true },
  { key: 'yield_grams', label: 'Yield', hideable: true },
  { key: 'raw_cost', label: 'Raw Cost', sortable: true, hideable: true },
  { key: 'cost_buffer_percent', label: 'Buffer %', hideable: true },
  { key: 'buffered_cost', label: 'Buffered Cost', hideable: true },
  { key: 'fill_size_g', label: 'Fill Size', hideable: true },
  { key: 'sell_price', label: 'Sell Price', hideable: true },
  { key: 'food_cost_percent', label: 'Food Cost %', sortable: true },
]

const tableActions: Action[] = [
  { name: 'edit-costing', icon: 'edit', color: 'indigo', label: 'Edit Costing' },
]

// Above ~35% food cost is a common rule-of-thumb caution line for
// packaged retail goods; below is comfortable, at/above draws the eye.
const foodCostClass = (percent: number): string => {
  if (percent >= 35) return 'text-red-600 dark:text-red-400'
  if (percent >= 28) return 'text-amber-600 dark:text-amber-400'
  return 'text-green-600 dark:text-green-400'
}

const editingRecipe = ref<RecipeCostRow | null>(null)

const editForm = useForm<{ sell_price: number | null; fill_size_g: number | null; cost_buffer_percent: number | null }>({
  sell_price: null,
  fill_size_g: null,
  cost_buffer_percent: null,
})

const handleAction = (action: string, item: RecipeCostRow) => {
  if (action === 'edit-costing') {
    editingRecipe.value = item
    editForm.sell_price = item.sell_price
    editForm.fill_size_g = item.fill_size_g
    editForm.cost_buffer_percent = item.cost_buffer_percent
    editForm.clearErrors()
  }
}

const closeEditModal = () => {
  editingRecipe.value = null
}

const submitEdit = () => {
  if (!editingRecipe.value) return
  editForm.put(route('admin.costing.recipes.update-costing', editingRecipe.value.id), {
    preserveScroll: true,
    onSuccess: () => closeEditModal(),
  })
}
</script>
