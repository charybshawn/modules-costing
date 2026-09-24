<template>
  <div class="pb-36 md:pt-6 md:pb-6">
    <CostingModuleNav />
    <AdminMobileHeader title="Costing & Recipes" />

    <div class="hidden md:block mb-6">
      <div class="flex items-baseline gap-2">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Costing & Recipes</h1>
        <span v-if="props.module_version" class="text-xs text-gray-400 dark:text-gray-500" :title="`Built ${props.module_version.date}`">
          {{ props.module_version.commit }} · {{ props.module_version.date }}
        </span>
      </div>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Pick up wherever you're at in the weekly routine.</p>
    </div>

    <!-- Mobile: icon shelf (shortcuts into the routine) above the headline
         stats. Desktop has its own stat cards below. -->
    <ActionShelf class="md:hidden" overlay="always">
      <ShelfAction icon="tag" label="Update prices" :href="routine[0].href" :count="props.stale_price_count" attention />
      <ShelfAction icon="cart" label="Log a purchase" :href="routine[3].href" />
      <ShelfAction icon="calendar" label="Plan a production run" :href="routine[2].href" />
      <ShelfAction icon="check" label="Complete a production run" :href="routine[4].href" :count="props.planned_run_count" />
    </ActionShelf>

    <StatHero class="md:hidden -mt-4" :headline="heroHeadline" :stats="heroStats" />
    <hr class="md:hidden border-gray-200 dark:border-gray-700" />

    <!-- Desktop stat cards: the mobile StatHero's figures, minus "up to
         date" (the headline card's hint already says it). -->
    <dl class="hidden md:grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div
        v-for="stat in [heroHeadline, ...heroStats.slice(0, 3)]"
        :key="stat.label"
        class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5"
      >
        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ stat.label }}</dt>
        <dd class="mt-1 text-2xl font-semibold" :class="TONES[stat.tone ?? 'default']">{{ stat.value }}</dd>
        <dd v-if="stat.hint" class="text-sm text-gray-500 dark:text-gray-400">{{ stat.hint }}</dd>
      </div>
    </dl>

    <!-- The weekly routine: one continuous white list (full-bleed on
         mobile, a card on desktop), neutral icons, no coloured tiles. -->
    <section class="bg-white dark:bg-gray-800 md:rounded-lg md:shadow-sm md:border md:border-gray-200 md:dark:border-gray-700">
      <h2 class="px-4 md:px-5 pt-5 pb-2 text-base font-semibold text-gray-900 dark:text-white">Weekly routine</h2>
      <ul class="divide-y divide-gray-100 dark:divide-gray-700">
        <li v-for="step in routine" :key="step.label">
          <Link :href="step.href" class="tap-target-touch flex items-center gap-4 px-4 md:px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <span class="flex-shrink-0 w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
              <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" :d="SHELF_ICONS[step.icon]" />
              </svg>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ step.label }}</span>
              <span class="block text-sm text-gray-500 dark:text-gray-400">{{ step.description }}</span>
            </span>
            <span
              v-if="step.count"
              class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
              :class="step.attention
                ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
            >
              {{ step.count }}
            </span>
            <svg class="flex-shrink-0 w-4 h-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </Link>
        </li>
      </ul>
    </section>

    <p v-if="props.module_version" class="md:hidden px-4 pt-4 text-center text-xs text-gray-400 dark:text-gray-500">
      {{ props.module_version.commit }} · {{ props.module_version.date }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ActionShelf from '@/Components/Admin/ActionShelf.vue'
import ShelfAction from '@/Components/Admin/ShelfAction.vue'
import StatHero, { type HeroStat, type StatTone } from '@/Components/Admin/StatHero.vue'
import { SHELF_ICONS, type ShelfIcon } from '@/Components/Admin/shelfIcons'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface Props {
  stale_price_count: number
  planned_run_count: number
  ingredient_count: number
  active_recipe_count: number
  module_version: { commit: string; date: string } | null
}

const props = defineProps<Props>()

// Same tone palette as StatHero, for the desktop cards.
const TONES: Record<StatTone, string> = {
  default: 'text-gray-900 dark:text-white',
  good: 'text-green-600 dark:text-green-400',
  warning: 'text-amber-600 dark:text-amber-400',
  danger: 'text-red-600 dark:text-red-400',
}

// The week starts with the price check, so it's the headline.
const heroHeadline = computed<HeroStat>(() => ({
  label: 'Prices to update',
  value: props.stale_price_count,
  hint: props.stale_price_count > 0 ? `of ${props.ingredient_count} ingredients` : 'All prices are current',
  tone: props.stale_price_count > 0 ? 'warning' : 'good',
}))

const heroStats = computed<HeroStat[]>(() => [
  { label: 'Planned runs', value: props.planned_run_count },
  { label: 'Ingredients', value: props.ingredient_count },
  { label: 'Active recipes', value: props.active_recipe_count },
  { label: 'Prices up to date', value: props.ingredient_count - props.stale_price_count },
])

interface RoutineStep {
  label: string
  description: string
  href: string
  icon: ShelfIcon
  count?: number
  attention?: boolean
}

const routine = computed<RoutineStep[]>(() => [
  {
    label: 'Update Prices',
    description: 'Weekly source-by-source price check.',
    href: route('admin.costing.price-history.index', { needs_update: 1 }),
    icon: 'tag',
    count: props.stale_price_count,
    attention: true,
  },
  {
    label: 'Inventory Recount',
    description: 'Reconcile what you have logged with what’s on the shelf.',
    href: route('admin.costing.inventory.index'),
    icon: 'clipboard',
  },
  {
    label: 'Plan a Production Run',
    description: 'Start from a booked kitchen slot, pick recipes and batches.',
    href: route('admin.costing.kitchen-rentals.index'),
    icon: 'calendar',
  },
  {
    label: 'Log a Purchase',
    description: 'Bought ingredients? Add the received stock.',
    href: route('admin.costing.inventory.index', { open_bulk: 1 }),
    icon: 'cart',
  },
  {
    label: 'Complete a Production Run',
    description: 'Record what you actually made and close out the run.',
    href: route('admin.costing.production-planner.runs'),
    icon: 'check',
    count: props.planned_run_count,
  },
])
</script>
