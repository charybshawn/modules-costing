<template>
  <div class="pb-36 md:pt-6 md:pb-6">
    <div>
      <CostingModuleNav />
      <AdminMobileHeader title="Recipes — Grid View" :href="route('admin.costing.recipes.index')" />

      <div class="hidden md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Recipes — Grid View</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Grams (or units) per jar, per flavour. Read-only — edit a recipe to change its weights.</p>
        </div>
        <Link :href="route('admin.costing.recipes.index')" class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
          List View
        </Link>
      </div>

      <div class="md:hidden mb-6">
        <Link :href="route('admin.costing.recipes.index')" class="tap-target-touch w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700">
          List View
        </Link>
      </div>

      <div v-if="recipes.length === 0" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 text-sm text-gray-500 dark:text-gray-400">
        No recipes yet.
      </div>

      <!-- Pivot/matrix table (ingredients x recipes), not a record list --
           kept as a native table with both axes sticky (corner cell sticky
           on both, so it stays put while scrolling either direction) and
           horizontal scroll, rather than collapsed into a DataTable card
           list which can't represent this shape. Denser padding/type below
           md so more of the grid fits on a phone at once. -->
      <div v-else class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <div class="overflow-auto max-h-[70vh]">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-900">
              <tr>
                <th class="sticky left-0 top-0 z-20 bg-gray-50 dark:bg-gray-900 px-2 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase sm:px-4 sm:py-2">Ingredient</th>
                <th
                  v-for="recipe in recipes"
                  :key="recipe.id"
                  class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900 px-2 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase whitespace-nowrap sm:px-4 sm:py-2"
                >
                  <Link :href="route('admin.costing.recipes.edit', recipe.id)" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                    {{ recipe.name }}
                  </Link>
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="ingredient in ingredients" :key="ingredient.id">
                <td class="sticky left-0 z-10 bg-white dark:bg-gray-800 px-2 py-1.5 text-sm text-gray-900 dark:text-white sm:px-4 sm:py-2">
                  {{ ingredient.name }}
                  <span v-if="ingredient.category" class="block text-xs text-gray-400 dark:text-gray-500">{{ ingredient.category }}</span>
                </td>
                <td v-for="recipe in recipes" :key="recipe.id" class="px-2 py-1.5 text-sm text-gray-700 dark:text-gray-300 sm:px-4 sm:py-2">
                  <template v-if="quantityFor(recipe, ingredient.id) > 0">
                    {{ formatQuantity(quantityFor(recipe, ingredient.id), ingredient.unit_type) }}
                  </template>
                  <span v-else class="text-gray-300 dark:text-gray-600">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import { formatQuantity } from '../Shared/formatWeight'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface Ingredient {
  id: number
  name: string
  unit_type: 'g' | 'unit'
  category: string | null
}

interface RecipeColumn {
  id: number
  name: string
  quantities: Record<number, number>
}

interface Props {
  ingredients: Ingredient[]
  recipes: RecipeColumn[]
}

const props = defineProps<Props>()

const quantityFor = (recipe: RecipeColumn, ingredientId: number): number => recipe.quantities[ingredientId] ?? 0
</script>
