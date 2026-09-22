<template>
  <!-- Content only -- no fixed positioning, backdrop, or card chrome of
       its own. This renders inside CostingModuleNav's #drawer slot, which
       owns all of that (so the sheet grows directly out of the bottom
       nav bar as one unit, rather than floating as a separate card above
       it). h-full so the header stays pinned and the body fills/scrolls
       within whatever height CostingModuleNav's drawer container gives it. -->
  <template v-if="ingredient">
    <div class="shrink-0 flex items-start justify-between px-4 pt-4 pb-3 border-b border-gray-200 dark:border-gray-700">
      <div>
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ ingredient.name }}</h2>
        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
          On Hand: <span class="font-semibold">{{ fmt(totalOnHand) }}</span>
        </p>
      </div>
      <button
        type="button"
        @click="$emit('close')"
        aria-label="Close"
        class="tap-target-touch shrink-0 -mr-2 -mt-1 inline-flex items-center justify-center text-gray-400 dark:text-gray-500"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
    </div>

    <div class="flex-1 overflow-y-auto px-4 py-4">
      <div v-if="loadingSources" class="text-sm text-gray-500 dark:text-gray-400">Loading...</div>
      <p v-else-if="sources.length === 0" class="text-sm text-gray-500 dark:text-gray-400">No sources yet -- add one below.</p>
      <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
        <li v-for="source in sources" :key="source.id" class="py-3">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <div class="min-w-0">
                <p class="text-sm font-bold text-gray-900 dark:text-white truncate">{{ source.provider }}</p>
                <p v-if="source.brand" class="text-xs italic text-gray-500 dark:text-gray-400 truncate">{{ source.brand }}</p>
              </div>

              <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ formatPackages(source.packages) }} × {{ fmt(source.package_size) }} = {{ fmt(source.quantity_on_hand) }}
                <span v-if="source.units_per_case > 1" class="text-gray-400 dark:text-gray-500">(case of {{ source.units_per_case }})</span>
              </div>
              <p v-if="recountKey === source.id && recountError" class="mt-1 text-xs text-red-600 dark:text-red-400">{{ recountError }}</p>
            </div>

            <!-- Recount, subtract, and add all live in this one group now
                 (previously Recount was a separate text link inline with
                 the quantity line, off to the left of the +/- circles) --
                 one place for every way to change this source's count,
                 each an icon rather than mixing a text link with symbol
                 buttons. Swaps to the recount input + save/cancel once
                 active, in the same spot, rather than opening it
                 elsewhere. -->
            <div v-if="recountKey !== source.id" class="flex-shrink-0 flex items-center gap-2">
              <button
                type="button"
                @click="startRecount(source)"
                class="tap-target-touch w-11 h-11 flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-indigo-600 dark:text-indigo-400 active:bg-gray-100 dark:active:bg-gray-600"
                aria-label="Recount"
                title="Recount"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
              </button>
              <button
                type="button"
                @click="startQuickAdjust(source, 'subtract')"
                class="tap-target-touch w-11 h-11 flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 active:bg-gray-100 dark:active:bg-gray-600"
                aria-label="Subtract packages"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 12h16" /></svg>
              </button>
              <button
                type="button"
                @click="startQuickAdjust(source, 'add')"
                class="tap-target-touch w-11 h-11 flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 active:bg-gray-100 dark:active:bg-gray-600"
                aria-label="Add packages"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
              </button>
            </div>
            <div v-else class="flex-shrink-0 flex items-center gap-1.5">
              <input
                v-model.number="recountPackages"
                type="number"
                inputmode="decimal"
                min="0"
                step="0.01"
                autofocus
                :disabled="recountSaving"
                class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base"
                @keyup.enter="saveRecount(source)"
                @keyup.esc="cancelRecount"
              />
              <IconButton type="button" @click="saveRecount(source)" :disabled="recountSaving" class="text-green-600 dark:text-green-400 disabled:opacity-40" label="Save">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
              </IconButton>
              <IconButton type="button" @click="cancelRecount" :disabled="recountSaving" class="text-gray-400 dark:text-gray-500 disabled:opacity-40" label="Cancel">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </IconButton>
            </div>
          </div>

          <div v-if="quickAdjustKey === source.id" class="mt-3 rounded-md bg-gray-50 dark:bg-gray-700/50 p-3 space-y-2">
            <p class="text-sm text-gray-700 dark:text-gray-300">
              {{ quickAdjustDirection === 'add' ? 'Add' : 'Subtract' }} how many packages of {{ fmt(source.package_size) }}?
            </p>
            <div class="flex items-center gap-3">
              <button type="button" @click="quickAdjustPackages = Math.max(0, quickAdjustPackages - 1)" class="tap-target-touch w-11 h-11 flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-lg dark:text-gray-300">−</button>
              <input
                v-model.number="quickAdjustPackages"
                type="number"
                inputmode="decimal"
                min="0"
                step="1"
                class="w-16 text-center rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-lg"
              />
              <button type="button" @click="quickAdjustPackages = quickAdjustPackages + 1" class="tap-target-touch w-11 h-11 flex items-center justify-center rounded-full border border-gray-300 dark:border-gray-600 text-lg dark:text-gray-300">+</button>
              <span class="text-sm text-gray-500 dark:text-gray-400">= {{ fmt(quickAdjustPackages * source.package_size) }}</span>
            </div>
            <input
              v-model="quickAdjustNotes"
              type="text"
              placeholder="Detail (optional) -- e.g. GFS shipment, found spoiled"
              class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base"
            />
            <p v-if="quickAdjustError" class="text-xs text-red-600 dark:text-red-400">{{ quickAdjustError }}</p>
            <div class="flex justify-end gap-2 pt-1">
              <button type="button" @click="cancelQuickAdjust" :disabled="quickAdjustSaving" class="tap-target-touch py-1.5 px-3 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200 disabled:opacity-40">
                Cancel
              </button>
              <button
                type="button"
                @click="saveQuickAdjust(source)"
                :disabled="quickAdjustSaving || quickAdjustPackages <= 0"
                :class="['tap-target-touch py-1.5 px-4 rounded-md text-sm font-medium text-white disabled:opacity-40', quickAdjustDirection === 'add' ? 'bg-green-600' : 'bg-amber-600']"
              >
                <span v-if="quickAdjustSaving">Saving...</span>
                <span v-else>Confirm {{ quickAdjustDirection === 'add' ? 'received' : 'correction' }}</span>
              </button>
            </div>
          </div>
        </li>
      </ul>

      <div v-if="!loadingSources" class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
        <button v-if="!addingSource" type="button" @click="startAddSource" class="tap-target-touch text-sm font-medium text-indigo-600 dark:text-indigo-400">
          + Add a source
        </button>
        <div v-else class="space-y-2">
          <div class="grid grid-cols-1 gap-2">
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Provider</label>
              <input v-model="newSourceProvider" type="text" placeholder="e.g. GFS" autofocus class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Brand</label>
              <input v-model="newSourceBrand" type="text" placeholder="Optional" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Unit Size ({{ baseUnitLabel }})</label>
              <input v-model.number="newSourceSize" type="number" inputmode="decimal" min="0.01" step="0.01" title="The size of ONE individual package -- e.g. 1 lid. Never the case total, even if sold by the case." class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Total Units</label>
              <input v-model.number="newSourceUnitsPerCase" type="number" inputmode="decimal" min="1" step="1" title="How many individual packages come in one case -- purchasing info only, doesn't change how stock is counted. Leave at 1 if not sold by the case." class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base" />
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" @click="saveNewSource" :disabled="addSourceSaving || !newSourceProvider || !newSourceSize" class="tap-target-touch py-1.5 px-4 rounded-md text-sm font-medium text-white bg-indigo-600 disabled:opacity-40">
              <span v-if="addSourceSaving">Saving...</span>
              <span v-else>Add</span>
            </button>
            <button type="button" @click="cancelAddSource" :disabled="addSourceSaving" class="tap-target-touch text-sm font-medium text-gray-500 dark:text-gray-400">Cancel</button>
          </div>
          <p v-if="addSourceError" class="text-xs text-red-600 dark:text-red-400">{{ addSourceError }}</p>
        </div>
      </div>

      <!-- Expandable history -- fetched on first expand, not on drawer
           open, since most opens are just a quick recount/adjust and never
           look at it. -->
      <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
        <button
          type="button"
          @click="toggleHistory"
          class="tap-target-touch text-sm font-medium text-indigo-600 dark:text-indigo-400 inline-flex items-center gap-1"
        >
          <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="{ 'rotate-90': historyOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
          {{ historyOpen ? 'Hide' : 'Show' }} Adjustment History
        </button>

        <div v-if="historyOpen" class="mt-3">
          <div v-if="historyLoading" class="text-sm text-gray-500 dark:text-gray-400">Loading...</div>
          <p v-else-if="history.length === 0" class="text-sm text-gray-500 dark:text-gray-400">No adjustments recorded yet.</p>
          <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
            <li v-for="entry in history" :key="entry.id" class="py-2">
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ entry.created_at }}</span>
                <span class="text-sm font-medium" :class="entry.delta >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                  {{ entry.delta >= 0 ? '+' : '' }}{{ fmt(entry.delta) }}
                </span>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ entry.source ?? 'Unknown source' }} — {{ entry.reason }} — by {{ entry.user_name ?? 'System' }}
                <template v-if="entry.production_run_name"> ({{ entry.production_run_name }})</template>
              </p>
              <p v-if="entry.notes" class="text-xs text-gray-400 dark:text-gray-500 italic">{{ entry.notes }}</p>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import { formatQuantity } from './formatWeight'
