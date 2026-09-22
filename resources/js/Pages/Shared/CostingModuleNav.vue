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

  <!-- Optional per-page drawer content (e.g. Inventory's row-tap stock
       adjuster): grows directly out of this bar rather than floating as
       its own separate card, matching the host app's own Admin/Inventory
       drawer (its persistent tab-row footer doubling as the drawer's
       handle). bottom is set to navBarHeight -- AdminQuickLinksBar's own
       height, MEASURED rather than a guessed constant, since it already
       varies with label line count (1 vs 2 lines) and safe-area-inset --
       so the drawer's bottom edge always lands exactly flush with the
       bar's top edge, no gap, no overlap, regardless of device. Only
       rendered at all when a page actually provides the slot -- pages
       that don't (everything except Inventory today) get the bar exactly
       as before. -->
  <template v-if="$slots.drawer">
    <!-- z-45: above AdminQuickLinksBar's own z-40, so the backdrop actually
         dims the bar (reading as inert while the drawer's open) instead of
         the bar painting over it. The drawer pane itself goes higher
         still (z-50, the app's usual modal/toast tier). -->
    <div
      v-if="drawerOpen"
      class="md:hidden fixed inset-0 z-[45] bg-black/30"
      @click="$emit('closeDrawer')"
    />
    <div
      v-if="drawerOpen"
      class="md:hidden fixed inset-x-0 z-50 max-h-[70dvh] flex flex-col rounded-t-2xl bg-white dark:bg-gray-800 shadow-xl"
      :style="{ bottom: navBarHeight + 'px' }"
    >
      <slot name="drawer" />
    </div>
  </template>

  <AdminQuickLinksBar ref="navBarRef" :links="mobileLinks" scrollable />
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, useTemplateRef } from 'vue'
import AdminQuickLinksBar, { type QuickLinkItem } from '@/Components/Admin/AdminQuickLinksBar.vue'
import { adminNavIcons } from '@/utils/adminNavIcons'

interface Props {
  // Whether a page-provided #drawer slot is currently expanded. Ignored
  // (and the slot never rendered) when no page passes that slot at all.
  drawerOpen?: boolean
}

withDefaults(defineProps<Props>(), { drawerOpen: false })

defineEmits<{ closeDrawer: [] }>()

// AdminQuickLinksBar positions ITSELF fixed/bottom-0 -- that doesn't
// prevent measuring its own real rendered height (fixed only removes an
// element from affecting its ancestors' layout, not its own box), it just
// means a wrapping element around it can't be used for this the normal
// way (a wrapper containing only a fixed child reports zero height).
// $el reaches the component's actual root DOM node directly instead.
//
// Manual ResizeObserver rather than vueuse's useElementSize: that composable
// observes as soon as its target ref resolves, but a template ref to a
// child component is still null at the point CostingModuleNav's own setup()
// runs (refs populate once the child mounts, which hasn't happened yet) --
// confirmed live as "Failed to execute 'observe' on 'ResizeObserver':
// parameter 1 is not of type 'Element'", thrown during initial mount and
// left CostingModuleNav (and everything nested under it, including the
// drawer) broken for the rest of the page's life. onMounted below
// guarantees the child already exists before the first observe() call.
const navBarComponent = useTemplateRef<InstanceType<typeof AdminQuickLinksBar>>('navBarRef')
const navBarHeight = ref(0)
let navBarResizeObserver: ResizeObserver | null = null

onMounted(() => {
  const el = navBarComponent.value?.$el
  if (!(el instanceof HTMLElement)) return
  navBarHeight.value = el.getBoundingClientRect().height
  navBarResizeObserver = new ResizeObserver(([entry]) => {
    navBarHeight.value = entry.contentRect.height
  })
  navBarResizeObserver.observe(el)
})

onUnmounted(() => navBarResizeObserver?.disconnect())

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
