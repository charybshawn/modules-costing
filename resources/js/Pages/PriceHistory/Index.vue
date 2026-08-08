<template>
  <div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Price History</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Log every wholesaler you check, even if you don't buy. Only entries from the last 7 days count toward an ingredient's current price.
          </p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
          <Link :href="route('admin.costing.ingredients.index')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Ingredients
          </Link>
          <Link :href="route('admin.costing.price-history.create')" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Log Price
          </Link>
        </div>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <label class="mb-4 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input v-model="needsUpdateOnly" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
        Needs update only ({{ needsUpdateCount }})
      </label>

      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <DataTable
          :columns="columns"
          :items="filteredEntries"
          :actions="tableActions"
          searchable
          search-placeholder="Search price history..."
          empty-message="No price history logged yet."
          empty-action-label="Log your first price"
          :empty-action-href="route('admin.costing.price-history.create')"
          table-id="costing-price-history"
          item-key="id"
          @action="handleAction"
        >
          <template #cell-ingredient_name="{ item }">
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.ingredient_name }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">{{ item.notes }}</div>
          </template>

          <template #cell-qty="{ item }">
            <span class="text-sm text-gray-900 dark:text-white">{{ item.qty !== null ? formatQuantity(item.qty, item.unit_type ?? 'g') : '—' }}</span>
          </template>

          <template #cell-purchased_at="{ item }">
            <span class="text-sm text-gray-900 dark:text-white">{{ item.purchased_at ?? '—' }}</span>
            <span v-if="item.needs_update" class="ml-2 inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-300">
              needs update
            </span>
          </template>

          <template #cell-price_per_unit="{ item }">
            <span v-if="item.price_per_unit !== null" class="text-sm font-medium text-gray-900 dark:text-white">
              ${{ Number(item.price_per_unit).toFixed(2) }}
              <span v-if="item.price_per_100g !== null" class="font-normal text-gray-500 dark:text-gray-400">(${{ Number(item.price_per_100g).toFixed(2) }}/100g)</span>
            </span>
            <span v-else class="text-sm text-red-500 dark:text-red-400 italic">incomplete</span>
          </template>
        </DataTable>
      </div>

      <UpdatePriceModal :entry="updatingEntry" @close="updatingEntry = null" @updated="updatingEntry = null" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column, type Action } from '@/Components/Admin/DataTable.vue'
import UpdatePriceModal, { type UpdatePriceEntry } from '../Shared/UpdatePriceModal.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import { formatQuantity } from '../Shared/formatWeight'

defineOptions({ layout: AdminLayout })

interface PriceHistoryRow {
  id: number
  ingredient_id: number
  ingredient_name: string
  unit_type: 'g' | 'unit' | null
  purchased_at: string | null
  provider: string
  brand: string | null
  qty: number | null
  total_price: number | null
  sku: string | null
  notes: string | null
  price_per_unit: number | null
  price_per_100g: number | null
  needs_update: boolean
}

interface Props {
  entries: PriceHistoryRow[]
}

const props = defineProps<Props>()

// Defaults from ?needs_update=1 so the Dashboard's "Update Prices" card can
// deep-link straight into the filtered view instead of landing on everything.
const needsUpdateOnly = ref(new URLSearchParams(window.location.search).get('needs_update') === '1')
const needsUpdateCount = computed(() => props.entries.filter((e) => e.needs_update).length)
const filteredEntries = computed(() => (needsUpdateOnly.value ? props.entries.filter((e) => e.needs_update) : props.entries))

const columns: Column[] = [
  { key: 'ingredient_name', label: 'Ingredient', sortable: true },
  { key: 'purchased_at', label: 'Date', sortable: true },
  { key: 'provider', label: 'Wholesaler', sortable: true, filterable: true },
  { key: 'brand', label: 'Brand', sortable: true, hideable: true, filterable: true },
  { key: 'qty', label: 'Qty', hideable: true },
  { key: 'total_price', label: 'Total Price', hideable: true },
  { key: 'price_per_unit', label: '$/kg (or $/unit)' },
]

const tableActions: Action[] = [
  { name: 'update-price', icon: 'adjust', color: 'green', label: 'Update Price' },
  { name: 'clone', icon: 'duplicate', color: 'gray', label: 'Clone', href: (item) => route('admin.costing.price-history.create', { clone: item.id }) },
  { name: 'edit', icon: 'edit', color: 'indigo', label: 'Edit', href: (item) => route('admin.costing.price-history.edit', item.id) },
  { name: 'delete', icon: 'delete', color: 'red', label: 'Delete' },
]

const updatingEntry = ref<UpdatePriceEntry | null>(null)

const handleAction = (action: string, item: PriceHistoryRow) => {
  if (action === 'update-price') {
    updatingEntry.value = { id: item.id, ingredient_name: item.ingredient_name, provider: item.provider, brand: item.brand, qty: item.qty, unit_type: item.unit_type }
  } else if (action === 'delete') {
    if (confirm(`Delete this ${item.ingredient_name} price entry?`)) {
      router.delete(route('admin.costing.price-history.destroy', item.id), { preserveScroll: true })
    }
  }
}
</script>
