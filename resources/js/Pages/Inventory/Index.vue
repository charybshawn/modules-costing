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
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
          <button
            type="button"
            @click="openBulkModal"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
          >
            Bulk Update Stock
          </button>
          <button
            type="button"
            @click="openAddItemModal"
            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
          >
            + Add Item
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
          <h2 class="text-lg font-medium text-gray-900 dark:text-white">Bulk Update Stock</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update several ingredients' stock in one go.</p>

          <FormErrorSummary :errors="bulkForm.errors" class="mt-4" />

          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mode</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <label
                :class="[
                  'relative flex cursor-pointer rounded-lg border p-3 focus:outline-none',
                  bulkForm.mode === 'adjust' ? 'border-indigo-600 ring-1 ring-indigo-600 bg-indigo-50 dark:bg-indigo-900/30' : 'border-gray-300 dark:border-gray-600'
                ]"
              >
                <input v-model="bulkForm.mode" type="radio" value="adjust" class="sr-only" />
                <div>
                  <span class="block text-sm font-medium text-gray-900 dark:text-white">Adjust</span>
                  <span class="block text-xs text-gray-500 dark:text-gray-400">Adds to or subtracts from current stock (received, correction, spoilage).</span>
                </div>
              </label>
              <label
                :class="[
                  'relative flex cursor-pointer rounded-lg border p-3 focus:outline-none',
                  bulkForm.mode === 'recount' ? 'border-indigo-600 ring-1 ring-indigo-600 bg-indigo-50 dark:bg-indigo-900/30' : 'border-gray-300 dark:border-gray-600'
                ]"
              >
                <input v-model="bulkForm.mode" type="radio" value="recount" class="sr-only" />
                <div>
                  <span class="block text-sm font-medium text-gray-900 dark:text-white">Inventory Recount</span>
                  <span class="block text-xs text-gray-500 dark:text-gray-400">Replaces current stock with the entered amount (a physical count).</span>
                </div>
              </label>
            </div>
          </div>

          <div v-if="bulkForm.mode === 'adjust'" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason</label>
              <select v-model="bulkForm.reason" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="received">Stock received (bought more)</option>
                <option value="correction">Correction (mistake, spoilage, shrinkage)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes (optional, applies to whole batch)</label>
              <input v-model="bulkForm.notes" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="e.g. Weekly shopping run" />
            </div>
          </div>

          <div class="mt-4 space-y-3 max-h-80 overflow-y-auto">
            <div v-for="(row, index) in bulkForm.items" :key="index">
              <div class="flex flex-wrap items-center gap-3">
                <select
                  v-model.number="row.ingredient_id"
                  required
                  @change="onIngredientChange(row)"
                  class="flex-1 min-w-[10rem] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                >
                  <option :value="null" disabled>Select an ingredient&hellip;</option>
                  <option
                    v-for="ingredient in availableIngredients(row.ingredient_id)"
                    :key="ingredient.ingredient_id"
                    :value="ingredient.ingredient_id"
                  >
                    {{ ingredient.name }} ({{ formatQuantity(ingredient.on_hand, ingredient.unit_type) }} on hand){{ ingredient.source_count === 0 ? ' -- no sources yet' : '' }}
                  </option>
                </select>
                <select
                  v-model.number="row.package_size_id"
                  required
                  :disabled="sourcesFor(row.ingredient_id).length === 0"
                  class="flex-1 min-w-[10rem] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:opacity-50"
                >
                  <option :value="null" disabled>{{ sourcesFor(row.ingredient_id).length === 0 ? 'No sources yet' : 'Select a source…' }}</option>
                  <option v-for="source in sourcesFor(row.ingredient_id)" :key="source.id" :value="source.id">
                    {{ source.provider }}{{ source.brand ? ' — ' + source.brand : '' }} ({{ formatQuantity(source.package_size, ingredientFor(row.ingredient_id)?.unit_type ?? 'g') }}/pkg{{ source.units_per_case > 1 ? `, case of ${source.units_per_case}` : '' }})
                  </option>
                </select>
                <input
                  v-model.number="row.packages"
                  type="number"
                  inputmode="decimal"
                  :min="bulkForm.mode === 'recount' ? 0 : undefined"
                  step="0.01"
                  required
                  :disabled="row.package_size_id === null"
                  :placeholder="bulkForm.mode === 'adjust' ? 'Packages (+/-)' : 'Packages on hand'"
                  class="w-full sm:w-36 flex-1 sm:flex-none min-w-0 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:opacity-50"
                />
                <button
                  type="button"
                  @click="removeRow(index)"
                  :disabled="bulkForm.items.length === 1"
                  class="text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 disabled:opacity-30 disabled:hover:text-gray-400 dark:disabled:hover:text-gray-500"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <p v-if="row.package_size_id !== null" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ packageSizeHint(row) }}
              </p>
            </div>
          </div>

          <button
            type="button"
            @click="addRow"
            :disabled="bulkForm.items.length >= ingredients.length"
            class="mt-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 disabled:opacity-40 disabled:hover:text-indigo-600 dark:disabled:hover:text-indigo-400"
          >
            + Add another ingredient
          </button>

          <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="closeBulkModal" class="bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
              Cancel
            </button>
            <button type="submit" :disabled="bulkForm.processing" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
              <span v-if="bulkForm.processing">Saving...</span>
              <span v-else>Save</span>
            </button>
          </div>
        </form>
      </Modal>

      <!-- Add Item modal -- for something not in the system at all yet.
           An existing ingredient that just needs a new source still uses
           the per-item Stock modal (opened via the ingredient name above),
           not this. -->
      <Modal :show="showAddItemModal" max-width="lg" @close="closeAddItemModal">
        <form @submit.prevent="submitAddItem" class="p-6">
          <h2 class="text-lg font-medium text-gray-900 dark:text-white">Add Item</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">For something not in the system yet -- creates the ingredient, its first source, and a starting count in one go.</p>

          <FormErrorSummary :errors="addItemForm.errors" class="mt-4" />

          <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
              <input v-model="addItemForm.name" type="text" required autofocus placeholder="e.g. Cream Cheese" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
              <input v-model="addItemForm.category" type="text" placeholder="e.g. Dairy & Eggs" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Measured In *</label>
              <select v-model="addItemForm.unit_type" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="g">Grams (priced per kg)</option>
                <option value="unit">Units (priced per unit)</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Waste % *</label>
              <input v-model.number="addItemForm.waste_percent" type="number" min="1" max="100" step="0.01" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">100 = no waste, 95 = 5% trim loss. Adjust later on the Ingredients page if unsure.</p>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">First Source</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Provider *</label>
                <input v-model="addItemForm.provider" type="text" required placeholder="e.g. GFS" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Brand</label>
                <input v-model="addItemForm.brand" type="text" placeholder="Optional" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Size of 1 Package *</label>
                <input v-model.number="addItemForm.package_size" type="number" min="0.01" step="0.01" required :placeholder="addItemForm.unit_type === 'unit' ? 'e.g. 1' : 'e.g. 3500'" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ addItemForm.unit_type === 'unit' ? 'ONE individual package -- e.g. 1 lid, 1 bag. Never the case total, even if sold by the case (use Units Per Case for that).' : 'Grams in ONE package -- e.g. 3.5kg = 3500. Never the case total.' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Units Per Case</label>
                <input v-model.number="addItemForm.units_per_case" type="number" min="1" step="1" placeholder="1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Purchasing info only -- how many of the above come in one case. Doesn't change how stock is counted. Leave at 1 if not sold by the case.</p>
              </div>
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Packages On Hand</label>
                <input v-model.number="addItemForm.packages" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  <template v-if="addItemQuantityPreview">= {{ addItemQuantityPreview }}</template>
                  <template v-else>Leave blank to add this item with zero stock for now. Always individual packages, not cases.</template>
                </p>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="closeAddItemModal" class="bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
              Cancel
            </button>
            <button type="submit" :disabled="addItemForm.processing" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
              <span v-if="addItemForm.processing">Adding...</span>
              <span v-else>Add Item</span>
            </button>
          </div>
        </form>
      </Modal>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column } from '@/Components/Admin/DataTable.vue'
