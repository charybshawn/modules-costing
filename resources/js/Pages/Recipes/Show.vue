<template>
  <AdminShowShell>
    <template #mobile-header>
      <AdminMobileHeader :title="recipe.name" :href="indexUrl" />
    </template>

    <template #actions>
      <IconButton :href="route('admin.costing.recipes.edit', recipe.id)" label="Edit recipe" :class="iconActionClass">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="EDIT_ICON" />
        </svg>
      </IconButton>
      <IconButton label="Delete recipe" :class="dangerIconActionClass" @click="destroy">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="DELETE_ICON" />
        </svg>
      </IconButton>
    </template>

    <template #header>
      <Link :href="indexUrl" :class="backLinkClass">&larr; Back to Recipes</Link>
      <div class="hidden md:flex items-center gap-3 mt-2 mb-6 pr-40">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white truncate">{{ recipe.name }}</h1>
        <span
          class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
          :class="recipe.is_active
            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
            : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
        >
          {{ recipe.is_active ? 'Active' : 'Inactive' }}
        </span>
      </div>
    </template>

    <div class="space-y-8">
      <dl class="grid grid-cols-2 gap-4">
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Cost per unit<template v-if="costPerJar.prorated"> ({{ recipe.fill_size_g }}g)</template></dt>
          <dd class="text-lg font-semibold text-gray-900 dark:text-white tabular-nums">${{ costPerJar.total.toFixed(2) }}</dd>
          <dd v-if="costPerJar.prorated" class="text-xs text-gray-500 dark:text-gray-400">Batch: ${{ costPerJar.batchCost.toFixed(2) }} for {{ +totalGrams.toFixed(2) }}g</dd>
          <dd v-if="costPerJar.anyStale || costPerJar.anyMissing" class="text-xs text-amber-600 dark:text-amber-400">
            Estimate: {{ costPerJar.anyMissing ? 'some prices missing' : 'some prices need updating' }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Stock can make</dt>
          <dd class="text-lg font-semibold" :class="belowMinimum ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white'">
            {{ recipe.max_producible_units }} unit{{ recipe.max_producible_units !== 1 ? 's' : '' }}
          </dd>
          <dd v-if="recipe.min_stock_threshold !== null" class="text-xs text-gray-500 dark:text-gray-400">Minimum {{ recipe.min_stock_threshold }}</dd>
        </div>
        <div class="md:hidden">
          <dt class="text-sm text-gray-500 dark:text-gray-400">Status</dt>
          <dd class="text-lg text-gray-900 dark:text-white">{{ recipe.is_active ? 'Active' : 'Inactive' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Finished product</dt>
          <dd class="text-lg text-gray-900 dark:text-white truncate">{{ finishedGoodOption?.label ?? 'Not linked' }}</dd>
        </div>
      </dl>

      <div>
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Ingredients (per batch)</h2>
        <!-- mx-2: DataTable's mobile rows are sized for a p-6 card; the
             shell pads p-4 on mobile, so this keeps them flush there too. -->
        <div v-if="recipe.ingredients.length" class="mx-2 md:mx-0">
          <DataTable
            :columns="ingredientColumns"
            :items="lines"
            item-key="id"
            hide-toolbar
            mobile-row-style="flat"
            :mobile-summary-fields="3"
            :mobile-hidden-columns="['price']"
            :row-href="(line) => route('admin.costing.ingredients.show', line.id)"
          >
            <template #cell-name="{ item }">
              <span class="text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</span>
              <span v-if="item.isHouseMade" class="ml-1.5 inline-flex items-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">In-house</span>
            </template>
            <template #cell-quantity="{ item }">
              <span class="text-sm tabular-nums text-gray-900 dark:text-white">{{ item.quantity }}{{ item.unit }}</span>
            </template>
            <template #cell-percent="{ item }">
              <span class="text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ item.percent === null ? '—' : `${item.percent.toFixed(1)}%` }}</span>
            </template>
            <template #cell-price="{ item }">
              <span class="text-sm" :class="item.stale ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400'">{{ item.priceLabel }}</span>
            </template>
            <template #cell-subtotal="{ item }">
              <span class="text-sm tabular-nums text-gray-900 dark:text-white">{{ item.subtotal === null ? '—' : `$${item.subtotal.toFixed(2)}` }}</span>
            </template>
          </DataTable>
        </div>
        <p v-else class="text-sm text-gray-500 dark:text-gray-400">No ingredients yet.</p>

        <div v-if="recipe.raw_per_unit.length" class="mt-4">
          <button
            type="button"
            class="tap-target-touch inline-flex items-center text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
            :aria-expanded="showRaw"
            @click="showRaw = !showRaw"
          >
            {{ showRaw ? 'Hide' : 'Show' }} raw ingredients per unit
          </button>
          <div v-if="showRaw" class="mt-2">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
              Everything one {{ recipe.fill_size_g ? `${recipe.fill_size_g}g ` : '' }}unit takes, with in-house ingredients broken down into what they're made from.
            </p>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
              <li v-for="raw in recipe.raw_per_unit" :key="raw.id" class="flex items-center justify-between gap-3 py-2 text-sm">
                <span class="truncate text-gray-900 dark:text-white">{{ raw.name }}</span>
                <span class="shrink-0 tabular-nums text-gray-500 dark:text-gray-400">{{ formatQuantity(raw.quantity) }}{{ raw.unit_type === 'unit' ? ' unit' : 'g' }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div v-if="recipe.byproducts.length">
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Byproducts (per batch)</h2>
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
          <li v-for="line in recipe.byproducts" :key="line.id" class="flex items-center justify-between gap-3 py-2 text-sm">
            <span class="truncate text-gray-900 dark:text-white">{{ line.name }} — {{ line.byproduct_name }}</span>
            <span class="shrink-0 text-gray-500 dark:text-gray-400">{{ line.quantity_per_jar }}{{ line.unit_type === 'unit' ? ' unit' : 'g' }}</span>
          </li>
        </ul>
      </div>

      <div>
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Notes</h2>
        <p class="text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ recipe.notes || 'No notes' }}</p>
      </div>
    </div>
  </AdminShowShell>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import AdminShowShell from '@/Components/Admin/AdminShowShell.vue'
import DataTable, { type Column } from '@/Components/Admin/DataTable.vue'
import IconButton from '@/Components/IconButton.vue'
import { costPerFilledUnit } from '../Shared/fillCost'
import type { FinishedGoodOption } from '../Shared/FinishedGoodPicker.vue'
import { backLinkClass, dangerIconActionClass, iconActionClass } from '../Shared/formClasses'
import { DELETE_ICON, EDIT_ICON } from '../Shared/showIcons'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface IngredientLine {
  id: number
  name: string
  unit_type: 'g' | 'unit'
  is_house_made: boolean
  quantity_per_jar: number
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
  min_stock_threshold: number | null
  fill_size_g: number | null
  is_active: boolean
  max_producible_units: number
  ingredients: IngredientLine[]
  raw_per_unit: Array<{ id: number; name: string; unit_type: 'g' | 'unit'; quantity: number }>
  byproducts: Array<{ id: number; name: string; byproduct_name: string | null; unit_type: 'g' | 'unit'; quantity_per_jar: number }>
}

interface Props {
  recipe: Recipe
  finishedGoodOption: FinishedGoodOption | null
}

const props = defineProps<Props>()
const { confirmDialog } = useConfirmDialog()

const indexUrl = route('admin.costing.recipes.index')

const belowMinimum = computed(() =>
  props.recipe.min_stock_threshold !== null && props.recipe.max_producible_units < props.recipe.min_stock_threshold)

const showRaw = ref(false)

// Small amounts (spices) keep their decimals; bigger ones don't need them.
const formatQuantity = (value: number) => (value < 10 ? +value.toFixed(2) : +value.toFixed(1))

const ingredientColumns: Column[] = [
  { key: 'name', label: 'Ingredient' },
  { key: 'quantity', label: 'Amount' },
  { key: 'percent', label: '% of weight' },
  { key: 'price', label: 'Price' },
  { key: 'subtotal', label: 'Cost' },
]

// Total gram weight of the batch -- per-unit ingredients have no weight, so
// they're left out of the total and get no percentage.
const totalGrams = computed(() => props.recipe.ingredients
  .filter((ingredient) => ingredient.unit_type === 'g')
  .reduce((sum, ingredient) => sum + Number(ingredient.quantity_per_jar || 0), 0))

// Per-ingredient cost lines -- same effective-price math as Edit's
// Costing Breakdown (falls back to the stale figure when nothing's fresh).
const lines = computed(() => props.recipe.ingredients.map((ingredient) => {
  const suffix = ingredient.unit_type === 'unit' ? '/unit' : '/kg'
  const fresh = ingredient.status === 'ok'
  const price = fresh ? ingredient.weekly_price : ingredient.stale_price
  const effective = fresh ? ingredient.effective_price : ingredient.stale_effective_price
  const quantity = ingredient.quantity_per_jar
  return {
    id: ingredient.id,
    name: ingredient.name,
    isHouseMade: ingredient.is_house_made,
    quantity,
    unit: ingredient.unit_type === 'unit' ? ' unit' : 'g',
    percent: ingredient.unit_type === 'g' && totalGrams.value > 0
      ? (Number(quantity || 0) / totalGrams.value) * 100
      : null,
    stale: !fresh,
    priceLabel: price === null ? 'no price' : `$${Number(price).toFixed(2)}${suffix}${fresh ? '' : ' (needs update)'}`,
    subtotal: effective === null || !quantity
      ? null
      : ingredient.unit_type === 'unit' ? quantity * effective : (quantity * effective) / 1000,
  }
}))

const costPerJar = computed(() => {
  const batchCost = lines.value.reduce((sum, line) => sum + (line.subtotal ?? 0), 0)
  const fill = props.recipe.fill_size_g
  return {
    total: costPerFilledUnit(batchCost, totalGrams.value, fill),
    batchCost,
    prorated: fill !== null && fill > 0 && totalGrams.value > 0,
    anyStale: lines.value.some((line) => line.stale && line.subtotal !== null),
    anyMissing: lines.value.some((line) => line.subtotal === null && line.quantity > 0),
  }
})

const destroy = async () => {
  const confirmed = await confirmDialog({
    title: 'Delete Recipe',
    message: `Delete "${props.recipe.name}"? This also removes it from any production runs.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (confirmed) router.delete(route('admin.costing.recipes.destroy', props.recipe.id))
}
</script>
