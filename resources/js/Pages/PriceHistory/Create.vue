<template>
  <div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Log a Price</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Log every wholesaler you check, even if you didn't buy.</p>
          </div>
          <Link :href="route('admin.costing.price-history.index')" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; Back</Link>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-6">
          <FormErrorSummary :errors="form.errors" />

          <div v-if="props.clone" class="rounded-md bg-indigo-50 dark:bg-indigo-900/20 p-4">
            <p class="text-sm text-indigo-800 dark:text-indigo-200">Cloned from a previous entry -- update the price and date, then save.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ingredient *</label>
            <select v-model="form.ingredient_id" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
              <option value="">Select ingredient</option>
              <option v-for="ingredient in ingredients" :key="ingredient.id" :value="ingredient.id">{{ ingredient.name }}</option>
            </select>
            <p v-if="form.errors.ingredient_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.ingredient_id }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date Checked</label>
              <input v-model="form.purchased_at" type="date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source *</label>
              <div v-if="!addingSource">
                <select v-model="form.package_size_id" required :disabled="!form.ingredient_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50">
                  <option value="">{{ form.ingredient_id ? 'Select a source' : 'Select an ingredient first' }}</option>
                  <option v-for="source in sources" :key="source.id" :value="source.id">{{ sourceLabel(source) }}</option>
                </select>
                <button type="button" @click="startAddSource" :disabled="!form.ingredient_id" class="mt-1 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 disabled:opacity-40 disabled:cursor-not-allowed">+ Add new source</button>
                <p v-if="form.errors.package_size_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.package_size_id }}</p>
              </div>
              <div v-else class="mt-1 space-y-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <input v-model="newSourceProvider" type="text" placeholder="Provider (e.g. GFS)" autofocus class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                  <input v-model="newSourceBrand" type="text" placeholder="Brand (optional)" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                </div>
                <div class="flex flex-wrap items-center gap-2">
                  <input v-model.number="newSourceSize" type="number" min="0.01" step="0.01" placeholder="Package size" class="w-full sm:w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                  <input v-model.number="newSourceUnitsPerCase" type="number" min="1" step="1" placeholder="Units/case" title="Leave at 1 if not sold by the case" class="w-full sm:w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                  <button type="button" @click="saveNewSource" :disabled="addSourceSaving || !newSourceProvider || !newSourceSize" class="py-1.5 px-4 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40">
                    <span v-if="addSourceSaving">Saving...</span>
                    <span v-else>Add</span>
                  </button>
                  <button type="button" @click="cancelAddSource" :disabled="addSourceSaving" class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">Cancel</button>
                </div>
                <p v-if="addSourceError" class="text-xs text-red-600 dark:text-red-400">{{ addSourceError }}</p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-if="isGramBased">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Weight{{ isCase ? ' per Each' : '' }}</label>
              <div class="mt-1 flex gap-2">
                <input v-model.number="weightValue" type="number" min="0" step="0.01" class="flex-1 min-w-0 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. 2.27" />
                <select v-model="weightUnit" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                  <option value="kg">kg</option>
                  <option value="g">g</option>
                </select>
              </div>

              <label class="mt-2 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                <input v-model="isCase" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
                This was a case of multiple identical packages
              </label>
              <div v-if="isCase" class="mt-1 flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">Eaches per case</span>
                <input v-model.number="eachesPerCase" type="number" min="1" step="1" class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-xs" />
              </div>

              <p v-if="form.qty !== null" class="mt-1 text-xs text-gray-500 dark:text-gray-400">= {{ form.qty }} g total logged</p>
            </div>
            <div v-else>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Qty (units)</label>
              <input v-model.number="form.qty" type="number" min="0" step="1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. 24" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total Price ($)</label>
              <input v-model.number="form.total_price" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">$/kg (or $/unit) is calculated automatically from the weight/qty above and this price.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SKU</label>
              <input v-model="form.sku" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
            <input v-model="form.notes" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
          </div>

          <div class="flex items-center justify-end pt-6 border-t border-gray-200 dark:border-gray-700">
            <Link :href="route('admin.costing.price-history.index')" class="bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">Cancel</Link>
            <button type="submit" :disabled="form.processing" class="ml-3 bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
              <span v-if="form.processing">Saving...</span>
              <span v-else>Log Price</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePersistedForm } from '@/composables/usePersistedForm'
