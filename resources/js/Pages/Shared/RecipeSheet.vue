<template>
  <div class="space-y-5 text-sm text-gray-900 dark:text-white print:text-black">
    <h3 v-if="title" class="text-base font-semibold">{{ title }}</h3>

    <!-- Column headings, shared by every section below. -->
    <div :class="[gridClass, 'border-b border-gray-300 dark:border-gray-600 pb-1 text-xs font-medium uppercase text-gray-500 dark:text-gray-400 print:text-black']">
      <span></span>
      <span class="text-right">Per batch</span>
      <span class="text-right">Total ({{ batches }} batch{{ batches === 1 ? '' : 'es' }})</span>
    </div>

    <section v-for="(prep, index) in sheet.prep" :key="prep.id">
      <h4 class="font-medium">
        {{ index + 1 }}. Prep: {{ prep.name }}
        <span v-if="prep.cook_down_percent !== null" class="font-normal text-gray-500 dark:text-gray-400 print:text-black">(cooks down to {{ +prep.cook_down_percent.toFixed(2) }}%)</span>
      </h4>
      <ul :class="listClass">
        <li :class="[gridClass, 'py-1.5 font-medium']">
          <span>Make</span>
          <span class="text-right tabular-nums">{{ amount(prep.quantity * units, 'g') }}</span>
          <span class="text-right tabular-nums">{{ amount(prep.quantity * units * batches, 'g') }}</span>
        </li>
        <li v-for="component in prep.components_per_kg" :key="component.id" :class="[gridClass, 'py-1.5']">
          <span>{{ component.name }}</span>
          <span class="text-right tabular-nums">{{ amount(component.quantity_per_kg * prep.quantity * units / 1000, component.unit_type) }}</span>
          <span class="text-right tabular-nums">{{ amount(component.quantity_per_kg * prep.quantity * units * batches / 1000, component.unit_type) }}</span>
        </li>
      </ul>
      <p v-if="!prep.components_per_kg.length" class="mt-1 text-gray-500 dark:text-gray-400 print:text-black">Set a cook-down % on {{ prep.name }} to see what goes into it.</p>
    </section>

    <section>
      <h4 class="font-medium">{{ sheet.prep.length + 1 }}. Mix</h4>
      <ul :class="listClass">
        <li v-for="line in sheet.mix" :key="line.id" :class="[gridClass, 'py-1.5']">
          <span>{{ line.name }}</span>
          <span class="text-right tabular-nums">{{ amount(line.quantity * units, line.unit_type) }}</span>
          <span class="text-right tabular-nums">{{ amount(line.quantity * units * batches, line.unit_type) }}</span>
        </li>
      </ul>
    </section>

    <section v-if="sheet.pack.length || fills(1) !== null">
      <h4 class="font-medium">{{ sheet.prep.length + 2 }}. Pack</h4>
      <ul :class="listClass">
        <li v-for="line in sheet.pack" :key="line.id" :class="[gridClass, 'py-1.5']">
          <span>{{ line.name }}</span>
          <span class="text-right tabular-nums">{{ amount(line.quantity * units, line.unit_type) }}</span>
          <span class="text-right tabular-nums">{{ amount(line.quantity * units * batches, line.unit_type) }}</span>
        </li>
        <li v-if="fills(1) !== null" :class="[gridClass, 'py-1.5 text-gray-500 dark:text-gray-400 print:text-black']">
          <span>Fills about (at {{ sheet.fill_size_g }}g)</span>
          <span class="text-right tabular-nums">{{ fills(1) }} units</span>
          <span class="text-right tabular-nums">{{ fills(batches) }} units</span>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup lang="ts">
import { formatQuantity } from './formatWeight'

export interface RecipeSheetData {
  name: string
  fill_size_g: number | null
  recipe_grams: number
  prep: Array<{
    id: number
    name: string
    quantity: number
    cook_down_percent: number | null
    components_per_kg: Array<{ id: number; name: string; unit_type: 'g' | 'unit'; quantity_per_kg: number }>
  }>
  mix: Array<{ id: number; name: string; unit_type: 'g' | 'unit'; is_house_made: boolean; quantity: number }>
  pack: Array<{ id: number; name: string; unit_type: 'g' | 'unit'; quantity: number }>
}

// One product's production sheet -- what to prep, mix and pack -- with
// every per-unit amount scaled to `units`. Shared by the recipe page's
// batch calculator (a live preview) and a production run's recipe sheet.
const props = withDefaults(defineProps<{
  sheet: RecipeSheetData
  // Units in one batch -- the "Per batch" column.
  units: number
  // How many batches -- the "Total" column is per batch x this.
  batches?: number
  title?: string
}>(), { batches: 1 })

const listClass = 'mt-1 divide-y divide-gray-200 dark:divide-gray-700 print:divide-gray-300'
const gridClass = 'grid grid-cols-[1fr_6rem_8rem] items-baseline gap-3'

// Whole items for packaging and anything counted; grams to two decimals.
const amount = (value: number, unitType: 'g' | 'unit') =>
  formatQuantity(unitType === 'unit' ? Math.ceil(value) : Math.round(value * 100) / 100, unitType)

// The recipe weighs more than a unit is filled with; the extra fills more units.
const fills = (batchCount: number) => (props.sheet.fill_size_g && props.sheet.fill_size_g > 0 && props.sheet.recipe_grams > 0
  ? Math.floor((props.units * batchCount * props.sheet.recipe_grams) / props.sheet.fill_size_g)
  : null)
</script>
