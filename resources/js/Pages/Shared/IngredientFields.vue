<template>
  <div class="space-y-6">
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
      <input v-model="form.name" type="text" required :class="inputClass" placeholder="e.g. Cream Cheese" />
      <InputError :message="form.errors.name" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
      <input v-model="form.category" type="text" list="category-options" :class="inputClass" placeholder="e.g. Dairy & Eggs" />
      <datalist id="category-options">
        <option v-for="c in categories" :key="c" :value="c" />
      </datalist>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pick one already in use or type a new one.</p>
    </div>

    <fieldset>
      <legend class="block text-sm font-medium text-gray-700 dark:text-gray-300">Measured in *</legend>
      <div class="mt-2 space-y-2">
        <label v-for="option in unitOptions" :key="option.value" class="tap-target-touch flex items-start gap-3">
          <input v-model="form.unit_type" type="radio" name="unit_type" :value="option.value" class="mt-0.5 border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
          <span class="text-sm">
            <span class="font-medium text-gray-900 dark:text-white">{{ option.label }}</span>
            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ option.hint }}</span>
          </span>
        </label>
      </div>
      <InputError :message="form.errors.unit_type" />
    </fieldset>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Waste % *</label>
      <input v-model.number="form.waste_percent" type="number" inputmode="decimal" min="1" max="100" step="0.01" required :class="inputClass" />
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">The usable share after trimming: 100 = no waste, 95 = 5% trim loss.</p>
      <InputError :message="form.errors.waste_percent" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Byproduct <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
      <input v-model="form.byproduct_name" type="text" :class="inputClass" placeholder="e.g. Juice, Brine" />
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Name a usable byproduct (e.g. pickle juice) to make it selectable as its own line in Recipes. Free: not costed or tracked in inventory.</p>
      <InputError :message="form.errors.byproduct_name" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
      <textarea v-model="form.notes" rows="3" :class="inputClass" placeholder="Brand recommendations, purchasing notes, etc."></textarea>
    </div>
  </div>
</template>

<script setup lang="ts">
import InputError from '@/Components/InputError.vue'
import { inputClass } from './formClasses'

export interface IngredientFormData {
  name: string
  category: string
  unit_type: 'g' | 'unit'
  waste_percent: number
  byproduct_name: string
  notes: string
}

defineProps<{
  // The page's usePersistedForm -- fields bind straight into it.
  form: any
  categories: string[]
}>()

// Two options: radios, so both are visible at a glance (FORM_DESIGN.md →
// Input type selection).
const unitOptions = [
  { value: 'g', label: 'Grams', hint: 'Priced per kg.' },
  { value: 'unit', label: 'Units', hint: 'Priced per unit, e.g. packaging.' },
]
</script>
