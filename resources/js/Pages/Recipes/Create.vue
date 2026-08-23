<template>
  <div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Add Recipe</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Add each ingredient this flavour uses, with grams (or units) per jar.</p>
          </div>
          <Link :href="route('admin.costing.recipes.index')" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; Back</Link>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-6">
          <FormErrorSummary :errors="form.errors" />

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Flavour Name *</label>
            <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Sriracha Maple Bacon" />
            <p v-if="isDuplicateName" class="mt-1 text-sm text-amber-600 dark:text-amber-500">A recipe named "{{ form.name.trim() }}" already exists.</p>
            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
            <textarea v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Finished product</label>
            <select v-model.number="form.product_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
              <option :value="null">Not linked to a storefront product</option>
              <option v-for="product in props.products" :key="product.id" :value="product.id">{{ product.title }}</option>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">When set, completing a production run for this recipe credits the units produced to this product's storefront stock.</p>
            <p v-if="form.errors.product_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.product_id }}</p>
          </div>

          <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Ingredients (per jar)</h3>
            <div v-if="form.ingredients.length" class="border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
              <div v-for="(row, index) in form.ingredients" :key="index" class="flex flex-wrap items-center gap-3 px-4 py-2">
                <select v-model.number="row.ingredient_id" required class="flex-1 min-w-[10rem] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                  <option :value="null" disabled>Select an ingredient&hellip;</option>
                  <option v-for="opt in availableIngredients(form.ingredients, row.ingredient_id)" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
                </select>
                <input v-model.number="row.quantity_per_jar" type="number" min="0" step="0.01" required class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <span class="text-xs text-gray-500 dark:text-gray-400 w-10">{{ ingredientUnit(row.ingredient_id) }}</span>
                <button type="button" @click="form.ingredients.splice(index, 1)" class="text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
            </div>
            <button type="button" @click="addRow(form.ingredients)" :disabled="form.ingredients.length >= props.ingredients.length" class="mt-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 disabled:opacity-40">
              + Add Ingredient
            </button>
          </div>

          <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Byproducts (per jar)</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Free, always assumed sufficient -- not costed or tracked in inventory, just documents the recipe.</p>
            <div v-if="form.byproducts.length" class="border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
              <div v-for="(row, index) in form.byproducts" :key="index" class="flex flex-wrap items-center gap-3 px-4 py-2">
                <select v-model.number="row.ingredient_id" required class="flex-1 min-w-[10rem] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                  <option :value="null" disabled>Select a byproduct&hellip;</option>
                  <option v-for="opt in availableIngredients(form.byproducts, row.ingredient_id, byproductIngredients)" :key="opt.id" :value="opt.id">{{ opt.name }} — {{ opt.byproduct_name }}</option>
                </select>
                <input v-model.number="row.quantity_per_jar" type="number" min="0" step="0.01" required class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <span class="text-xs text-gray-500 dark:text-gray-400 w-10">{{ ingredientUnit(row.ingredient_id) }}</span>
                <button type="button" @click="form.byproducts.splice(index, 1)" class="text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
            </div>
            <button type="button" @click="addRow(form.byproducts, byproductIngredients)" :disabled="byproductIngredients.length === 0 || form.byproducts.length >= byproductIngredients.length" class="mt-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 disabled:opacity-40">
              + Add Byproduct
            </button>
          </div>

          <div class="flex items-center justify-end pt-6 border-t border-gray-200 dark:border-gray-700">
            <Link :href="route('admin.costing.recipes.index')" class="bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">Cancel</Link>
            <button type="submit" :disabled="form.processing" class="ml-3 bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
              <span v-if="form.processing">Creating...</span>
              <span v-else>Create Recipe</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePersistedForm } from '@/composables/usePersistedForm'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'

defineOptions({ layout: AdminLayout })

interface IngredientOption {
  id: number
  name: string
  unit_type: 'g' | 'unit'
  byproduct_name: string | null
}

interface ProductOption {
  id: number
  title: string
}

interface Props {
  ingredients: IngredientOption[]
  products: ProductOption[]
  existingRecipeNames: string[]
}

const props = defineProps<Props>()

type Row = { ingredient_id: number | null; quantity_per_jar: number | null }

interface FormData {
  name: string
  notes: string
  product_id: number | null
  ingredients: Row[]
  byproducts: Row[]
}

const byproductIngredients = props.ingredients.filter((i) => i.byproduct_name)

const form = usePersistedForm<FormData>({
  name: '',
  notes: '',
  product_id: null,
  ingredients: [],
  byproducts: [],
}, { key: 'costing-recipe-create' })

const isDuplicateName = computed(() => {
  const name = form.name.trim().toLowerCase()
  return name.length > 0 && props.existingRecipeNames.some((existing) => existing.trim().toLowerCase() === name)
})

const ingredientUnit = (id: number | null) => (props.ingredients.find((i) => i.id === id)?.unit_type === 'unit' ? 'unit' : 'g')

// Ingredients already picked in another row aren't offered again.
const availableIngredients = (rows: Row[], currentValue: number | null, pool: IngredientOption[] = props.ingredients) => {
  const chosen = new Set(rows.map((r) => r.ingredient_id).filter((id) => id !== null && id !== currentValue))
  return pool.filter((i) => !chosen.has(i.id))
}

const addRow = (rows: Row[], pool: IngredientOption[] = props.ingredients) => {
  if (rows.length < pool.length) {
    rows.push({ ingredient_id: null, quantity_per_jar: null })
  }
}

const submit = () => {
  if (isDuplicateName.value && !confirm(`A recipe named "${form.name.trim()}" already exists. Continue anyway?`)) {
    return
  }

  // Mutate the form's own reactive data (not .transform(), which would
  // return the raw underlying Inertia form and bypass usePersistedForm's
  // wrapped .post() -- specifically its clear-localStorage-on-success
  // logic -- since transform() doesn't return the proxy).
  form.ingredients = form.ingredients.filter((r) => r.ingredient_id !== null)
  form.byproducts = form.byproducts.filter((r) => r.ingredient_id !== null)
  form.post(route('admin.costing.recipes.store'))
}
</script>
