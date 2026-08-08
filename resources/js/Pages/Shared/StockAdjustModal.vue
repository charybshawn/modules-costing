<template>
  <Modal :show="props.ingredient !== null" max-width="lg" @close="$emit('close')">
    <div v-if="props.ingredient" class="p-6">
      <h2 class="text-lg font-medium text-gray-900 dark:text-white">Stock -- {{ props.ingredient.name }}</h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        On Hand: <span class="font-semibold">{{ fmt(totalOnHand) }}</span>
      </p>

      <div v-if="loadingSources" class="mt-6 text-sm text-gray-500 dark:text-gray-400">Loading...</div>
      <p v-else-if="sources.length === 0" class="mt-6 text-sm text-gray-500 dark:text-gray-400">No sources yet -- add one below.</p>
      <ul v-else class="mt-4 divide-y divide-gray-200 dark:divide-gray-700 max-h-[28rem] overflow-y-auto">
        <li v-for="source in sources" :key="source.id" class="py-3">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
                {{ source.provider }}<span v-if="source.brand"> — {{ source.brand }}</span>
                <button
                  type="button"
                  @click="deleteSource(source)"
                  :disabled="source.quantity_on_hand > 0"
                  class="text-gray-300 dark:text-gray-600 hover:text-red-600 dark:hover:text-red-400 disabled:opacity-40 disabled:hover:text-gray-300 dark:disabled:hover:text-gray-600"
                  :title="source.quantity_on_hand > 0 ? 'Recount to 0 first, then remove' : 'Remove this source'"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                </button>
              </div>

              <div v-if="recountKey !== source.id" class="text-sm text-gray-500 dark:text-gray-400">
                {{ formatPackages(source.packages) }} × {{ fmt(source.package_size) }} = {{ fmt(source.quantity_on_hand) }}
                <button type="button" @click="startRecount(source)" class="ml-1 font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">Recount</button>
              </div>
              <div v-else class="mt-1 flex items-center gap-1.5">
                <span class="text-xs text-gray-500 dark:text-gray-400">Packages of {{ fmt(source.package_size) }}</span>
                <input
                  v-model.number="recountPackages"
                  type="number"
                  inputmode="decimal"
                  min="0"
                  step="0.01"
                  autofocus
                  :disabled="recountSaving"
                  class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                  @keyup.enter="saveRecount(source)"
                  @keyup.esc="cancelRecount"
                />
                <button type="button" @click="saveRecount(source)" :disabled="recountSaving" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300 disabled:opacity-40" title="Save">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                </button>
                <button type="button" @click="cancelRecount" :disabled="recountSaving" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-40" title="Cancel">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
              <p v-if="recountKey === source.id && recountError" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ recountError }}</p>
            </div>

            <div class="flex-shrink-0 flex items-center gap-2">
              <button
                type="button"
                @click="startQuickAdjust(source, 'subtract')"
                class="w-10 h-10 flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-xl font-medium hover:bg-gray-50 dark:hover:bg-gray-700 active:bg-gray-100 dark:active:bg-gray-600"
                title="Subtract packages"
              >
                −
              </button>
              <button
                type="button"
                @click="startQuickAdjust(source, 'add')"
                class="w-10 h-10 flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 text-xl font-medium hover:bg-gray-50 dark:hover:bg-gray-700 active:bg-gray-100 dark:active:bg-gray-600"
                title="Add packages"
              >
                +
              </button>
            </div>
          </div>

          <!-- Quick adjust stepper -->
          <div v-if="quickAdjustKey === source.id" class="mt-3 rounded-md bg-gray-50 dark:bg-gray-700/50 p-3 space-y-2">
            <p class="text-sm text-gray-700 dark:text-gray-300">
              {{ quickAdjustDirection === 'add' ? 'Add' : 'Subtract' }} how many packages of {{ fmt(source.package_size) }}?
            </p>
            <div class="flex items-center gap-3">
              <button type="button" @click="quickAdjustPackages = Math.max(0, quickAdjustPackages - 1)" class="w-10 h-10 flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">−</button>
              <input
                v-model.number="quickAdjustPackages"
                type="number"
                inputmode="decimal"
                min="0"
                step="1"
                class="w-16 text-center rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-lg"
              />
              <button type="button" @click="quickAdjustPackages = quickAdjustPackages + 1" class="w-10 h-10 flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">+</button>
              <span class="text-sm text-gray-500 dark:text-gray-400">= {{ fmt(quickAdjustPackages * source.package_size) }}</span>
            </div>
            <input
              v-model="quickAdjustNotes"
              type="text"
              placeholder="Detail (optional) -- e.g. GFS shipment, found spoiled"
              class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
            />
            <p v-if="quickAdjustError" class="text-xs text-red-600 dark:text-red-400">{{ quickAdjustError }}</p>
            <div class="flex justify-end gap-2 pt-1">
              <button type="button" @click="cancelQuickAdjust" :disabled="quickAdjustSaving" class="py-1.5 px-3 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40">
                Cancel
              </button>
              <button
                type="button"
                @click="saveQuickAdjust(source)"
                :disabled="quickAdjustSaving || quickAdjustPackages <= 0"
                :class="['py-1.5 px-4 rounded-md text-sm font-medium text-white disabled:opacity-40', quickAdjustDirection === 'add' ? 'bg-green-600 hover:bg-green-700' : 'bg-amber-600 hover:bg-amber-700']"
              >
                <span v-if="quickAdjustSaving">Saving...</span>
                <span v-else>Confirm {{ quickAdjustDirection === 'add' ? 'received' : 'correction' }}</span>
              </button>
            </div>
          </div>
        </li>
      </ul>

      <div v-if="!loadingSources" class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
        <button v-if="!addingSource" type="button" @click="startAddSource" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
          + Add a source
        </button>
        <div v-else class="space-y-2">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <input v-model="newSourceProvider" type="text" placeholder="Provider (e.g. GFS)" autofocus class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <input v-model="newSourceBrand" type="text" placeholder="Brand (optional)" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
          </div>
          <div class="flex items-center gap-2">
            <input v-model.number="newSourceSize" type="number" inputmode="decimal" min="0.01" step="0.01" :placeholder="`Package size (${baseUnitLabel})`" class="w-full sm:w-40 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
            <button type="button" @click="saveNewSource" :disabled="addSourceSaving || !newSourceProvider || !newSourceSize" class="py-1.5 px-4 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40">
              <span v-if="addSourceSaving">Saving...</span>
              <span v-else>Add</span>
            </button>
            <button type="button" @click="cancelAddSource" :disabled="addSourceSaving" class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Cancel</button>
          </div>
          <p v-if="addSourceError" class="text-xs text-red-600 dark:text-red-400">{{ addSourceError }}</p>
        </div>
      </div>

      <div class="mt-6 flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
        <button type="button" @click="$emit('close')" class="bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
          Close
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import { formatQuantity } from './formatWeight'

