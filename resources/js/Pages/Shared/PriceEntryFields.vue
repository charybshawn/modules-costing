<template>
  <div class="space-y-6">
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ingredient *</label>
      <select v-model="form.ingredient_id" required :class="inputClass">
        <option value="" disabled>Select an ingredient</option>
        <option v-for="ingredient in ingredients" :key="ingredient.id" :value="ingredient.id">{{ ingredient.name }}</option>
      </select>
      <InputError :message="form.errors.ingredient_id" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source *</label>
      <template v-if="!entry.addingSource">
        <select v-model="form.package_size_id" required :disabled="!form.ingredient_id" :class="inputClass">
          <option value="" disabled>{{ form.ingredient_id ? 'Select a source' : 'Select an ingredient first' }}</option>
          <option v-for="source in entry.sources" :key="source.id" :value="source.id">{{ sourceLabel(source) }}</option>
        </select>
        <button
          type="button"
          :disabled="!form.ingredient_id"
          class="tap-target-touch inline-flex items-center mt-1 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 disabled:opacity-40 disabled:cursor-not-allowed"
          @click="entry.startAddSource"
        >
          + Add a new source
        </button>
        <InputError :message="form.errors.package_size_id" />
        <p v-if="!form.errors.package_size_id && missingSourceHint && form.ingredient_id && !form.package_size_id" class="mt-1 text-xs text-amber-600 dark:text-amber-500">
          {{ missingSourceHint }}
        </p>
      </template>

      <div v-else class="mt-2 space-y-4 rounded-md border border-gray-200 dark:border-gray-700 p-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Provider *</label>
          <input v-model="entry.newSourceProvider" type="text" placeholder="e.g. GFS" :class="inputClass" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Brand <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
          <input v-model="entry.newSourceBrand" type="text" :class="inputClass" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Unit size *</label>
            <input v-model.number="entry.newSourceSize" type="number" inputmode="decimal" min="0.01" step="0.01" :class="inputClass" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Units per case</label>
            <input v-model.number="entry.newSourceUnitsPerCase" type="number" inputmode="numeric" min="1" step="1" :class="inputClass" />
          </div>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">Unit size is ONE package (e.g. one lid), never the case total. Leave units per case at 1 if it isn't sold by the case.</p>
        <p v-if="entry.addSourceError" class="text-sm text-red-600 dark:text-red-400">{{ entry.addSourceError }}</p>
        <div class="flex items-center gap-3">
          <button
            type="button"
            :disabled="entry.addSourceSaving || !entry.newSourceProvider || !entry.newSourceSize"
            class="tap-target-touch inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40"
            @click="entry.saveNewSource"
          >
            {{ entry.addSourceSaving ? 'Adding…' : 'Add Source' }}
          </button>
          <button type="button" :disabled="entry.addSourceSaving" :class="secondaryButtonClass" @click="entry.cancelAddSource">Cancel</button>
        </div>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date checked <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
      <input v-model="form.purchased_at" type="date" :class="inputClass" />
    </div>

    <div v-if="entry.isGramBased">
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Weight{{ entry.isCase ? ' per each' : '' }} *</label>
      <div class="flex gap-2">
        <input v-model.number="entry.weightValue" type="number" inputmode="decimal" min="0" step="0.01" placeholder="e.g. 2.27" :class="[inputClass, 'flex-1 min-w-0']" />
        <select v-model="entry.weightUnit" aria-label="Weight unit" :class="[inputClass, '!w-24']">
          <option value="kg">kg</option>
          <option value="g">g</option>
        </select>
      </div>

      <label class="tap-target-touch mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
        <input v-model="entry.isCase" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
        This was a case of identical packages
      </label>
      <div v-if="entry.isCase" class="mt-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Items per case</label>
        <input v-model.number="entry.eachesPerCase" type="number" inputmode="numeric" min="1" step="1" :class="[inputClass, '!w-32']" />
      </div>

      <p v-if="form.qty !== null" class="mt-1 text-xs text-gray-500 dark:text-gray-400">= {{ form.qty }} g total logged</p>
      <InputError :message="form.errors.qty" />
    </div>
    <div v-else>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Quantity (units) *</label>
      <input v-model.number="form.qty" type="number" inputmode="numeric" min="0" step="1" placeholder="e.g. 24" :class="inputClass" />
      <InputError :message="form.errors.qty" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Total price ($) *</label>
      <input v-model.number="form.total_price" type="number" inputmode="decimal" min="0" step="0.01" :class="inputClass" />
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">$/kg (or $/unit) is worked out from this and the quantity above.</p>
      <InputError :message="form.errors.total_price" />

      <fieldset v-if="!entry.isGramBased && entry.selectedSource && entry.selectedSource.units_per_case > 1" class="mt-3">
        <legend class="text-sm font-medium text-gray-700 dark:text-gray-300">Price is for</legend>
        <div class="mt-1 space-y-1">
          <label class="tap-target-touch flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="radio" :checked="!form.priced_as_case" class="border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" @change="entry.applyPricedAsCase(false)" />
            One package
          </label>
          <label class="tap-target-touch flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="radio" :checked="form.priced_as_case" class="border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" @change="entry.applyPricedAsCase(true)" />
            Whole case ({{ entry.selectedSource.units_per_case }})
          </label>
        </div>
      </fieldset>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SKU <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
      <input v-model="form.sku" type="text" :class="inputClass" />
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
      <input v-model="form.notes" type="text" :class="inputClass" />
    </div>
  </div>
</template>

<script setup lang="ts">
import InputError from '@/Components/InputError.vue'
import { sourceLabel } from './useIngredientSources'
import type { PriceEntryIngredient, PriceEntryState } from './usePriceEntry'
import { inputClass, secondaryButtonClass } from './formClasses'

// Stateless: all state lives in the page's usePriceEntry (see its docblock
// for why) and is passed in as `entry`.
defineProps<{
  form: any
  entry: PriceEntryState
  ingredients: PriceEntryIngredient[]
  // Shown under Source when none is picked (e.g. the entry's original
  // source was removed).
  missingSourceHint?: string
}>()
</script>
