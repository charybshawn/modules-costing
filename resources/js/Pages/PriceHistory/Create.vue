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
            <p v-if="form.errors.ingredient_id" class="mt-1 text-sm text-red-600">{{ form.errors.ingredient_id }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date Checked</label>
              <input v-model="form.purchased_at" type="date" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Wholesaler *</label>
              <input v-model="form.provider" type="text" required list="wholesaler-options" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. GFS, No Frills, Wholesale Club" />
              <datalist id="wholesaler-options">
                <option v-for="p in providers" :key="p" :value="p" />
              </datalist>
              <p v-if="form.errors.provider" class="mt-1 text-sm text-red-600">{{ form.errors.provider }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Brand</label>
              <input v-model="form.brand" type="text" list="brand-options" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Kraft, Great Value" />
              <datalist id="brand-options">
                <option v-for="b in brands" :key="b" :value="b" />
              </datalist>
              <p v-if="form.errors.brand" class="mt-1 text-sm text-red-600">{{ form.errors.brand }}</p>
            </div>
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
import { computed, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePersistedForm } from '@/composables/usePersistedForm'
import { useWeightEntry } from '../Shared/useWeightEntry'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'

defineOptions({ layout: AdminLayout })

interface CloneSource {
  ingredient_id: number
  provider: string
  brand: string | null
  qty: number | null
  total_price: number | null
  sku: string | null
  notes: string | null
}

interface Props {
  ingredients: Array<{ id: number; name: string; unit_type: 'g' | 'unit' }>
  providers: string[]
  brands: string[]
  clone: CloneSource | null
  preselectIngredientId: number | null
}

const props = defineProps<Props>()

interface FormData {
  ingredient_id: number | ''
  purchased_at: string
  provider: string
  brand: string
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
  purchased_at: new Date().toISOString().slice(0, 10),
  provider: props.clone?.provider ?? '',
  brand: props.clone?.brand ?? '',
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