export interface StockIngredient {
  id: number
  name: string
  unit_type: 'g' | 'unit'
}

interface Source {
  id: number
  provider: string
  brand: string | null
  package_size: number
  quantity_on_hand: number
  packages: number
}

interface Props {
  ingredient: StockIngredient | null
}

const props = defineProps<Props>()

const emit = defineEmits<{ close: []; updated: [] }>()

// Display values auto-scale (g -> kg); the "Add a source" input below
// stays in the base unit since that's what actually gets typed there.
const fmt = (value: number) => formatQuantity(value, props.ingredient?.unit_type ?? 'g')
const baseUnitLabel = computed(() => (props.ingredient?.unit_type === 'unit' ? 'unit(s)' : 'g'))
const formatPackages = (n: number) => (Number.isInteger(n) ? String(n) : n.toFixed(2))

const sources = ref<Source[]>([])
const loadingSources = ref(false)
const totalOnHand = computed(() => sources.value.reduce((sum, s) => sum + s.quantity_on_hand, 0))

const fetchSources = async (ingredientId: number) => {
  loadingSources.value = true
  try {
    const { data } = await axios.get(route('admin.costing.inventory.sources', ingredientId))
    sources.value = data.sources
  } finally {
    loadingSources.value = false
  }
}

watch(
  () => props.ingredient,
  (ingredient) => {
    sources.value = []
    cancelRecount()
    cancelQuickAdjust()
    cancelAddSource()
    if (ingredient) {
      fetchSources(ingredient.id)
    }
  },
)

// Recount -- replaces a source's quantity outright, same inline-edit
// pattern as AvailablePricesModal's price/package-size editing.
const recountKey = ref<number | null>(null)
const recountPackages = ref<number | null>(null)
const recountSaving = ref(false)
const recountError = ref<string | null>(null)

const startRecount = (source: Source) => {
  recountKey.value = source.id
  recountPackages.value = source.packages
  recountError.value = null
}