import IconButton from '@/Components/IconButton.vue'
import type { StockIngredient } from './StockAdjustModal.vue'

interface Source {
  id: number
  provider: string
  brand: string | null
  package_size: number
  units_per_case: number
  quantity_on_hand: number
  packages: number
}

interface HistoryEntry {
  id: number
  source: string | null
  reason: string
  delta: number
  on_hand_before: number
  on_hand_after: number
  notes: string | null
  user_name: string | null
  production_run_name: string | null
  created_at: string
}

interface Props {
  ingredient: StockIngredient | null
}

const props = defineProps<Props>()

const emit = defineEmits<{ close: [] }>()

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

// History and its own "opened at least once" flag reset with every
// ingredient change too, same as sources -- reopening the drawer for the
// same ingredient later re-fetches rather than showing stale data.
const history = ref<HistoryEntry[]>([])
const historyOpen = ref(false)
const historyLoading = ref(false)
const historyLoaded = ref(false)

const toggleHistory = () => {
  historyOpen.value = !historyOpen.value
  if (historyOpen.value && !historyLoaded.value && props.ingredient) {
    fetchHistory(props.ingredient.id)
  }
}

const fetchHistory = async (ingredientId: number) => {
  historyLoading.value = true
  try {
    const { data } = await axios.get(route('admin.costing.inventory.history', ingredientId))
    history.value = data.adjustments
    historyLoaded.value = true
  } finally {
    historyLoading.value = false
  }
}

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
        if (historyLoaded.value) fetchHistory(ingredientId)
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
        if (historyLoaded.value) fetchHistory(ingredientId)
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

