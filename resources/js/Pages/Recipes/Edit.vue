<template>
  <div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Edit Recipe: {{ recipe.name }}</h1>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" />
          </div>
          <Link :href="route('admin.costing.recipes.index')" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; Back</Link>
        </div>

        <form @submit.prevent class="p-6 space-y-6">
          <FormErrorSummary :errors="form.errors" />

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Flavour Name *</label>
            <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
            <p v-if="isDuplicateName" class="mt-1 text-sm text-amber-600 dark:text-amber-500">A recipe named "{{ form.name.trim() }}" already exists.</p>
            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.name }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
            <textarea v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
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
                <button
                  v-if="row.ingredient_id !== null"
                  type="button"
                  @click="openPricesModal(row.ingredient_id)"
                  class="text-xs font-medium whitespace-nowrap"
                  :class="priceIndicatorClass(row.ingredient_id)"
                >
                  {{ priceIndicatorLabel(row.ingredient_id) }}
                </button>
                <button type="button" @click="form.ingredients.splice(index, 1)" class="text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
              </div>
            </div>
            <button type="button" @click="addRow(form.ingredients)" :disabled="form.ingredients.length >= props.ingredients.length" class="mt-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 disabled:opacity-40">
              + Add Ingredient
            </button>

            <div v-if="form.ingredients.length" class="mt-4 rounded-md bg-gray-50 dark:bg-gray-700/50 px-4 py-3">
              <p class="text-sm font-medium text-gray-900 dark:text-white">Estimated cost per jar: ${{ costPerJar.total.toFixed(2) }}</p>
              <p v-if="costPerJar.anyStale || costPerJar.anyMissing" class="mt-1 text-xs text-amber-600 dark:text-amber-500">
                Estimate only -- {{ costPerJar.anyMissing ? 'one or more ingredients have no logged price' : 'one or more ingredients are using a price that needs updating' }}. Click a price above to fix it.
              </p>
            </div>
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

          <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="destroy" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete Recipe</button>
            <Link :href="route('admin.costing.recipes.index')" class="bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">Cancel</Link>
          </div>
        </form>
      </div>

      <AvailablePricesModal :ingredient="pricesIngredient" @close="pricesIngredient = null" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { usePersistedForm } from '@/composables/usePersistedForm'
import AvailablePricesModal, { type PricesIngredient } from '../Shared/AvailablePricesModal.vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import SaveIndicator from '@/Components/Admin/SaveIndicator.vue'

defineOptions({ layout: AdminLayout })

interface IngredientOption {
  id: number
  name: string
  unit_type: 'g' | 'unit'
  byproduct_name: string | null
  status: 'ok' | 'no_price_this_week'
  weekly_price: number | null
  effective_price: number | null
  stale_price: number | null
  stale_effective_price: number | null
}

interface Recipe {
  id: number
  name: string
  notes: string | null
  ingredients: Array<{ ingredient_id: number; quantity_per_jar: number }>
  byproducts: Array<{ ingredient_id: number; quantity_per_jar: number }>
}

interface Props {
  recipe: Recipe
  ingredients: IngredientOption[]
  existingRecipeNames: string[]
}

const props = defineProps<Props>()

type Row = { ingredient_id: number | null; quantity_per_jar: number | null }

interface FormData {
  name: string
  notes: string
  ingredients: Row[]
  byproducts: Row[]
}

const byproductIngredients = props.ingredients.filter((i) => i.byproduct_name)

const initialData: FormData = {
  name: props.recipe.name,
  notes: props.recipe.notes ?? '',
  ingredients: props.recipe.ingredients.map((row) => ({ ...row })),
  byproducts: props.recipe.byproducts.map((row) => ({ ...row })),
}

const form = usePersistedForm<FormData>(initialData, {
  key: `costing-recipe-edit-${props.recipe.id}`,
  initialData,
  autosave: {
    url: route('admin.costing.recipes.update', props.recipe.id),
    requiredFields: ['name'],
  },
})

