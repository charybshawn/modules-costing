<template>
  <nav class="mb-6 border-b border-gray-200 dark:border-gray-700">
    <div class="flex gap-6 overflow-x-auto">
      <Link
        v-for="tab in tabs"
        :key="tab.href"
        :href="tab.href"
        :class="[
          'whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors',
          isActive(tab)
            ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600'
        ]"
      >
        {{ tab.label }}
      </Link>
    </div>
  </nav>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'

// Shared sub-navigation for every top-level page in the Costing module.
// AdminNav only supports one sidebar entry per module (see
// App\Support\AdminNav / ADMIN_MODULE_AUTHORING.md), so without this strip
// Inventory / Recipes / Production Planner are only reachable by typing
// their URL directly -- there's no other in-app path to them.
const page = usePage()

interface Tab {
  label: string
  href: string
  match: string
  // Dashboard's match ('/admin/costing') is a prefix of every other tab's
  // URL too, so it needs an exact check -- a plain startsWith would make it
  // look active on every sub-page.
  exact?: boolean
}

const tabs: Tab[] = [
  { label: 'Dashboard', href: route('admin.costing.index'), match: '/admin/costing', exact: true },
  { label: 'Ingredients', href: route('admin.costing.ingredients.index'), match: '/admin/costing/ingredients' },
  { label: 'Price History', href: route('admin.costing.price-history.index'), match: '/admin/costing/price-history' },
  { label: 'Inventory', href: route('admin.costing.inventory.index'), match: '/admin/costing/inventory' },
  { label: 'Recipes', href: route('admin.costing.recipes.index'), match: '/admin/costing/recipes' },
  { label: 'Rental Schedule', href: route('admin.costing.kitchen-rentals.index'), match: '/admin/costing/kitchen-rentals' },
]

const isActive = (tab: Tab) => {
  const url = page.url as string
  return tab.exact ? url === tab.match || url === `${tab.match}/` : url.startsWith(tab.match)
}
</script>