import Modal from '@/Components/Modal.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import StockAdjustModal, { type StockIngredient } from '../Shared/StockAdjustModal.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import { formatQuantity } from '../Shared/formatWeight'

defineOptions({ layout: AdminLayout })

interface IngredientSource {
  id: number
  provider: string
  brand: string | null
  package_size: number
  units_per_case: number
}

interface InventoryRow {
  ingredient_id: number
  name: string
  category: string | null
  unit_type: 'g' | 'unit'
  on_hand: number
  source_count: number
  sources: IngredientSource[]
  preferred_source_id: number | null
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
  items: Array<{ ingredient_id: number | null; package_size_id: number | null; packages: number | null }>
}

const showBulkModal = ref(false)

const bulkForm = useForm<BulkFormData>({
  mode: 'adjust',
  reason: 'received',
  notes: '',
  items: [{ ingredient_id: null, package_size_id: null, packages: null }],
})

const openBulkModal = () => {
  bulkForm.reset()
  bulkForm.clearErrors()
  showBulkModal.value = true
}

const closeBulkModal = () => {
  showBulkModal.value = false
}

// Dashboard's "Log a Purchase" card deep-links here with ?open_bulk=1 --
// the bulk form already defaults to adjust/received, exactly that flow.
onMounted(() => {
  if (new URLSearchParams(window.location.search).get('open_bulk') === '1') {
    openBulkModal()
  }
})