const cancelRecount = () => {
  recountKey.value = null
  recountPackages.value = null
  recountError.value = null
}

const saveRecount = (source: Source) => {
  if (recountPackages.value === null || !props.ingredient) return
  const ingredientId = props.ingredient.id
  recountSaving.value = true
  recountError.value = null

  router.post(
    route('admin.costing.inventory.sources.adjust', [ingredientId, source.id]),
    { mode: 'recount', packages: recountPackages.value, direction: null, notes: null },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        cancelRecount()
        fetchSources(ingredientId)
        emit('updated')
      },
      onError: (errors) => {
        recountError.value = errors.packages ?? 'Could not save.'
      },
      onFinish: () => {
        recountSaving.value = false
      },
    },
  )
}

// Remove a source entirely -- backend blocks this while it still has stock
// (recount to 0 first, same guard the disabled state above enforces).
const deleteSource = (source: Source) => {
  if (!props.ingredient) return
  const label = source.brand ? `${source.provider} — ${source.brand}` : source.provider
  if (!confirm(`Remove "${label}" as a source for ${props.ingredient.name}?`)) return

  const ingredientId = props.ingredient.id
  router.delete(route('admin.costing.inventory.sources.destroy', [ingredientId, source.id]), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => fetchSources(ingredientId),
  })
}

// Quick adjust -- add or subtract N packages. Direction picks the reason
// server-side (add -> received, subtract -> correction), so there's no
// separate reason field to fill in here.
const quickAdjustKey = ref<number | null>(null)
const quickAdjustDirection = ref<'add' | 'subtract'>('add')
const quickAdjustPackages = ref(1)
const quickAdjustNotes = ref('')
const quickAdjustSaving = ref(false)
const quickAdjustError = ref<string | null>(null)

const startQuickAdjust = (source: Source, direction: 'add' | 'subtract') => {
  quickAdjustKey.value = source.id
  quickAdjustDirection.value = direction
  quickAdjustPackages.value = 1
  quickAdjustNotes.value = ''
  quickAdjustError.value = null
}

const cancelQuickAdjust = () => {
  quickAdjustKey.value = null
  quickAdjustPackages.value = 1
  quickAdjustNotes.value = ''
  quickAdjustError.value = null
}

const saveQuickAdjust = (source: Source) => {
  if (quickAdjustPackages.value <= 0 || !props.ingredient) return
  const ingredientId = props.ingredient.id
  quickAdjustSaving.value = true
  quickAdjustError.value = null

  router.post(
    route('admin.costing.inventory.sources.adjust', [ingredientId, source.id]),
    {
      mode: 'adjust',
      packages: quickAdjustPackages.value,
      direction: quickAdjustDirection.value,
      notes: quickAdjustNotes.value || null,
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        cancelQuickAdjust()
        fetchSources(ingredientId)
        emit('updated')
      },
      onError: (errors) => {
        quickAdjustError.value = errors.packages ?? 'Could not save.'
      },
      onFinish: () => {
        quickAdjustSaving.value = false
      },
    },
  )
}

// Add a new source -- reuses IngredientController::setPackageSize
// unchanged (it already upserts provider/brand/package_size); the new row
// starts at 0 on hand, stocked via the quick-adjust buttons afterward.
const addingSource = ref(false)
const newSourceProvider = ref('')
const newSourceBrand = ref('')
const newSourceSize = ref<number | null>(null)
const addSourceSaving = ref(false)
const addSourceError = ref<string | null>(null)

const startAddSource = () => {
  addingSource.value = true
  newSourceProvider.value = ''
  newSourceBrand.value = ''
  newSourceSize.value = null
  addSourceError.value = null
}

const cancelAddSource = () => {
  addingSource.value = false
  addSourceError.value = null
}

const saveNewSource = () => {
  if (!newSourceProvider.value || !newSourceSize.value || !props.ingredient) return
  const ingredientId = props.ingredient.id
  addSourceSaving.value = true
  addSourceError.value = null

  router.post(
    route('admin.costing.ingredients.set-package-size', ingredientId),
    { provider: newSourceProvider.value, brand: newSourceBrand.value || null, package_size: newSourceSize.value },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        cancelAddSource()
        fetchSources(ingredientId)
      },
      onError: (errors) => {
        addSourceError.value = errors.package_size ?? errors.provider ?? 'Could not save.'
      },
      onFinish: () => {
        addSourceSaving.value = false
      },
    },
  )
}
</script>