const addingSource = ref(false)
const newSourceProvider = ref('')
const newSourceBrand = ref('')
const newSourceSize = ref<number | null>(null)
const newSourceUnitsPerCase = ref<number>(1)
const addSourceSaving = ref(false)
const addSourceError = ref<string | null>(null)

const startAddSource = () => {
  addingSource.value = true
  newSourceProvider.value = ''
  newSourceBrand.value = ''
  newSourceSize.value = null
  newSourceUnitsPerCase.value = 1
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
    { provider: newSourceProvider.value, brand: newSourceBrand.value || null, package_size: newSourceSize.value, units_per_case: newSourceUnitsPerCase.value || 1 },
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

// immediate: true -- unlike StockAdjustModal (always mounted, toggling
// visibility via CSS, so this watch only ever needs to react to the
// null-to-ingredient transition), this component is mounted fresh each
// time CostingModuleNav's #drawer v-if opens, already carrying its
// ingredient prop from the start. A non-immediate watch never sees that
// as a "change" and fetchSources() would never run on first open.
//
// Declared last, after cancelRecount/cancelQuickAdjust/cancelAddSource --
// immediate:true runs this synchronously as watch() is called, and those
// three are const function declarations below the TDZ boundary at any
// earlier point in the script.
watch(
  () => props.ingredient,
  (ingredient) => {
    sources.value = []
    history.value = []
    historyOpen.value = false
    historyLoaded.value = false
    cancelRecount()
    cancelQuickAdjust()
    cancelAddSource()
    if (ingredient) {
      fetchSources(ingredient.id)
    }
  },
  { immediate: true },
)
</script>
