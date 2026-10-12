<template>
  <div>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
      Each size this flavour is filled into. A size has its own fill weight, packaging and storefront product: completing a production run credits the units filled to that product, and its price is the sell price used for costing.
    </p>

    <div v-if="form.sizes.length" class="space-y-4">
      <div
        v-for="(size, index) in form.sizes"
        :key="size.id ?? `new-${index}`"
        class="rounded-md border border-gray-200 dark:border-gray-700 p-4 space-y-4"
      >
        <div class="flex items-start justify-between gap-3">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ sizeTitle(size) }}</h3>
          <IconButton label="Remove size" :class="dangerIconActionClass" @click="form.sizes.splice(index, 1)">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </IconButton>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fill weight per unit (g) *</label>
            <input v-model.number="size.fill_size_g" type="number" inputmode="decimal" min="0" step="0.01" required :class="inputClass" placeholder="e.g. 280" />
            <InputError :message="form.errors[`sizes.${index}.fill_size_g`]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Label <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
            <input v-model="size.label" type="text" :class="inputClass" :placeholder="size.fill_size_g ? `${size.fill_size_g}g` : 'e.g. 280g'" />
            <InputError :message="form.errors[`sizes.${index}.label`]" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Finished product
            <span v-if="form.is_active">*</span>
            <span v-else class="font-normal text-gray-500 dark:text-gray-400">Optional</span>
          </label>
          <FinishedGoodPicker v-model="size.finished_good_id" :initial-label="size.finished_good_label" class="mt-1" />
          <InputError :message="form.errors[`sizes.${index}.finished_good_id`]" />
        </div>

        <div>
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Packaging per unit</p>
          <div v-if="size.packaging.length" class="mt-1 border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700">
            <div v-for="(line, lineIndex) in size.packaging" :key="lineIndex" class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2">
              <select
                v-model.number="line.ingredient_id"
                required
                :aria-label="`Packaging ${lineIndex + 1}`"
                class="flex-1 basis-full sm:basis-auto min-w-[10rem] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"
              >
                <option :value="null" disabled>Select packaging&hellip;</option>
                <option v-for="opt in availablePackaging(size, line.ingredient_id)" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
              </select>
              <div class="flex flex-1 sm:flex-none items-center gap-2">
                <input
                  v-model.number="line.quantity_per_unit"
                  type="number"
                  inputmode="decimal"
                  min="0"
                  step="0.001"
                  required
                  :aria-label="`Quantity for packaging ${lineIndex + 1}`"
                  class="w-28 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"
                />
                <span class="text-xs text-gray-500 dark:text-gray-400 w-8">unit</span>
              </div>
              <IconButton label="Remove packaging" :class="dangerIconActionClass" @click="size.packaging.splice(lineIndex, 1)">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </IconButton>
            </div>
          </div>
          <p v-else class="mt-1 text-sm text-gray-500 dark:text-gray-400">None yet.</p>

          <button
            type="button"
            :disabled="size.packaging.length >= packagingPool.length"
            class="tap-target-touch inline-flex items-center mt-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 disabled:opacity-40"
            @click="size.packaging.push({ ingredient_id: null, quantity_per_unit: 1 })"
          >
            + Add Packaging
          </button>
          <p v-if="packagingPool.length === 0" class="mt-1 text-xs text-gray-500 dark:text-gray-400">No ingredient is in the Packaging category yet.</p>
        </div>
      </div>
    </div>
    <p v-else class="text-sm text-gray-500 dark:text-gray-400">No sizes yet.</p>

    <InputError :message="form.errors.sizes" />

    <button
      type="button"
      class="tap-target-touch inline-flex items-center mt-4 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
      @click="addSize"
    >
      + Add Size
    </button>
  </div>
</template>

<script setup lang="ts">
import InputError from '@/Components/InputError.vue'
import IconButton from '@/Components/IconButton.vue'
import FinishedGoodPicker from './FinishedGoodPicker.vue'
import { dangerIconActionClass, inputClass } from './formClasses'

export interface PackagingOption {
  id: number
  name: string
  unit_type: 'g' | 'unit'
}

export interface SizePackagingRow {
  ingredient_id: number | null
  quantity_per_unit: number | null
}

export interface RecipeSizeRow {
  id: number | null
  label: string
  fill_size_g: number | null
  finished_good_id: number | null
  // Display only (the picker's initial text) -- never sent.
  finished_good_label: string | null
  packaging: SizePackagingRow[]
}

const props = defineProps<{
  form: any
  packagingPool: PackagingOption[]
}>()

const sizeTitle = (size: RecipeSizeRow) => size.label || (size.fill_size_g ? `${size.fill_size_g}g` : 'New size')

// Packaging already picked on this size isn't offered again.
const availablePackaging = (size: RecipeSizeRow, currentValue: number | null) => {
  const chosen = new Set(size.packaging.map((line) => line.ingredient_id).filter((id) => id !== null && id !== currentValue))
  return props.packagingPool.filter((option) => !chosen.has(option.id))
}

const addSize = () => {
  props.form.sizes.push({ id: null, label: '', fill_size_g: props.form.sizes.length ? null : 280, finished_good_id: null, finished_good_label: null, packaging: [] })
}
</script>
