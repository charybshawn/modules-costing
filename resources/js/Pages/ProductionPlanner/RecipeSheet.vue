<template>
  <div class="pb-36 md:pt-6 md:pb-6 print:pt-6 print:pb-6">
    <div>
      <CostingModuleNav class="print:hidden" />
      <AdminMobileHeader title="Recipe Sheet" :href="route('admin.costing.production-planner.runs')" class="print:hidden" />

      <div class="md:max-w-5xl md:mx-auto md:px-6 lg:px-8">
      <div class="hidden md:flex md:items-center md:justify-between mb-6 print:hidden">
        <Link :href="route('admin.costing.production-planner.runs')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; Back to All Runs</Link>
        <div class="flex items-center gap-4">
          <Link :href="route('admin.costing.production-planner.purchase-order', production_run.id)" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">Purchase Order</Link>
          <button @click="print" class="tap-target-touch inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
            Print
          </button>
        </div>
      </div>

      <ActionShelf class="md:hidden print:hidden" overlay="always">
        <ShelfAction icon="download" label="Print" @click="print" />
      </ActionShelf>

      <div id="recipe-sheet-print" class="bg-white dark:bg-gray-800 print:bg-white md:shadow-sm md:rounded-lg p-4 md:p-8 -mt-4 md:mt-0 print:mt-0 print:shadow-none print:p-0">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white print:text-black">Recipe Sheet</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 print:text-black">
          Production run{{ production_run.name ? ` ${production_run.name}` : '' }} ({{ production_run.run_date }})
        </p>

        <p v-if="sheets.length === 0" class="mt-8 text-center text-gray-600 dark:text-gray-400 py-12 border border-dashed border-gray-300 dark:border-gray-600 rounded-md">
          No flavours in this run yet. Add batches to see their recipes.
        </p>

        <!-- One product per printed page. -->
        <div v-for="(item, index) in sheets" :key="item.recipe_id" class="mt-8" :class="index > 0 ? 'pt-8 border-t border-gray-200 dark:border-gray-700 print:border-0 print:pt-0 print:break-before-page' : ''">
          <!-- Per batch: what the kitchen makes at once, repeated `batches` times. -->
          <RecipeSheet :sheet="item.sheet" :units="item.batch_size" :title="batchTitle(item)" />
        </div>
      </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ActionShelf from '@/Components/Admin/ActionShelf.vue'
import ShelfAction from '@/Components/Admin/ShelfAction.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import RecipeSheet, { type RecipeSheetData } from '../Shared/RecipeSheet.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

defineProps<{
  production_run: { id: number; name: string | null; run_date: string }
  sheets: Array<{ recipe_id: number; units: number; batches: number; batch_size: number; sheet: RecipeSheetData }>
}>()

const print = () => window.print()

const batchTitle = (item: { batches: number; batch_size: number; sheet: RecipeSheetData }) =>
  `${item.sheet.name}: batch of ${item.batch_size} units, make ${item.batches} batch${item.batches === 1 ? '' : 'es'}`
</script>

<style>
/* Print only the recipe sheets -- not the admin layout, links or buttons
   around them. Scoped to this page's content: Inertia keeps a visited
   page's styles loaded, so an unscoped rule would blank other printouts. */
@media print {
  body:has(#recipe-sheet-print) *:not(:has(#recipe-sheet-print)):not(#recipe-sheet-print):not(#recipe-sheet-print *) {
    display: none !important;
  }

  body:has(#recipe-sheet-print) *:has(#recipe-sheet-print) {
    margin: 0 !important;
    padding: 0 !important;
    max-width: none !important;
    border: 0 !important;
    box-shadow: none !important;
    background: none !important;
  }

  #recipe-sheet-print,
  #recipe-sheet-print * {
    color: #000 !important;
  }

  @page {
    margin: 12mm;
  }
}
</style>
