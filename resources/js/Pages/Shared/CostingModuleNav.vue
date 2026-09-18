<template>
  <!-- Desktop: unchanged horizontal tab strip. Mobile (md:hidden): the
       strip moves to a fixed bottom quick-links bar instead (matches the
       host app's AdminQuickLinksBar, used the same way on Admin/Products,
       Admin/Dashboard, etc.) -- the sidebar-driven per-module nav the host
       app relies on elsewhere doesn't exist for this module's own
       sub-sections (see the comment below), so this strip is the only way
       to reach Inventory/Recipes/Production/Settings, and a bottom bar
       reads far better than a horizontally-scrolled tab row on a phone. -->
  <nav class="hidden md:block mb-6 border-b border-gray-200 dark:border-gray-700">
    <div class="flex gap-6 overflow-x-auto">
      <Link class="tap-target-touch"
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

  <AdminQuickLinksBar :links="mobileLinks" scrollable />
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AdminQuickLinksBar, { type QuickLinkItem } from '@/Components/Admin/AdminQuickLinksBar.vue'
import { adminNavIcons } from '@/utils/adminNavIcons'

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
  // adminNavIcons.ts key for the mobile bar's tile. No exact per-tab icon
  // exists there (that set is sidebar-module icons, not sub-section
  // icons), so these are the closest reasonable stand-ins rather than new
  // SVGs drawn just for this bar.
  icon: keyof typeof adminNavIcons
}

const tabs: Tab[] = [
  { label: 'Dashboard', href: route('admin.costing.index'), match: '/admin/costing', exact: true, icon: 'dashboard' },
  { label: 'Ingredients', href: route('admin.costing.ingredients.index'), match: '/admin/costing/ingredients', icon: 'categories' },
  { label: 'Price History', href: route('admin.costing.price-history.index'), match: '/admin/costing/price-history', icon: 'events' },
  { label: 'Inventory', href: route('admin.costing.inventory.index'), match: '/admin/costing/inventory', icon: 'inventory' },
  { label: 'Recipes', href: route('admin.costing.recipes.index'), match: '/admin/costing/recipes', icon: 'products' },
  { label: 'Production', href: route('admin.costing.production-planner.runs'), match: '/admin/costing/production-planner', icon: 'shipping' },
  { label: 'Settings', href: route('admin.costing.settings.index'), match: '/admin/costing/settings', icon: 'settings' },
]

const isActive = (tab: Tab) => {
  const url = page.url as string
  return tab.exact ? url === tab.match || url === `${tab.match}/` : url.startsWith(tab.match)
}

// AdminQuickLinksBar has no "active" state of its own (it's an action bar
// on the reference pages, not a persistent-location nav) -- variant:
// 'default' vs the indigo-strip's active styling both read as "amber
// accent" either way, so the active tab is conveyed by label alone here
// rather than fighting the shared component's shell for a state it
// wasn't built to carry.
const mobileLinks = computed<QuickLinkItem[]>(() => tabs.map((tab) => ({
  key: tab.href,
  label: tab.label.replace(' ', '\n'),
  href: tab.href,
  icon: adminNavIcons[tab.icon],
})))
</script>
