<template>
  <div class="pb-36 md:pt-6 md:pb-6 print:pt-6 print:pb-6">
    <div>
      <CostingModuleNav class="print:hidden" />
      <AdminMobileHeader title="Purchase Order" :href="route('admin.costing.production-planner.runs')" class="print:hidden" />

      <div class="md:max-w-5xl md:mx-auto md:px-6 lg:px-8">
      <div class="hidden md:flex md:items-center md:justify-between mb-6 print:hidden">
        <Link :href="route('admin.costing.production-planner.runs')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; Back to All Runs</Link>
        <button @click="print" class="tap-target-touch inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
          Print
        </button>
      </div>

      <ActionShelf class="md:hidden print:hidden" overlay="always">
        <ShelfAction icon="download" label="Print" @click="print" />
      </ActionShelf>

      <div class="bg-white dark:bg-gray-800 print:bg-white md:shadow-sm md:rounded-lg p-4 md:p-8 -mt-4 md:mt-0 print:mt-0 print:shadow-none print:p-0">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white print:text-black">Purchase Order</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 print:text-black">
          Production run: {{ productionRun.total_units }} units total{{ productionRun.name ? ` — ${productionRun.name}` : '' }} ({{ productionRun.run_date }})
        </p>

        <div v-if="plan.purchase_rows.length === 0" class="mt-8 text-center text-gray-600 dark:text-gray-400 print:text-black py-12 border border-dashed border-gray-300 dark:border-gray-600 rounded-md">
          Nothing to buy — inventory covers all requirements.
        </div>

        <div v-else>
          <!-- Mobile: plain stacked rows (DataTable's 'flat' treatment --
               no card chrome, this is a short homogeneous printable list,
               not a rich record browser). Desktop/print: unchanged table. -->
          <div class="md:hidden print:hidden mt-6 divide-y divide-gray-200 dark:divide-gray-700">
            <div v-for="row in plan.purchase_rows" :key="row.ingredient_id" class="py-3">
              <div class="flex items-baseline justify-between gap-3">
                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ row.ingredient_name }}</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">${{ row.est_cost.toFixed(2) }}</span>
              </div>
              <div class="mt-0.5 text-sm text-gray-700 dark:text-gray-300">
                {{ formatQuantity(row.purchase_qty, row.unit_type) }}
                <span v-if="row.units_to_buy"> · {{ row.units_to_buy }} {{ row.purchase_unit ?? 'no price this week' }}</span>
              </div>
              <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {{ row.best_source ?? 'No preferred source' }}
              </div>
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
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">To Purchase</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">Units to Buy</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">Purchase Unit</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">Best Source</th>
                  <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 print:text-black uppercase">Est. Cost</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="row in plan.purchase_rows" :key="row.ingredient_id">
                  <td class="px-4 py-2 text-sm text-gray-900 dark:text-white print:text-black">{{ row.ingredient_name }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">{{ formatQuantity(row.purchase_qty, row.unit_type) }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">{{ row.units_to_buy }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">{{ row.purchase_unit ?? '— no price this week' }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">{{ row.best_source ?? '—' }}</td>
                  <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 print:text-black">${{ row.est_cost.toFixed(2) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="5" class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white print:text-black text-right">Total Estimated Purchase Cost</td>
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
  to_purchase: number
  units_to_buy: number
  purchase_qty: number
  purchase_unit: string | null
  best_source: string | null
  est_cost: number
}

interface Plan {
  purchase_rows: PlanRow[]
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
</script>
