<template>
  <div class="pb-36 md:pt-6 md:pb-6 print:pt-6 print:pb-6">
    <div>
      <CostingModuleNav class="print:hidden" />
      <AdminMobileHeader title="Purchase Order" :href="route('admin.costing.production-planner.runs')" class="print:hidden" />

      <div class="md:max-w-5xl md:mx-auto md:px-6 lg:px-8">
      <div class="hidden md:flex md:items-center md:justify-between mb-6 print:hidden">
        <Link :href="route('admin.costing.production-planner.runs')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; Back to All Runs</Link>
        <div class="flex items-center gap-4">
          <Link :href="route('admin.costing.production-planner.recipe-sheet', productionRun.id)" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">Recipe Sheet</Link>
          <button @click="print" class="tap-target-touch inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
            Print
          </button>
        </div>
      </div>

      <ActionShelf class="md:hidden print:hidden" overlay="always">
        <ShelfAction icon="download" label="Print" @click="print" />
      </ActionShelf>

      <div id="purchase-order-sheet" class="bg-white dark:bg-gray-800 print:bg-white md:shadow-sm md:rounded-lg p-4 md:p-8 -mt-4 md:mt-0 print:mt-0 print:shadow-none print:p-0">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white print:text-black">Purchase Order</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 print:text-black">
          Production run: {{ productionRun.total_units }} units total{{ productionRun.name ? ` — ${productionRun.name}` : '' }} ({{ productionRun.run_date }})
        </p>

        <div v-if="plan.prep_rows.length" class="mt-6">
          <h2 class="text-lg font-medium text-gray-900 dark:text-white print:text-black">Prep first</h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 print:text-black">
            Made in-house for this run. What they're made from is already included in the list below.
          </p>
          <ul class="mt-2 divide-y divide-gray-200 dark:divide-gray-700">
            <li v-for="row in plan.prep_rows" :key="row.ingredient_id" class="flex flex-wrap items-baseline justify-between gap-x-3 py-2 text-sm">
              <span class="font-medium text-gray-900 dark:text-white print:text-black">{{ row.ingredient_name }}</span>
              <span class="text-gray-700 dark:text-gray-300 print:text-black">
                {{ formatQuantity(row.required, 'g') }}
                <span v-if="row.yield_g"> · {{ row.prep_batches }} prep batch{{ row.prep_batches === 1 ? '' : 'es' }} of {{ formatQuantity(row.yield_g, 'g') }}</span>
              </span>
            </li>
          </ul>
        </div>

        <p v-if="plan.purchase_rows.length === 0" class="mt-6 text-sm text-gray-600 dark:text-gray-400 print:text-black">
          Nothing to buy — stock on hand covers everything below.
        </p>

        <div v-if="orderRows.length">
          <!-- Mobile: plain stacked rows (DataTable's 'flat' treatment --
               no card chrome, this is a short homogeneous printable list,
               not a rich record browser). Desktop/print: unchanged table. -->
          <div class="md:hidden print:hidden mt-6 divide-y divide-gray-200 dark:divide-gray-700">
            <div v-for="row in orderRows" :key="row.ingredient_id" class="py-3" :class="fromStockClass(row)" :title="fromStockTitle(row)">
              <div v-if="!row.needs_purchase" class="flex items-baseline justify-between gap-3 text-sm">
                <span>{{ row.ingredient_name }}</span>
                <span>{{ formatQuantity(roundRequired(row), row.unit_type) }} from stock</span>
              </div>
              <template v-else>
              <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ row.ingredient_name }}</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">${{ row.est_cost.toFixed(2) }}</span>
              </div>
              <div class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">
                Need {{ formatQuantity(roundNeeded(row), row.unit_type) }} · buy {{ formatQuantity(row.purchase_qty, row.unit_type) }}
                <span v-if="row.units_to_buy"> · {{ row.units_to_buy }} {{ row.purchase_unit ?? 'no price this week' }}</span>
              </div>
              <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {{ row.best_source ?? 'No preferred source' }}
              </div>
              </template>
            </div>
            <div class="py-3 flex items-baseline justify-between gap-3">
              <span class="text-sm font-semibold text-gray-900 dark:text-white">Total Estimated Purchase Cost</span>
              <span class="text-sm font-semibold text-gray-900 dark:text-white">${{ plan.total_estimated_cost.toFixed(2) }}</span>
            </div>
          </div>

          <div class="hidden md:block print:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mt-6">
              <thead class="bg-gray-50 dark:bg-gray-700/50 print:bg-transparent">
                <tr>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">Ingredient</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">Needed</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">To Purchase</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">Units to Buy</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">Purchase Unit</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">Best Source</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">Est. Cost</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <template v-for="row in orderRows" :key="row.ingredient_id">
                <!-- Covered by stock on hand: still listed (so the order shows
                     everything the run uses), struck through. -->
                <tr v-if="!row.needs_purchase" :class="fromStockClass(row)" :title="fromStockTitle(row)">
                  <td class="px-4 py-2 text-sm">{{ row.ingredient_name }}</td>
                  <td class="px-4 py-2 text-sm">{{ formatQuantity(roundRequired(row), row.unit_type) }}</td>
                  <td class="px-4 py-2 text-sm" colspan="5">from stock</td>
                </tr>
                <tr v-else>
                  <td class="px-4 py-2 text-sm text-gray-900 dark:text-white print:text-black">{{ row.ingredient_name }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">{{ formatQuantity(roundNeeded(row), row.unit_type) }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">{{ formatQuantity(row.purchase_qty, row.unit_type) }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">{{ row.units_to_buy }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">{{ row.purchase_unit ?? '— no price this week' }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">{{ row.best_source ?? '—' }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">${{ row.est_cost.toFixed(2) }}</td>
                </tr>
                </template>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="6" class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white print:text-black text-right">Total Estimated Purchase Cost</td>
                  <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white print:text-black">${{ plan.total_estimated_cost.toFixed(2) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ActionShelf from '@/Components/Admin/ActionShelf.vue'
import ShelfAction from '@/Components/Admin/ShelfAction.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import { formatQuantity } from '../Shared/formatWeight'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface PlanRow {
  ingredient_id: number
  ingredient_name: string
  unit_type: 'g' | 'unit'
  required: number
  on_hand: number
  needs_purchase: boolean
  to_purchase: number
  units_to_buy: number
  purchase_qty: number
  purchase_unit: string | null
  best_source: string | null
  est_cost: number
}

interface PrepRow {
  ingredient_id: number
  ingredient_name: string
  required: number
  yield_g: number
  prep_batches: number
}

interface Plan {
  rows: PlanRow[]
  purchase_rows: PlanRow[]
  prep_rows: PrepRow[]
  total_estimated_cost: number
}

interface ProductionRun {
  id: number
  name: string | null
  run_date: string
  total_units: number
}

interface Props {
  production_run: ProductionRun
  plan: Plan
}

const props = defineProps<Props>()

// The template read this as `productionRun` (no such binding existed) --
// a pre-existing bug that threw at render time; fixed while touching this
// line for the total_jars -> total_units rename.
const productionRun = computed(() => props.production_run)

const print = () => window.print()

// What the order actually has to cover -- required for the run (including
// trim, e.g. apple cores) less what's already on hand -- before it's rounded
// up to whole packages. Packaging counts are whole items.
// Everything the run uses: what to buy first, then what stock already
// covers (struck through).
const orderRows = computed(() => [
  ...props.plan.rows.filter((row) => row.needs_purchase),
  ...props.plan.rows.filter((row) => !row.needs_purchase),
])

const fromStockClass = (row: PlanRow) => (row.needs_purchase ? '' : 'line-through text-gray-400 dark:text-gray-500 print:text-gray-500')
const fromStockTitle = (row: PlanRow) => (row.needs_purchase ? undefined : 'Covered by stock on hand -- nothing to buy')

// The full amount the run takes, for a row stock covers entirely.
const roundRequired = (row: PlanRow) => (row.unit_type === 'unit' ? Math.ceil(row.required) : row.required)

const roundNeeded = (row: PlanRow) => (row.unit_type === 'unit' ? Math.ceil(row.to_purchase) : row.to_purchase)
</script>

<style>
/* Print only the purchase order -- not the admin sidebar, header,
   breadcrumbs, buttons or environment banners around it. */
@media print {
  /* Only while this printout is on the page -- Inertia keeps a visited
     page's styles loaded, so an unscoped rule would blank other pages'
     printouts. Drop everything that isn't the printout or one of its
     containers... */
  body:has(#purchase-order-sheet) *:not(:has(#purchase-order-sheet)):not(#purchase-order-sheet):not(#purchase-order-sheet *) {
    display: none !important;
  }

  /* ...and flatten those containers so the printout starts at the top
     of the page with no layout padding, cards or backgrounds. */
  body:has(#purchase-order-sheet) *:has(#purchase-order-sheet) {
    margin: 0 !important;
    padding: 0 !important;
    max-width: none !important;
    border: 0 !important;
    box-shadow: none !important;
    background: none !important;
  }

  #purchase-order-sheet,
  #purchase-order-sheet * {
    color: #000 !important;
  }

  @page {
    margin: 12mm;
  }
}
</style>
