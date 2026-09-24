<template>
  <AdminShowShell>
    <template #mobile-header>
      <AdminMobileHeader :title="ingredient.name" :href="indexUrl" />
    </template>

    <template #actions>
      <IconButton :href="route('admin.costing.ingredients.edit', ingredient.id)" label="Edit ingredient" :class="iconActionClass">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="EDIT_ICON" />
        </svg>
      </IconButton>
      <IconButton label="Delete ingredient" :class="dangerIconActionClass" @click="destroy">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="DELETE_ICON" />
        </svg>
      </IconButton>
    </template>

    <template #header>
      <Link :href="indexUrl" :class="backLinkClass">&larr; Back to Ingredients</Link>
      <!-- pr-40 keeps a long name clear of the sticky actions pill that
           overlays this row's right side on desktop. -->
      <h1 class="hidden md:block mt-2 mb-6 pr-40 text-2xl font-semibold text-gray-900 dark:text-white">{{ ingredient.name }}</h1>
    </template>

    <div class="space-y-8">
      <dl class="grid grid-cols-2 gap-4">
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Current price</dt>
          <dd class="text-lg font-semibold" :class="ingredient.status === 'ok' ? 'text-gray-900 dark:text-white' : 'text-amber-600 dark:text-amber-400'">
            {{ currentPrice ?? 'No price logged' }}
          </dd>
          <dd v-if="ingredient.status !== 'ok'" class="text-xs text-amber-600 dark:text-amber-400">
            Needs an update: nothing logged in the last {{ staleness_days }} days
          </dd>
          <dd v-else-if="ingredient.source_used" class="text-xs text-gray-500 dark:text-gray-400">
            {{ ingredient.source_used }}, {{ ingredient.last_price_date }}
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">After waste</dt>
          <dd class="text-lg text-gray-900 dark:text-white">{{ effectivePrice ?? '—' }}</dd>
          <dd class="text-xs text-gray-500 dark:text-gray-400">{{ ingredient.waste_percent }}% usable</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Measured in</dt>
          <dd class="text-lg text-gray-900 dark:text-white">{{ ingredient.unit_type === 'unit' ? 'Units' : 'Grams' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Buys in</dt>
          <dd class="text-lg text-gray-900 dark:text-white">{{ ingredient.purchase_unit ?? '—' }}</dd>
        </div>
      </dl>

      <div>
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Details</h2>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">Category</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ ingredient.category || '—' }}</dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">Byproduct</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ ingredient.byproduct_name || 'None' }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Notes</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-white whitespace-pre-line">{{ ingredient.notes || 'No notes' }}</dd>
          </div>
        </dl>
      </div>

      <div>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
          <h2 class="text-lg font-medium text-gray-900 dark:text-white">Sources &amp; prices</h2>
          <Link
            :href="route('admin.costing.price-history.create', { ingredient: ingredient.id })"
            class="tap-target-touch inline-flex items-center text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300"
          >
            + Log a price
          </Link>
        </div>
        <p class="mb-2 text-sm text-gray-600 dark:text-gray-400">
          Where you buy this and what you're paying. Pick one as preferred to lock it in for recipes and the production planner, regardless of price.
        </p>
        <!-- mx-2: SourcesTable bleeds -mx-6 (sized for a p-6 card); the
             shell pads p-4 on mobile, so this keeps it flush there too. -->
        <div class="mx-2 md:mx-0">
          <SourcesTable :ingredient="{ id: ingredient.id, name: ingredient.name, unit_type: ingredient.unit_type }" />
        </div>
      </div>

      <div>
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Used in recipes</h2>
        <ul v-if="ingredient.recipes.length" class="divide-y divide-gray-200 dark:divide-gray-700">
          <li v-for="recipe in ingredient.recipes" :key="recipe.id">
            <Link
              :href="route('admin.costing.recipes.show', recipe.id)"
              class="tap-target-touch flex items-center justify-between gap-3 py-2 text-sm text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400"
            >
              <span class="truncate">{{ recipe.name }}</span>
              <span v-if="!recipe.is_active" class="shrink-0 text-xs text-gray-500 dark:text-gray-400">Inactive</span>
            </Link>
          </li>
        </ul>
        <p v-else class="text-sm text-gray-500 dark:text-gray-400">Not used in any recipe yet.</p>
      </div>
    </div>
  </AdminShowShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import AdminShowShell from '@/Components/Admin/AdminShowShell.vue'
import IconButton from '@/Components/IconButton.vue'
import SourcesTable from '../Shared/SourcesTable.vue'
import { backLinkClass, dangerIconActionClass, iconActionClass } from '../Shared/formClasses'
import { DELETE_ICON, EDIT_ICON } from '../Shared/showIcons'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface Ingredient {
  id: number
  name: string
  category: string | null
  unit_type: 'g' | 'unit'
  waste_percent: number
  notes: string | null
  byproduct_name: string | null
  recipes: Array<{ id: number; name: string; is_active: boolean }>
  status: 'ok' | 'no_price_this_week'
  weekly_price: number | null
  effective_price: number | null
  stale_price: number | null
  stale_effective_price: number | null
  source_used: string | null
  last_price_date: string | null
  purchase_unit: string | null
}

interface Props {
  ingredient: Ingredient
  staleness_days: number
}

const props = defineProps<Props>()
const { confirmDialog } = useConfirmDialog()

const indexUrl = route('admin.costing.ingredients.index')
const suffix = computed(() => (props.ingredient.unit_type === 'unit' ? '/unit' : '/kg'))

// Falls back to the stale figure (flagged in amber) when nothing's fresh.
const money = (value: number | null) => (value === null ? null : `$${Number(value).toFixed(2)}${suffix.value}`)
const currentPrice = computed(() => money(props.ingredient.status === 'ok' ? props.ingredient.weekly_price : props.ingredient.stale_price))
const effectivePrice = computed(() => money(props.ingredient.status === 'ok' ? props.ingredient.effective_price : props.ingredient.stale_effective_price))

const destroy = async () => {
  const confirmed = await confirmDialog({
    title: 'Delete Ingredient',
    message: `Delete "${props.ingredient.name}"? This also removes its price history and inventory record.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (confirmed) router.delete(route('admin.costing.ingredients.destroy', props.ingredient.id))
}
</script>
