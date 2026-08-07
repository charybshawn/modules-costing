<template>
  <div>
    <div v-if="loadingPrices" class="mt-6 text-sm text-gray-500 dark:text-gray-400">Loading...</div>

    <p v-else-if="priceOptions.length === 0" class="mt-6 text-sm text-gray-500 dark:text-gray-400">No sources logged yet for this ingredient.</p>

    <div v-else class="mt-4 -mx-6 max-h-96 overflow-y-auto">
      <DataTable :columns="columns" :items="priceOptions" item-key="package_size_id">
        <template #cell-source="{ item }">
          <span class="text-sm text-gray-900 dark:text-white">{{ item.provider }}<span v-if="item.brand"> — {{ item.brand }}</span></span>
          <span v-if="item.is_stale" class="ml-1 text-xs text-amber-600 dark:text-amber-400">needs update</span>
          <button
            v-if="item.package_size_id !== null"
            type="button"
            @click="deleteSource(item)"
            :disabled="item.quantity_on_hand > 0"
            class="ml-1.5 text-gray-300 dark:text-gray-600 hover:text-red-600 dark:hover:text-red-400 disabled:opacity-40 disabled:hover:text-gray-300 dark:disabled:hover:text-gray-600"
            :title="item.quantity_on_hand > 0 ? 'Still has stock on hand -- recount it to 0 on Inventory first' : 'Remove this source'"
          >
            <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </template>

        <template #cell-package_size="{ item }">
          <button
            v-if="packageSizeEditKey !== optionKey(item)"
            type="button"
            @click="startPackageSizeEdit(item)"
            class="text-sm -mx-2 px-2 py-0.5 rounded-md border border-transparent hover:border-gray-300 dark:hover:border-gray-500 transition-colors"
          >
            <span v-if="item.package_size !== null" class="text-gray-700 dark:text-gray-300">{{ formatQuantity(item.package_size, props.ingredient.unit_type) }}</span>
            <span v-else class="italic text-gray-400 dark:text-gray-500">not set</span>
          </button>
          <div v-else class="flex items-center gap-1">
            <input
              v-model.number="packageSizeEditValue"
              type="number"
              min="0.01"
              step="0.01"
              autofocus
              :disabled="packageSizeEditSaving"
              class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
              @keyup.enter="savePackageSizeEdit(item)"
              @keyup.esc="cancelPackageSizeEdit"
            />
            <button type="button" @click="savePackageSizeEdit(item)" :disabled="packageSizeEditSaving" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 disabled:opacity-40" title="Save">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </button>
            <button type="button" @click="cancelPackageSizeEdit" :disabled="packageSizeEditSaving" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-40" title="Cancel">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <p v-if="packageSizeEditKey === optionKey(item) && packageSizeEditError" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ packageSizeEditError }}</p>
        </template>

        <template #cell-price="{ item }">
          <button
            v-if="inlineEditKey !== optionKey(item)"
            type="button"
            @click="startInlineEdit(item)"
            class="text-sm -mx-2 px-2 py-0.5 rounded-md border border-transparent hover:border-gray-300 dark:hover:border-gray-500 transition-colors"
          >
            <span v-if="item.price_per_unit !== null" class="text-gray-900 dark:text-white">
              ${{ Number(item.price_per_unit).toFixed(2) }}{{ props.ingredient.unit_type === 'unit' ? '/unit' : '/kg' }}
            </span>
            <span v-else class="italic text-gray-400 dark:text-gray-500">incomplete</span>
          </button>
          <div v-else class="flex items-center gap-1">
            <span class="text-xs text-gray-500 dark:text-gray-400">$</span>
            <input
              v-model.number="inlineEditPrice"
              type="number"
              min="0"
              step="0.01"
              autofocus
              :disabled="inlineEditSaving"
              class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
              @keyup.enter="saveInlineEdit(item)"
              @keyup.esc="cancelInlineEdit"
            />
            <button type="button" @click="saveInlineEdit(item)" :disabled="inlineEditSaving" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 disabled:opacity-40" title="Save">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </button>
            <button type="button" @click="cancelInlineEdit" :disabled="inlineEditSaving" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-40" title="Cancel">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <p v-if="inlineEditKey === optionKey(item) && inlineEditError" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ inlineEditError }}</p>
        </template>

        <template #cell-checked="{ item }">
          <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.purchased_at ?? 'never' }}</span>
        </template>

        <template #cell-preferred="{ item }">
          <button v-if="!item.is_preferred" type="button" @click="selectPreferred(item)" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
            Set preferred
          </button>
          <span v-else class="text-sm font-medium text-indigo-700 dark:text-indigo-400">
            Preferred
            <button type="button" @click="clearPreferred()" class="ml-1 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300" title="Clear">&times;</button>
          </span>
        </template>
      </DataTable>
    </div>

    <div class="mt-4">
      <button v-if="!addingSource" type="button" @click="startAddSource" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
        + Add a new source
      </button>
      <div v-else class="rounded-md border border-gray-200 dark:border-gray-600 p-3 space-y-2">
        <div class="grid grid-cols-2 gap-2">
          <input v-model="newSourceProvider" type="text" placeholder="Provider (e.g. GFS)" autofocus :disabled="newSourceSaving" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
          <input v-model="newSourceBrand" type="text" placeholder="Brand (optional)" :disabled="newSourceSaving" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
        </div>
        <div class="grid grid-cols-2 gap-2">
          <input v-model.number="newSourceSize" type="number" min="0.01" step="0.01" :placeholder="`Package size (${props.ingredient.unit_type === 'unit' ? 'units' : 'g'})`" :disabled="newSourceSaving" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
          <input v-model.number="newSourcePrice" type="number" min="0" step="0.01" placeholder="Price for one package ($)" :disabled="newSourceSaving" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="saveNewSource"
            :disabled="newSourceSaving || !newSourceProvider || !newSourceSize || newSourcePrice === null"
            class="py-1.5 px-4 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40"
          >
            <span v-if="newSourceSaving">Saving...</span>
            <span v-else>Add</span>
          </button>
          <button type="button" @click="cancelAddSource" :disabled="newSourceSaving" class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Cancel</button>
        </div>
        <p v-if="newSourceError" class="text-xs text-red-600 dark:text-red-400">{{ newSourceError }}</p>
      </div>
    </div>

    <div class="mt-4">
      <Link :href="route('admin.costing.price-history.create', { ingredient: props.ingredient.id })" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
        Advanced entry (custom date, notes, SKU)
      </Link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import DataTable, { type Column } from '@/Components/Admin/DataTable.vue'