const addRow = () => {
  bulkForm.items.push({ ingredient_id: null, package_size_id: null, packages: null })
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

const ingredientFor = (ingredientId: number | null): InventoryRow | null => {
  if (ingredientId === null) return null
  return props.ingredients.find((i) => i.ingredient_id === ingredientId) ?? null
}

const sourcesFor = (ingredientId: number | null): IngredientSource[] => ingredientFor(ingredientId)?.sources ?? []

// Picking a new ingredient resets the source/quantity below it -- a
// previously-chosen source almost certainly doesn't belong to the newly
// picked ingredient. Preselects the ingredient's preferred (else first)
// source as a starting point, same default StockAdjustModal's own picker
// would land on, but the user can still change it to any real source.
const onIngredientChange = (row: { ingredient_id: number | null; package_size_id: number | null; packages: number | null }) => {
  const ingredient = ingredientFor(row.ingredient_id)
  row.package_size_id = ingredient?.preferred_source_id ?? ingredient?.sources[0]?.id ?? null
  row.packages = null
}

// The packages field is entered in whole-or-fractional packages (e.g. 0.5
// for half a package used), not the ingredient's raw base unit -- there's
// no other way to tell what "one package" of the chosen source actually
// is, or to see the resulting total, without leaving this modal.
const packageSizeHint = (row: { ingredient_id: number | null; package_size_id: number | null; packages: number | null }): string => {
  const ingredient = ingredientFor(row.ingredient_id)
  const source = ingredient?.sources.find((s) => s.id === row.package_size_id)
  if (!ingredient || !source) return ''

  const size = formatQuantity(source.package_size, ingredient.unit_type)
  const label = source.brand ? `${source.provider} — ${source.brand}` : source.provider
  const caseSuffix = source.units_per_case > 1 ? `, case of ${source.units_per_case}` : ''
  const base = `1 package = ${size}${caseSuffix} (${label})`

  if (!row.packages) return base

  // Always individual packages, never cases -- recount/adjust granularity
  // is unaffected by units_per_case, which only matters for purchasing.
  const total = row.packages * source.package_size
  const sign = total < 0 ? '−' : ''
  return `${base} → ${row.packages} × ${size} = ${sign}${formatQuantity(Math.abs(total), ingredient.unit_type)}`
}

const submitBulk = () => {
  bulkForm.post(route('admin.costing.inventory.bulk-update'), {
    preserveScroll: true,
    onSuccess: () => {
      showBulkModal.value = false
    },
  })
}

// Add Item modal -- onboards something not in the system at all yet:
// creates the Ingredient, its first Source, and an optional starting
// count in one submission (previously three separate screens). An
// existing ingredient that just needs a new source still uses the
// per-item Stock modal (openStockModal above), not this.
interface AddItemFormData {
  name: string
  category: string
  unit_type: 'g' | 'unit'
  waste_percent: number
  provider: string
  brand: string
  package_size: number | null
  units_per_case: number
  packages: number | null
}

const showAddItemModal = ref(false)

const addItemForm = useForm<AddItemFormData>({
  name: '',
  category: '',
  unit_type: 'g',
  waste_percent: 100,
  provider: '',
  brand: '',
  package_size: null,
  units_per_case: 1,
  packages: null,
})

const openAddItemModal = () => {
  addItemForm.reset()
  addItemForm.clearErrors()
  showAddItemModal.value = true
}

const closeAddItemModal = () => {
  showAddItemModal.value = false
}

const addItemQuantityPreview = computed<string | null>(() => {
  if (!addItemForm.packages || !addItemForm.package_size) return null
  return formatQuantity(addItemForm.packages * addItemForm.package_size, addItemForm.unit_type)
})

const submitAddItem = () => {
  addItemForm.post(route('admin.costing.inventory.items.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showAddItemModal.value = false
    },
  })
}
</script>
