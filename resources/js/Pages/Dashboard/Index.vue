<template>
  <div class="py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />

      <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Costing & Recipes</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Pick up wherever you're at in the weekly routine.</p>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <Link
          v-for="card in cards"
          :key="card.label"
          :href="card.href"
          class="group bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-md transition-all"
        >
          <div class="flex items-start">
            <div :class="['flex-shrink-0 w-14 h-14 rounded-lg flex items-center justify-center transition-colors', card.tileClass]">
              <svg class="w-7 h-7" :class="card.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="card.icon" />
              </svg>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ card.label }}</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ card.description }}</p>
              <p v-if="card.stat !== null" class="text-sm mt-2 font-medium" :class="card.statClass">{{ card.stat }}</p>
            </div>
          </div>
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

defineOptions({ layout: AdminLayout })

interface Props {
  stale_price_count: number
  planned_run_count: number
}

const props = defineProps<Props>()

const cards = computed(() => [
  {
    label: 'Update Prices',
    description: 'Weekly source-by-source price check.',
    href: route('admin.costing.price-history.index', { needs_update: 1 }),
    icon: 'M7 7h.01M7 3h5.586a1 1 0 01.707.293l6.414 6.414a1 1 0 010 1.414l-5.586 5.586a1 1 0 01-1.414 0L6.293 10.293A1 1 0 016 9.586V4a1 1 0 011-1z',
    tileClass: 'bg-amber-100 dark:bg-amber-900/40 group-hover:bg-amber-200 dark:group-hover:bg-amber-900/60',
    iconClass: 'text-amber-700 dark:text-amber-300',
    stat: props.stale_price_count > 0 ? `${props.stale_price_count} need${props.stale_price_count !== 1 ? 's' : ''} an update` : null,
    statClass: 'text-amber-700 dark:text-amber-400',
  },
  {
    label: 'Inventory Recount',
    description: 'Reconcile what you have logged with what’s physically on the shelf.',
    href: route('admin.costing.inventory.index'),
    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    tileClass: 'bg-blue-100 dark:bg-blue-900/40 group-hover:bg-blue-200 dark:group-hover:bg-blue-900/60',
    iconClass: 'text-blue-700 dark:text-blue-300',
    stat: null,
    statClass: '',
  },
  {
    label: 'Plan a Production Run',
    description: 'Start from a booked kitchen slot, pick recipes and batches.',
    href: route('admin.costing.kitchen-rentals.index'),
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    tileClass: 'bg-indigo-100 dark:bg-indigo-900/40 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/60',
    iconClass: 'text-indigo-700 dark:text-indigo-300',
    stat: null,
    statClass: '',
  },
  {
    label: 'Log a Purchase',
    description: 'Bought ingredients at the store? Add the received stock.',
    href: route('admin.costing.inventory.index', { open_bulk: 1 }),
    icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
    tileClass: 'bg-emerald-100 dark:bg-emerald-900/40 group-hover:bg-emerald-200 dark:group-hover:bg-emerald-900/60',
    iconClass: 'text-emerald-700 dark:text-emerald-300',
    stat: null,
    statClass: '',
  },
  {
    label: 'Complete a Production Run',
    description: 'Record what you actually made and close out the run.',
    href: route('admin.costing.production-planner.runs'),
    icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    tileClass: 'bg-purple-100 dark:bg-purple-900/40 group-hover:bg-purple-200 dark:group-hover:bg-purple-900/60',
    iconClass: 'text-purple-700 dark:text-purple-300',
    stat: props.planned_run_count > 0 ? `${props.planned_run_count} planned run${props.planned_run_count !== 1 ? 's' : ''} waiting` : null,
    statClass: 'text-purple-700 dark:text-purple-400',
  },
])
</script>
