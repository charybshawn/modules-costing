<template>
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Inventory</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Current stock on hand. Update before each production run -- the Production Planner deducts from these amounts.
          </p>
        </div>
        <div class="mt-4 md:mt-0 flex gap-2">
          <button
            type="button"
            @click="openBulkModal"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
          >
            Bulk Update Stock
          </button>
          <Link :href="route('admin.costing.inventory.adjustments')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            View History
          </Link>
          <Link :href="route('admin.costing.ingredients.index')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Ingredients
          </Link>
        </div>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <DataTable
          :columns="columns"
          :items="ingredients"
          searchable
          search-placeholder="Search inventory..."
          empty-message="No ingredients yet."
          table-id="costing-inventory"
          item-key="ingredient_id"
        >
          <template #cell-name="{ item }">
            <button type="button" @click="openStockModal(item)" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 hover:underline text-left">
              {{ item.name }}
            </button>
          </template>

          <template #cell-category="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.category ?? '—' }}</span>
          </template>

          <template #cell-source_count="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.source_count }}</span>
          </template>

          <template #cell-on_hand="{ item }">
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ formatQuantity(item.on_hand, item.unit_type) }}</span>
          </template>
        </DataTable>
      </div>

      <StockAdjustModal :ingredient="stockIngredient" @close="closeStockModal" @updated="router.reload({ only: ['ingredients'] })" />

      <!-- Bulk Update Stock modal -->
      <Modal :show="showBulkModal" max-width="2xl" @close="closeBulkModal">
        <form @submit.prevent="submitBulk" class="p-6">
          <h2 class="text-lg font-medium text-gray-900">Bulk Update Stock</h2>
          <p class="mt-1 text-sm text-gray-500">Update several ingredients' stock in one go.</p>

          <FormErrorSummary :errors="bulkForm.errors" class="mt-4" />

          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Mode</label>
            <div class="grid grid-cols-2 gap-3">
              <label
                :class="[
                  'relative flex cursor-pointer rounded-lg border p-3 focus:outline-none',
                  bulkForm.mode === 'adjust' ? 'border-indigo-600 ring-1 ring-indigo-600 bg-indigo-50' : 'border-gray-300'
                ]"
              >
                <input v-model="bulkForm.mode" type="radio" value="adjust" class="sr-only" />
                <div>
                  <span class="block text-sm font-medium text-gray-900">Adjust</span>
                  <span class="block text-xs text-gray-500">Adds to or subtracts from current stock (received, correction, spoilage).</span>
                </div>
              </label>
              <label
                :class="[
                  'relative flex cursor-pointer rounded-lg border p-3 focus:outline-none',
                  bulkForm.mode === 'recount' ? 'border-indigo-600 ring-1 ring-indigo-600 bg-indigo-50' : 'border-gray-300'
                ]"
              >
                <input v-model="bulkForm.mode" type="radio" value="recount" class="sr-only" />
                <div>
                  <span class="block text-sm font-medium text-gray-900">Inventory Recount</span>
                  <span class="block text-xs text-gray-500">Replaces current stock with the entered amount (a physical count).</span>
                </div>
              </label>
            </div>
          </div>

          <div v-if="bulkForm.mode === 'adjust'" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Reason</label>
              <select v-model="bulkForm.reason" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="received">Stock received (bought more)</option>
                <option value="correction">Correction (mistake, spoilage, shrinkage)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700">Notes (optional, applies to whole batch)</label>
              <input v-model="bulkForm.notes" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. Weekly shopping run" />
            </div>
          </div>

          <div class="mt-4 space-y-3 max-h-80 overflow-y-auto">
            <div v-for="(row, index) in bulkForm.items" :key="index" class="flex items-center gap-3">
              <select
                v-model.number="row.ingredient_id"
                required
                class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
              >
                <option :value="null" disabled>Select an ingredient&hellip;</option>
                <option
                  v-for="ingredient in availableIngredients(row.ingredient_id)"
                  :key="ingredient.ingredient_id"
                  :value="ingredient.ingredient_id"
                >
                  {{ ingredient.name }} ({{ formatQuantity(ingredient.on_hand, ingredient.unit_type) }} on hand)
                </option>
              </select>
              <input
                v-model.number="row.quantity"
                type="number"
                :min="bulkForm.mode === 'recount' ? 0 : undefined"
                step="0.01"
                required
                :placeholder="bulkForm.mode === 'adjust' ? 'Qty (+/-)' : 'New count'"
                class="w-32 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
              />
              <button
                type="button"
                @click="removeRow(index)"
                :disabled="bulkForm.items.length === 1"
                class="text-gray-400 hover:text-red-600 disabled:opacity-30 disabled:hover:text-gray-400"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>

          <button
            type="button"
            @click="addRow"
            :disabled="bulkForm.items.length >= ingredients.length"
            class="mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-40 disabled:hover:text-indigo-600"
          >
            + Add another ingredient
          </button>

          <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200">
            <button type="button" @click="closeBulkModal" class="bg-gray-200 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
              Cancel
            </button>
            <button type="submit" :disabled="bulkForm.processing" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
              <span v-if="bulkForm.processing">Saving...</span>
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
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column } from '@/Components/Admin/DataTable.vue'
import Modal from '@/Components/Modal.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import StockAdjustModal, { type StockIngredient } from '../Shared/StockAdjustModal.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import { formatQuantity } from '../Shared/formatWeight'

