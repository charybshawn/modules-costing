<template>
  <Modal :show="props.ingredient !== null" max-width="lg" @close="$emit('close')">
    <div v-if="props.ingredient" class="p-6">
      <h2 class="text-lg font-medium text-gray-900">Available Prices -- {{ props.ingredient.name }}</h2>
      <p class="mt-1 text-sm text-gray-500">
        Pick a wholesaler/brand to lock in as the preferred source for recipes and the production planner, regardless of price.
      </p>

      <div v-if="loadingPrices" class="mt-6 text-sm text-gray-500">Loading...</div>
      <div v-else-if="priceOptions.length === 0" class="mt-6 text-sm text-gray-500">
        No prices logged yet for this ingredient.
        <Link :href="route('admin.costing.price-history.create', { ingredient: props.ingredient.id })" class="font-medium text-indigo-600 hover:text-indigo-800">
          Log the first one.
        </Link>
      </div>
      <ul v-else class="mt-4 divide-y divide-gray-200 max-h-96 overflow-y-auto">
        <li v-for="option in priceOptions" :key="optionKey(option)" class="py-3 flex items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium text-gray-900">
              {{ option.provider }}<span v-if="option.brand"> — {{ option.brand }}</span>
              <span v-if="option.is_preferred" class="ml-2 inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-800">Preferred</span>
              <span v-if="option.is_stale" class="ml-2 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Needs update</span>
            </div>

            <div v-if="inlineEditKey !== optionKey(option)" class="text-sm text-gray-500">
              <span v-if="option.price_per_unit !== null">
                ${{ Number(option.price_per_unit).toFixed(2) }}{{ props.ingredient.unit_type === 'unit' ? '/unit' : '/kg' }}
                <span v-if="option.price_per_100g !== null">(${{ Number(option.price_per_100g).toFixed(2) }}/100g)</span>
              </span>
              <span v-else class="italic">incomplete</span>
              &middot; checked {{ option.purchased_at ?? 'never' }}
              <button type="button" @click="startInlineEdit(option)" class="ml-1 font-medium text-indigo-600 hover:text-indigo-800">Edit</button>
            </div>
            <div v-else class="mt-1 flex items-center gap-1.5">
              <span class="text-xs text-gray-500">New total price $</span>
              <input
                v-model.number="inlineEditPrice"
                type="number"
                min="0"
                step="0.01"
                autofocus
                :disabled="inlineEditSaving"
                class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                @keyup.enter="saveInlineEdit(option)"
                @keyup.esc="cancelInlineEdit"
              />
              <button type="button" @click="saveInlineEdit(option)" :disabled="inlineEditSaving" class="text-green-600 hover:text-green-800 disabled:opacity-40" title="Save">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
              </button>
              <button type="button" @click="cancelInlineEdit" :disabled="inlineEditSaving" class="text-gray-400 hover:text-gray-600 disabled:opacity-40" title="Cancel">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <p v-if="inlineEditKey === optionKey(option) && inlineEditError" class="mt-1 text-xs text-red-600">{{ inlineEditError }}</p>

            <div v-if="packageSizeEditKey !== optionKey(option)" class="text-xs text-gray-500 mt-0.5">
              <span v-if="option.package_size !== null">Package size: {{ option.package_size }} {{ props.ingredient.unit_type === 'unit' ? 'unit(s)' : 'g' }}</span>
              <span v-else class="italic">Package size: not set</span>
              <button type="button" @click="startPackageSizeEdit(option)" class="ml-1 font-medium text-indigo-600 hover:text-indigo-800">{{ option.package_size !== null ? 'Edit' : 'Add' }}</button>
            </div>
            <div v-else class="mt-1 flex items-center gap-1.5">
              <span class="text-xs text-gray-500">Package size ({{ props.ingredient.unit_type === 'unit' ? 'units' : 'g' }})</span>
              <input
                v-model.number="packageSizeEditValue"
                type="number"
                min="0.01"
                step="0.01"
                autofocus
                :disabled="packageSizeEditSaving"
                class="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                @keyup.enter="savePackageSizeEdit(option)"
                @keyup.esc="cancelPackageSizeEdit"
              />
              <button type="button" @click="savePackageSizeEdit(option)" :disabled="packageSizeEditSaving" class="text-green-600 hover:text-green-800 disabled:opacity-40" title="Save">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
              </button>
              <button type="button" @click="cancelPackageSizeEdit" :disabled="packageSizeEditSaving" class="text-gray-400 hover:text-gray-600 disabled:opacity-40" title="Cancel">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
            <p v-if="packageSizeEditKey === optionKey(option) && packageSizeEditError" class="mt-1 text-xs text-red-600">{{ packageSizeEditError }}</p>
          </div>
          <div class="flex-shrink-0">
            <button v-if="!option.is_preferred" type="button" @click="selectPreferred(option)" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
              Select as Preferred
            </button>
            <button v-else type="button" @click="clearPreferred()" class="text-sm font-medium text-gray-500 hover:text-gray-700">
              Clear
            </button>
          </div>
        </li>
      </ul>

      <div class="mt-6 flex items-center justify-between pt-4 border-t border-gray-200">
        <Link :href="route('admin.costing.price-history.create', { ingredient: props.ingredient.id })" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
          + Log a new price
        </Link>
        <button type="button" @click="$emit('close')" class="bg-gray-200 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
          Close
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import axios from 'axios'
import { Link, router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'

export interface PricesIngredient {
  id: number
  name: string
  unit_type: 'g' | 'unit'
}

interface PriceOption {
  price_history_entry_id: number
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
}

interface Props {
  ingredient: PricesIngredient | null
}

const props = defineProps<Props>()

defineEmits<{ close: [] }>()

const optionKey = (option: PriceOption) => `${option.provider}|${option.brand ?? ''}`

const priceOptions = ref<PriceOption[]>([])
const loadingPrices = ref(false)

const fetchPriceOptions = async (ingredientId: number) => {
  loadingPrices.value = true
  try {
    const { data } = await axios.get(route('admin.costing.ingredients.price-options', ingredientId))
    priceOptions.value = data.options
  } finally {
    loadingPrices.value = false
  }
}

// Reacts to the parent opening/closing this modal by setting :ingredient,
// rather than the parent having to call a fetch method directly.
watch(
  () => props.ingredient,
  (ingredient) => {
    priceOptions.value = []
    cancelInlineEdit()
    cancelPackageSizeEdit()
    if (ingredient) {
      fetchPriceOptions(ingredient.id)
    }
  },
)

const selectPreferred = (option: PriceOption) => {
  if (!props.ingredient) return
  router.post(
    route('admin.costing.ingredients.set-preferred', props.ingredient.id),
    { provider: option.provider, brand: option.brand },
    { preserveScroll: true, onSuccess: () => fetchPriceOptions(props.ingredient!.id) },
  )
}

const clearPreferred = () => {
  if (!props.ingredient) return
  router.post(
    route('admin.costing.ingredients.set-preferred', props.ingredient.id),
    { provider: null, brand: null },
    { preserveScroll: true, onSuccess: () => fetchPriceOptions(props.ingredient!.id) },
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
  if (inlineEditPrice.value === null || !props.ingredient) return
  const ingredientId = props.ingredient.id
  inlineEditSaving.value = true
  inlineEditError.value = null

  router.post(
    route('admin.costing.price-history.update-price', option.price_history_entry_id),
    { total_price: inlineEditPrice.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        cancelInlineEdit()
        fetchPriceOptions(ingredientId)
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
  if (packageSizeEditValue.value === null || !props.ingredient) return
  const ingredientId = props.ingredient.id
  packageSizeEditSaving.value = true
  packageSizeEditError.value = null

  router.post(
    route('admin.costing.ingredients.set-package-size', ingredientId),
    { provider: option.provider, brand: option.brand, package_size: packageSizeEditValue.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        cancelPackageSizeEdit()
        fetchPriceOptions(ingredientId)
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
</script>