// Drops rows where an ingredient hasn't been picked yet (e.g. right after
// "+ Add Ingredient", before selecting from the dropdown) -- registered as
// a persistent transform rather than done inline in submit() so it applies
// to every save, including autosave's background PUTs, not just an
// explicit click. The displayed form.ingredients/byproducts arrays
// themselves are untouched -- a half-filled row stays visible to keep
// editing, only the submitted payload is filtered.
form.transform((data) => ({
  ...data,
  ingredients: data.ingredients.filter((row) => row.ingredient_id !== null),
  byproducts: data.byproducts.filter((row) => row.ingredient_id !== null),
}))

const isDuplicateName = computed(() => {
  const name = form.name.trim().toLowerCase()
  return name.length > 0 && props.existingRecipeNames.some((existing) => existing.trim().toLowerCase() === name)
})

const findIngredient = (id: number | null) => props.ingredients.find((i) => i.id === id) ?? null

const ingredientUnit = (id: number | null) => (findIngredient(id)?.unit_type === 'unit' ? 'unit' : 'g')

// Price indicator next to each ingredient row -- click through to the same
// Available Prices modal used on the Ingredients page, without leaving the
// recipe.
const pricesIngredient = ref<PricesIngredient | null>(null)

const openPricesModal = (ingredientId: number) => {
  const ingredient = findIngredient(ingredientId)
  if (!ingredient) return
  pricesIngredient.value = { id: ingredient.id, name: ingredient.name, unit_type: ingredient.unit_type }
}

const priceIndicatorLabel = (id: number | null): string => {
  const ingredient = findIngredient(id)
  if (!ingredient) return ''
  const suffix = ingredient.unit_type === 'unit' ? '/unit' : '/kg'
  if (ingredient.status === 'ok') return `$${Number(ingredient.weekly_price).toFixed(2)}${suffix}`
  if (ingredient.stale_price !== null) return `$${Number(ingredient.stale_price).toFixed(2)}${suffix} ⚠`
  return 'no price'
}

const priceIndicatorClass = (id: number | null): string => {
  const ingredient = findIngredient(id)
  if (!ingredient) return ''
  if (ingredient.status === 'ok') return 'text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400'
  if (ingredient.stale_price !== null) return 'text-amber-600 hover:text-amber-800 dark:text-amber-500'
  return 'text-gray-400 dark:text-gray-500 italic hover:text-indigo-600 dark:hover:text-indigo-400'
}

// Estimated cost per jar -- sum of each main ingredient's effective $
// contribution (falling back to the stale figure when there's no fresh
// price), same math CalculateProductionPlan uses per-ingredient.
const costPerJar = computed(() => {
  let total = 0
  let anyStale = false
  let anyMissing = false

  for (const row of form.ingredients) {
    if (row.ingredient_id === null || !row.quantity_per_jar) continue
    const ingredient = findIngredient(row.ingredient_id)
    if (!ingredient) continue

    const effectivePrice = ingredient.status === 'ok' ? ingredient.effective_price : ingredient.stale_effective_price
    if (effectivePrice === null) {
      anyMissing = true
      continue
    }
    if (ingredient.status !== 'ok') anyStale = true

    total += ingredient.unit_type === 'unit'
      ? row.quantity_per_jar * effectivePrice
      : (row.quantity_per_jar * effectivePrice) / 1000
  }

  return { total, anyStale, anyMissing }
})

const availableIngredients = (rows: Row[], currentValue: number | null, pool: IngredientOption[] = props.ingredients) => {
  const chosen = new Set(rows.map((r) => r.ingredient_id).filter((id) => id !== null && id !== currentValue))
  return pool.filter((i) => !chosen.has(i.id))
}

const addRow = (rows: Row[], pool: IngredientOption[] = props.ingredients) => {
  if (rows.length < pool.length) {
    rows.push({ ingredient_id: null, quantity_per_jar: null })
  }
}

const destroy = () => {
  if (confirm(`Delete "${props.recipe.name}"? This also removes it from any production runs.`)) {
    router.delete(route('admin.costing.recipes.destroy', props.recipe.id))
  }
}
</script>