defineOptions({ layout: AdminLayout })

interface InventoryRow {
  ingredient_id: number
  name: string
  category: string | null
  unit_type: 'g' | 'unit'
  on_hand: number
  source_count: number
  notes: string | null
}

interface Props {
  ingredients: InventoryRow[]
}

const props = defineProps<Props>()

const columns: Column[] = [
  { key: 'name', label: 'Ingredient', sortable: true },
  { key: 'category', label: 'Category', sortable: true, hideable: true, filterable: true },
  { key: 'source_count', label: 'Sources', hideable: true },
  { key: 'on_hand', label: 'On Hand' },
]

// Stock modal -- markup/logic lives in the shared component; this page
// only owns which ingredient (if any) it's open for, same pattern as
// Ingredients/Index.vue's AvailablePricesModal.
const stockIngredient = ref<StockIngredient | null>(null)

const openStockModal = (item: InventoryRow) => {
  stockIngredient.value = { id: item.ingredient_id, name: item.name, unit_type: item.unit_type }
}

const closeStockModal = () => {
  stockIngredient.value = null
}

// Bulk Update Stock modal
interface BulkFormData {
  mode: 'adjust' | 'recount'
  reason: 'received' | 'correction'
  notes: string
  items: Array<{ ingredient_id: number | null; quantity: number | null }>
}

const showBulkModal = ref(false)

const bulkForm = useForm<BulkFormData>({
  mode: 'adjust',
  reason: 'received',
  notes: '',
  items: [{ ingredient_id: null, quantity: null }],
})

const openBulkModal = () => {
  bulkForm.reset()
  bulkForm.clearErrors()
  showBulkModal.value = true
}

const closeBulkModal = () => {
  showBulkModal.value = false
}

const addRow = () => {
  bulkForm.items.push({ ingredient_id: null, quantity: null })
}

const removeRow = (index: number) => {
  if (bulkForm.items.length > 1) {
    bulkForm.items.splice(index, 1)
  }
}

// Ingredients already picked in another row shouldn't be selectable again
// (matches the server's 'distinct' validation on ingredient_id).
const availableIngredients = (currentValue: number | null) => {
  const chosen = new Set(bulkForm.items.map((row) => row.ingredient_id).filter((id) => id !== null && id !== currentValue))
  return props.ingredients.filter((i) => !chosen.has(i.ingredient_id))
}

const submitBulk = () => {
  bulkForm.post(route('admin.costing.inventory.bulk-update'), {
    preserveScroll: true,
    onSuccess: () => {
      showBulkModal.value = false
    },
  })
}
</script>