import { formatQuantity } from './formatWeight'

export interface SourcesIngredient {
  id: number
  name: string
  unit_type: 'g' | 'unit'
}

interface PriceOption {
  price_history_entry_id: number
  package_size_id: number | null
  provider: string
  brand: string | null
  price_per_unit: number | null
  price_per_100g: number | null
  total_price: number | null
  qty: number | null
  purchased_at: string | null
  is_stale: boolean
  is_preferred: boolean
  package_size: number | null
  quantity_on_hand: number
}

interface Props {
  ingredient: SourcesIngredient
}

const props = defineProps<Props>()

// Minimalist datatable, no search/filter/column-hide chrome needed -- a
// single ingredient rarely has more than a handful of sources.
const columns: Column[] = [
  { key: 'source', label: 'Source' },
  { key: 'package_size', label: 'Package Size' },
  { key: 'price', label: 'Price' },
  { key: 'checked', label: 'Checked' },
  { key: 'preferred', label: '' },
]

const optionKey = (option: PriceOption) => String(option.package_size_id ?? `${option.provider}|${option.brand ?? ''}`)

// Reuses Inventory's own delete endpoint -- same guard applies here
// (blocked while stock is still on hand), so there's exactly one place
// that decides whether a source can be removed.
const deleteSource = (option: PriceOption) => {
  if (option.package_size_id === null) return
  const label = option.brand ? `${option.provider} — ${option.brand}` : option.provider
  if (!confirm(`Remove "${label}" as a source for ${props.ingredient.name}? Its price history is kept, just no longer linked to this source.`)) return

  router.delete(route('admin.costing.inventory.sources.destroy', [props.ingredient.id, option.package_size_id]), {
    preserveScroll: true,
    onSuccess: () => fetchPriceOptions(),
  })
}

const priceOptions = ref<PriceOption[]>([])
const loadingPrices = ref(false)

const fetchPriceOptions = async () => {
  loadingPrices.value = true
  try {
    const { data } = await axios.get(route('admin.costing.ingredients.price-options', props.ingredient.id))
    priceOptions.value = data.options
  } finally {
    loadingPrices.value = false
  }
}

onMounted(() => fetchPriceOptions())

const selectPreferred = (option: PriceOption) => {
  router.post(
    route('admin.costing.ingredients.set-preferred', props.ingredient.id),
    { provider: option.provider, brand: option.brand },
    { preserveScroll: true, onSuccess: () => fetchPriceOptions() },
  )
}

const clearPreferred = () => {
  router.post(
    route('admin.costing.ingredients.set-preferred', props.ingredient.id),
    { provider: null, brand: null },
    { preserveScroll: true, onSuccess: () => fetchPriceOptions() },
  )
}

// Inline price editing -- only one row editable at a time. Reuses the same
// update-price endpoint as everywhere else (new dated-today entry, same
// qty/provider/brand, just a new price).
const inlineEditKey = ref<string | null>(null)
const inlineEditPrice = ref<number | null>(null)
const inlineEditSaving = ref(false)
const inlineEditError = ref<string | null>(null)

const startInlineEdit = (option: PriceOption) => {
  inlineEditKey.value = optionKey(option)
  inlineEditPrice.value = option.total_price
  inlineEditError.value = null
}

