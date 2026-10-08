<template>
  <div class="space-y-5 text-sm text-gray-900 dark:text-white print:text-black">
    <h3 v-if="title" class="text-base font-semibold">{{ title }}</h3>

    <section v-for="(prep, index) in sheet.prep" :key="prep.id">
      <h4 class="font-medium">
        {{ index + 1 }}. Prep: {{ prep.name }}, make {{ formatQuantity(round(prep.quantity * units, 'g'), 'g') }}
        <span v-if="prep.cook_down_percent !== null" class="font-normal text-gray-500 dark:text-gray-400 print:text-black">(cooks down to {{ +prep.cook_down_percent.toFixed(2) }}%)</span>
      </h4>
      <ul v-if="prep.components_per_kg.length" :class="listClass">
        <li v-for="component in prep.components_per_kg" :key="component.id" :class="rowClass">
          <span>{{ component.name }}</span>
          <span class="tabular-nums">{{ formatQuantity(round(component.quantity_per_kg * prep.quantity * units / 1000, component.unit_type), component.unit_type) }}</span>
        </li>
      </ul>
      <p v-else class="mt-1 text-gray-500 dark:text-gray-400 print:text-black">Set a cook-down % on {{ prep.name }} to see what goes into it.</p>
    </section>

    <section>
      <h4 class="font-medium">{{ sheet.prep.length + 1 }}. Mix</h4>
      <ul :class="listClass">
        <li v-for="line in sheet.mix" :key="line.id" :class="rowClass">
          <span>{{ line.name }}</span>
          <span class="tabular-nums">{{ formatQuantity(round(line.quantity * units, line.unit_type), line.unit_type) }}</span>
        </li>
      </ul>
    </section>

    <section v-if="sheet.pack.length || filledUnits !== null">
      <h4 class="font-medium">{{ sheet.prep.length + 2 }}. Pack</h4>
      <ul v-if="sheet.pack.length" :class="listClass">
        <li v-for="line in sheet.pack" :key="line.id" :class="rowClass">
          <span>{{ line.name }}</span>
          <span class="tabular-nums">{{ formatQuantity(round(line.quantity * units, line.unit_type), line.unit_type) }}</span>
        </li>
      </ul>
      <p v-if="filledUnits !== null" class="mt-1 text-gray-500 dark:text-gray-400 print:text-black">
        Fills about {{ filledUnits }} units at {{ sheet.fill_size_g }}g each.
      </p>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
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
const props = defineProps<{
  sheet: RecipeSheetData
  units: number
  title?: string
}>()

const listClass = 'mt-1 divide-y divide-gray-200 dark:divide-gray-700 print:divide-gray-300'
const rowClass = 'flex items-baseline justify-between gap-3 py-1.5'

// Whole items for packaging and anything counted; grams to two decimals.
const round = (value: number, unitType: 'g' | 'unit') => (unitType === 'unit' ? Math.ceil(value) : Math.round(value * 100) / 100)

// The recipe weighs more than a unit is filled with; the extra fills more units.
const filledUnits = computed(() => (props.sheet.fill_size_g && props.sheet.fill_size_g > 0 && props.sheet.recipe_grams > 0
  ? Math.floor((props.units * props.sheet.recipe_grams) / props.sheet.fill_size_g)
  : null))
</script>