import { useWeightEntry } from '../Shared/useWeightEntry'
import { useIngredientSources, sourceLabel } from '../Shared/useIngredientSources'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'

defineOptions({ layout: AdminLayout })

interface CloneSource {
  ingredient_id: number
  package_size_id: number
  qty: number | null
  total_price: number | null
  sku: string | null
  notes: string | null
}

interface Props {
  ingredients: Array<{ id: number; name: string; unit_type: 'g' | 'unit' }>
  clone: CloneSource | null
  preselectIngredientId: number | null
}

const props = defineProps<Props>()

interface FormData {
  ingredient_id: number | ''
  package_size_id: number | ''
  purchased_at: string
  qty: number | null
  total_price: number | null
  sku: string
  notes: string
}

// Cloning carries over everything except the date, which resets to today
// since it's a new price check. preselectIngredientId (arriving via
// ?ingredient=) covers the other entry point: "Log a new price" from the
// Ingredients page for an ingredient that has none logged yet.
const initialData: FormData = {
  ingredient_id: props.clone?.ingredient_id ?? props.preselectIngredientId ?? '',
  package_size_id: props.clone?.package_size_id ?? '',
  purchased_at: new Date().toISOString().slice(0, 10),
  qty: props.clone?.qty ?? null,
  total_price: props.clone?.total_price ?? null,
  sku: props.clone?.sku ?? '',
  notes: props.clone?.notes ?? '',
}

const form = usePersistedForm<FormData>(initialData, {
  key: 'costing-price-history-create',
  initialData,
})

const selectedIngredient = computed(() => props.ingredients.find((i) => i.id === form.ingredient_id) ?? null)
const isGramBased = computed(() => selectedIngredient.value?.unit_type !== 'unit')

const { sources, addSource, addSourceSaving, addSourceError } = useIngredientSources(computed(() => form.ingredient_id))

// Switching to a different ingredient invalidates whatever source was
// picked for the previous one -- but only on a real change, not on mount,
// so a cloned/preselected package_size_id survives the initial load.
watch(
  () => form.ingredient_id,
  () => {
    form.package_size_id = ''
  },
)

const addingSource = ref(false)
const newSourceProvider = ref('')
const newSourceBrand = ref('')
const newSourceSize = ref<number | null>(null)
const newSourceUnitsPerCase = ref<number>(1)

const startAddSource = () => {
  addingSource.value = true
  newSourceProvider.value = ''
  newSourceBrand.value = ''
  newSourceSize.value = null
  newSourceUnitsPerCase.value = 1
}

const cancelAddSource = () => {
  addingSource.value = false
}

const saveNewSource = async () => {
  if (!newSourceProvider.value || !newSourceSize.value || !form.ingredient_id) return
  const id = await addSource(form.ingredient_id, newSourceProvider.value, newSourceBrand.value || null, newSourceSize.value, newSourceUnitsPerCase.value || 1)
  if (id !== null) {
    form.package_size_id = id
    addingSource.value = false
  }
}

// Weight input for gram-based ingredients -- kg/g toggle plus an optional
// case breakdown, resolving to a single grams total stored in form.qty.
// Cloning a gram-based entry seeds it with the cloned total, which is
// stored (and so must be displayed) in grams -- defaulting the unit
// selector to 'kg' in that case would silently misrepresent the value
// (6000 g shown as "6000 kg"). Only default to kg when starting blank.
const { weightValue, weightUnit, isCase, eachesPerCase, totalGrams } = useWeightEntry(
  isGramBased.value ? (props.clone?.qty ?? null) : null,
  props.clone?.qty != null ? 'g' : 'kg',
)

watch(totalGrams, (total) => {
  if (isGramBased.value && total !== null) {
    form.qty = total
  }
})

const submit = () => {
  form.post(route('admin.costing.price-history.store'))
}
</script>
