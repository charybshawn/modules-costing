<template>
  <div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Active</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Inactive recipes are hidden from the Production Planner's recipe picker, but stay listed here. Active recipes need at least one size, each linked to a finished product.</p>
      </div>
      <ToggleSwitch v-model="form.is_active" label="Active" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Flavour name *</label>
      <input v-model="form.name" type="text" required :class="inputClass" placeholder="e.g. Sriracha Maple Bacon" />
      <p v-if="duplicateName" class="mt-1 text-sm text-amber-600 dark:text-amber-500">A recipe named "{{ form.name.trim() }}" already exists.</p>
      <InputError :message="form.errors.name" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Minimum units in stock <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
      <input v-model.number="form.min_stock_threshold" type="number" inputmode="numeric" min="0" step="1" :class="inputClass" placeholder="e.g. 20" />
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Flagged for reordering whenever current ingredient stock can't make at least this many units. Leave blank to turn off.</p>
      <InputError :message="form.errors.min_stock_threshold" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Preferred batch size <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
      <input v-model.number="form.preferred_batch_size" type="number" inputmode="numeric" min="1" step="1" :class="inputClass" placeholder="e.g. 20" />
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">How many units you usually make at a time. The batch calculator on the recipe page starts from this; each production run still sets its own batch size.</p>
      <InputError :message="form.errors.preferred_batch_size" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
      <textarea v-model="form.notes" rows="4" :class="inputClass"></textarea>
    </div>
  </div>
</template>

<script setup lang="ts">
import InputError from '@/Components/InputError.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'
import type { RecipeSizeRow } from './RecipeSizesFields.vue'
import { inputClass } from './formClasses'

export type RecipeRow = { ingredient_id: number | null; quantity_per_jar: number | null }

export interface RecipeFormData {
  name: string
  notes: string
  min_stock_threshold: number | null
  preferred_batch_size: number | null
  is_active: boolean
  sizes: RecipeSizeRow[]
  ingredients: RecipeRow[]
  byproducts: RecipeRow[]
}

defineProps<{
  form: any
  duplicateName: boolean
}>()
</script>
