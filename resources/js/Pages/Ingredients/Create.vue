<template>
  <div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Add Ingredient</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">$/kg and $/unit pricing is calculated from Price History, not entered here.</p>
          </div>
          <Link :href="route('admin.costing.ingredients.index')" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; Back</Link>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-6">
          <FormErrorSummary :errors="form.errors" />

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
            <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Cream Cheese" />
            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
              <input v-model="form.category" type="text" list="category-options" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Dairy & Eggs" />
              <datalist id="category-options">
                <option v-for="c in categories" :key="c" :value="c" />
              </datalist>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Measured In *</label>
              <select v-model="form.unit_type" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="g">Grams (priced per kg)</option>
                <option value="unit">Units (priced per unit, e.g. packaging)</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Weight per Unit</label>
            <input v-model.number="form.weight_per_unit" type="number" min="0.01" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" :placeholder="form.unit_type === 'unit' ? 'e.g. 1' : 'e.g. 1000 for a 1kg block'" />
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              How much one typical unit weighs ({{ form.unit_type === 'unit' ? 'count' : 'grams' }}). Used to work out how many units to buy for a production run when Price History doesn't have a fresh price to derive it from. Leave blank if it varies too much to have a typical size.
            </p>
            <p v-if="form.errors.weight_per_unit" class="mt-1 text-sm text-red-600">{{ form.errors.weight_per_unit }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Waste % *</label>
              <input v-model.number="form.waste_percent" type="number" min="1" max="100" step="0.01" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">100 = no waste, 95 = 5% trim loss.</p>
              <p v-if="form.errors.waste_percent" class="mt-1 text-sm text-red-600">{{ form.errors.waste_percent }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Preferred Source</label>
              <input v-model="form.preferred_source" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Leave blank for auto-cheapest" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Low Stock Threshold</label>
            <input v-model.number="form.low_stock_threshold" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. 500" />
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Notify admins when on-hand stock drops to or below this amount (same unit as above). Leave blank to disable alerts for this ingredient.</p>
            <p v-if="form.errors.low_stock_threshold" class="mt-1 text-sm text-red-600">{{ form.errors.low_stock_threshold }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Byproduct</label>
            <input v-model="form.byproduct_name" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g. Juice, Brine -- leave blank if none" />
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">If this ingredient comes with a usable byproduct (e.g. pickle juice), name it here to make it selectable as its own line in Recipes. Free -- not costed or tracked in inventory.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
            <textarea v-model="form.notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Brand recommendations, purchasing notes, etc."></textarea>
          </div>

          <div class="flex items-center justify-end pt-6 border-t border-gray-200 dark:border-gray-700">
            <Link :href="route('admin.costing.ingredients.index')" class="bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">Cancel</Link>
            <button type="submit" :disabled="form.processing" class="ml-3 bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
              <span v-if="form.processing">Creating...</span>
              <span v-else>Create Ingredient</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { usePersistedForm } from '@/composables/usePersistedForm'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'

defineOptions({ layout: AdminLayout })

interface Props {
  categories: string[]
}

defineProps<Props>()

interface FormData {
  name: string
  category: string
  unit_type: 'g' | 'unit'
  weight_per_unit: number | null
  waste_percent: number
  preferred_source: string
  low_stock_threshold: number | null
  byproduct_name: string
  notes: string
}

const form = usePersistedForm<FormData>({
  name: '',
  category: '',
  unit_type: 'g',
  weight_per_unit: null,
  waste_percent: 100,
  preferred_source: '',
  low_stock_threshold: null,
  byproduct_name: '',
  notes: '',
}, { key: 'costing-ingredient-create' })

const submit = () => {
  form.post(route('admin.costing.ingredients.store'))
}
</script>