const cancelInlineEdit = () => {
  inlineEditKey.value = null
  inlineEditPrice.value = null
  inlineEditError.value = null
}

const saveInlineEdit = (option: PriceOption) => {
  if (inlineEditPrice.value === null) return
  inlineEditSaving.value = true
  inlineEditError.value = null

  router.post(
    route('admin.costing.price-history.update-price', option.price_history_entry_id),
    { total_price: inlineEditPrice.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        cancelInlineEdit()
        fetchPriceOptions()
      },
      onError: (errors) => {
        inlineEditError.value = errors.total_price ?? 'Could not save.'
      },
      onFinish: () => {
        inlineEditSaving.value = false
      },
    },
  )
}

// Package size editing -- independent of the price inline edit above (a
// different action, a different endpoint), so it gets its own toggle state
// rather than sharing inlineEditKey.
const packageSizeEditKey = ref<string | null>(null)
const packageSizeEditValue = ref<number | null>(null)
const packageSizeEditSaving = ref(false)
const packageSizeEditError = ref<string | null>(null)

const startPackageSizeEdit = (option: PriceOption) => {
  packageSizeEditKey.value = optionKey(option)
  packageSizeEditValue.value = option.package_size
  packageSizeEditError.value = null
}

const cancelPackageSizeEdit = () => {
  packageSizeEditKey.value = null
  packageSizeEditValue.value = null
  packageSizeEditError.value = null
}

const savePackageSizeEdit = (option: PriceOption) => {
  if (packageSizeEditValue.value === null) return
  packageSizeEditSaving.value = true
  packageSizeEditError.value = null

  router.post(
    route('admin.costing.ingredients.set-package-size', props.ingredient.id),
    { provider: option.provider, brand: option.brand, package_size: packageSizeEditValue.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        cancelPackageSizeEdit()
        fetchPriceOptions()
      },
      onError: (errors) => {
        packageSizeEditError.value = errors.package_size ?? 'Could not save.'
      },
      onFinish: () => {
        packageSizeEditSaving.value = false
      },
    },
  )
}

// Add a brand-new source (identity + package size) and its first logged
// price in one step -- price_history.create's full form is still there via
// "Advanced entry" for anything needing a custom date/notes/SKU, but the
// common case ("found a new supplier, here's what one package costs") no
// longer needs to leave this view. qty defaults to package_size (i.e.
// "one package"), matching how price_per_unit is computed everywhere else.
const addingSource = ref(false)
const newSourceProvider = ref('')
const newSourceBrand = ref('')
const newSourceSize = ref<number | null>(null)
const newSourcePrice = ref<number | null>(null)
const newSourceSaving = ref(false)
const newSourceError = ref<string | null>(null)

const startAddSource = () => {
  addingSource.value = true
  newSourceProvider.value = ''
  newSourceBrand.value = ''
  newSourceSize.value = null
  newSourcePrice.value = null
  newSourceError.value = null
}

const cancelAddSource = () => {
  addingSource.value = false
  newSourceError.value = null
}

const saveNewSource = () => {
  if (!newSourceProvider.value || !newSourceSize.value || newSourcePrice.value === null) return
  const provider = newSourceProvider.value
  const brand = newSourceBrand.value || null
  const packageSize = newSourceSize.value
  const price = newSourcePrice.value

  newSourceSaving.value = true
  newSourceError.value = null

  router.post(
    route('admin.costing.ingredients.set-package-size', props.ingredient.id),
    { provider, brand, package_size: packageSize },
    {
      preserveScroll: true,
      onSuccess: async () => {
        // set-package-size redirects rather than returning JSON, so the
        // new (or matched-existing) Source's id has to be looked up --
        // same endpoint Inventory's StockAdjustModal already reads.
        const { data } = await axios.get(route('admin.costing.inventory.sources', props.ingredient.id))
        const source = data.sources.find((s: { id: number; provider: string; brand: string | null }) => s.provider === provider && (s.brand ?? null) === brand)

        if (!source) {
          newSourceError.value = 'Source was created but could not be found -- try again.'
          newSourceSaving.value = false
          return
        }

        router.post(
          route('admin.costing.price-history.store'),
          {
            ingredient_id: props.ingredient.id,
            package_size_id: source.id,
            purchased_at: new Date().toISOString().slice(0, 10),
            qty: packageSize,
            total_price: price,
            // Otherwise store() redirects to the Price History index,
            // navigating away from wherever this table is embedded.
            stay: true,
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              cancelAddSource()
              fetchPriceOptions()
            },
            onError: (errors) => {
              newSourceError.value = errors.total_price ?? errors.package_size_id ?? 'Could not log the price.'
            },
            onFinish: () => {
              newSourceSaving.value = false
            },
          },
        )
      },
      onError: (errors) => {
        newSourceError.value = errors.package_size ?? errors.provider ?? 'Could not save.'
        newSourceSaving.value = false
      },
    },
  )
}
</script>
