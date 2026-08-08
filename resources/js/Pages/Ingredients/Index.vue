<template>
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Ingredients</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Master catalogue of ingredients. Pricing columns are calculated automatically from Price History.
          </p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
          <Link
            :href="route('admin.costing.price-history.index')"
            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
          >
            Price History
          </Link>
          <Link
            :href="route('admin.costing.ingredients.create')"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
          >
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Ingredient
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
          :sort-field="sortField"
          :sort-direction="sortDirection"
          :actions="tableActions"
          searchable
          search-placeholder="Search ingredients..."
          empty-message="No ingredients yet."
          empty-action-label="Add your first ingredient"
          :empty-action-href="route('admin.costing.ingredients.create')"
          table-id="costing-ingredients"
          item-key="id"
          @sort="handleSort"
          @action="handleAction"
        >
          <template #cell-name="{ item }">
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</div>
          </template>

          <template #cell-category="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.category ?? '—' }}</span>
          </template>

          <template #cell-waste_percent="{ item }">
            <span class="text-sm text-gray-900 dark:text-white">{{ item.waste_percent }}%</span>
          </template>

          <template #cell-weekly_price="{ item }">
            <button
              type="button"
              @click="openPricesModal(item)"
              class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 hover:underline"
            >
              <span v-if="item.status === 'ok'">
                ${{ Number(item.weekly_price).toFixed(2) }}{{ item.unit_type === 'unit' ? '/unit' : '/kg' }}
                <span v-if="item.price_per_100g !== null" class="font-normal">(${{ Number(item.price_per_100g).toFixed(2) }}/100g)</span>
              </span>
              <span v-else-if="item.stale_price !== null">
                ${{ Number(item.stale_price).toFixed(2) }}{{ item.unit_type === 'unit' ? '/unit' : '/kg' }}
                <span v-if="item.stale_price_per_100g !== null" class="font-normal">(${{ Number(item.stale_price_per_100g).toFixed(2) }}/100g)</span>
              </span>
              <span v-else class="italic font-normal">no price logged</span>
            </button>
            <span v-if="item.status !== 'ok' && item.stale_price !== null" class="ml-1.5 inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-300">
              needs update
            </span>
          </template>

          <template #cell-effective_price="{ item }">
            <span v-if="item.status === 'ok'" class="text-sm text-gray-900 dark:text-white">
              ${{ Number(item.effective_price).toFixed(2) }}
            </span>
            <span v-else-if="item.stale_effective_price !== null" class="text-sm text-gray-500 dark:text-gray-400">
              ${{ Number(item.stale_effective_price).toFixed(2) }}
            </span>
            <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>
          </template>

          <template #cell-source_count="{ item }">
            <button
              type="button"
              @click="openPricesModal(item)"
              class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 hover:underline"
            >
              {{ item.source_count }} source{{ item.source_count !== 1 ? 's' : '' }}..
            </button>
          </template>

          <template #cell-purchase_unit="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.purchase_unit ?? '—' }}</span>
          </template>
        </DataTable>
      </div>

      <AvailablePricesModal :ingredient="pricesIngredient" @close="closePricesModal" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column, type Action } from '@/Components/Admin/DataTable.vue'
import AvailablePricesModal, { type PricesIngredient } from '../Shared/AvailablePricesModal.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

defineOptions({ layout: AdminLayout })

interface IngredientRow {
  id: number
  name: string
  category: string | null
  unit_type: 'g' | 'unit'
  waste_percent: number
  weekly_price: number | null
  effective_price: number | null
  source_count: number
  purchase_unit: string | null
  status: 'ok' | 'no_price_this_week'
  stale_price: number | null
  stale_effective_price: number | null
  price_per_100g: number | null
  stale_price_per_100g: number | null
}

interface Props {
  ingredients: IngredientRow[]
}

const props = defineProps<Props>()

const sortField = ref('name')
const sortDirection = ref<'asc' | 'desc'>('asc')

const handleSort = (field: string) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
}

const ingredients = computed(() => {
  const field = sortField.value as keyof IngredientRow
  const dir = sortDirection.value === 'asc' ? 1 : -1
  return [...props.ingredients].sort((a, b) => String(a[field] ?? '').localeCompare(String(b[field] ?? '')) * dir)
})

const columns: Column[] = [
  { key: 'name', label: 'Ingredient', sortable: true },
  { key: 'category', label: 'Category', sortable: true, hideable: true, filterable: true },
  { key: 'waste_percent', label: 'Waste %', hideable: true },
  { key: 'weekly_price', label: '$/kg', hideable: true },
  { key: 'effective_price', label: 'Effective $/kg', hideable: true },
  { key: 'source_count', label: 'Sources', hideable: true },
  { key: 'purchase_unit', label: 'Purchase Unit', hideable: true },
]

const tableActions: Action[] = [
  { name: 'edit', icon: 'edit', color: 'indigo', label: 'Edit', href: (item) => route('admin.costing.ingredients.edit', item.id) },
  { name: 'delete', icon: 'delete', color: 'red', label: 'Delete' },
]

const handleAction = (action: string, item: IngredientRow) => {
  if (action === 'delete') {
    if (confirm(`Delete "${item.name}"? This also removes its price history and inventory record.`)) {
      router.delete(route('admin.costing.ingredients.destroy', item.id), { preserveScroll: true })
    }
  }
}

// Available Prices modal -- markup/logic lives in the shared component;
// this page only owns which ingredient (if any) it's open for.
const pricesIngredient = ref<PricesIngredient | null>(null)

const openPricesModal = (item: IngredientRow) => {
  pricesIngredient.value = { id: item.id, name: item.name, unit_type: item.unit_type }
}

const closePricesModal = () => {
  pricesIngredient.value = null
}
</script>
