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
        <li :class="[gridClass, 'py-2 text-base font-bold']">
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

    <!-- Packing: what the run actually fills (its allocations), else, for the
         batch calculator, what a batch of this size would fill. -->
    <section v-for="(pack, index) in packRows" :key="pack.key">
      <h4 class="font-medium">{{ sheet.prep.length + 2 + index }}. Pack: {{ pack.title }}</h4>
      <ul :class="listClass">
        <li :class="[gridClass, 'py-1.5 font-medium']">
          <span>Fills</span>
          <span class="text-right tabular-nums">{{ pack.perBatch === null ? '' : `${pack.perBatch} units` }}</span>
          <span class="text-right tabular-nums">{{ pack.total }} units</span>
        </li>
        <li v-for="line in pack.lines" :key="line.id" :class="[gridClass, 'py-1.5']">
          <span>{{ line.name }}</span>
          <span class="text-right tabular-nums">{{ pack.perBatch === null ? '' : amount(line.quantity * pack.perBatch, line.unit_type) }}</span>
          <span class="text-right tabular-nums">{{ amount(line.quantity * pack.total, line.unit_type) }}</span>
        </li>
      </ul>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatQuantity, formatWeight } from './formatWeight'

export interface RecipeSheetSize {
  id: number
  label: string
  fill_size_g: number
  pack: Array<{ id: number; name: string; unit_type: 'g' | 'unit'; quantity: number }>
}

export interface RecipeSheetAllocation {
  recipe_size_id: number
  label: string
  fill_size_g: number
  units: number
}

export interface RecipeSheetData {
  name: string
  recipe_grams: number
  sizes: RecipeSheetSize[]
  prep: Array<{
    id: number
    name: string
    quantity: number
    cook_down_percent: number | null
    components_per_kg: Array<{ id: number; name: string; unit_type: 'g' | 'unit'; quantity_per_kg: number }>
  }>
  mix: Array<{ id: number; name: string; unit_type: 'g' | 'unit'; is_house_made: boolean; quantity: number }>
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
  // The fills a production run allocated. Without them (the recipe page's
  // calculator) each size shows what a batch of it would fill.
  allocations?: RecipeSheetAllocation[]
}>(), { batches: 1, allocations: undefined })

const listClass = 'mt-1 divide-y divide-gray-200 dark:divide-gray-700 print:divide-gray-300'
const gridClass = 'grid grid-cols-[1fr_6rem_8rem] items-baseline gap-3'

// Whole items for packaging and anything counted; weights to one decimal
// place (kg from 1,000g up).
const amount = (value: number, unitType: 'g' | 'unit') =>
  unitType === 'unit' ? formatQuantity(Math.ceil(value), unitType) : formatWeight(value, 1)

// The recipe weighs more than a unit is filled with; the extra fills more units.
const fillsOf = (fillSizeG: number, batchCount: number) => (fillSizeG > 0 && props.sheet.recipe_grams > 0
  ? Math.floor((props.units * batchCount * props.sheet.recipe_grams) / fillSizeG)
  : 0)

const packRows = computed(() => {
  const sizes = props.sheet.sizes ?? []

  if (props.allocations) {
    return props.allocations.map((allocation) => ({
      key: allocation.recipe_size_id,
      title: allocation.label,
      perBatch: null as number | null,
      total: allocation.units,
      lines: sizes.find((size) => size.id === allocation.recipe_size_id)?.pack ?? [],
    }))
  }

  return sizes.map((size) => ({
    key: size.id,
    title: `${size.label}${size.label === `${size.fill_size_g}g` ? '' : ` (${size.fill_size_g}g)`}`,
    perBatch: fillsOf(size.fill_size_g, 1) as number | null,
    total: fillsOf(size.fill_size_g, props.batches),
    lines: size.pack,
  }))
})
</script>
