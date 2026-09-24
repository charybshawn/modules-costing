<template>
  <AdminShowShell>
    <template #mobile-header>
      <AdminMobileHeader :title="entry.ingredient_name ?? 'Price Entry'" :href="indexUrl" />
    </template>

    <template #actions>
      <IconButton :href="route('admin.costing.price-history.edit', entry.id)" label="Edit price entry" :class="iconActionClass">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="EDIT_ICON" />
        </svg>
      </IconButton>
      <IconButton label="Delete price entry" :class="dangerIconActionClass" @click="destroy">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="DELETE_ICON" />
        </svg>
      </IconButton>
    </template>

    <template #header>
      <Link :href="indexUrl" :class="backLinkClass">&larr; Back to Price History</Link>
      <h1 class="hidden md:block mt-2 mb-6 pr-40 text-2xl font-semibold text-gray-900 dark:text-white">{{ entry.ingredient_name ?? 'Price Entry' }}</h1>
    </template>

    <div class="space-y-8">
      <dl class="grid grid-cols-2 gap-4">
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Price</dt>
          <dd class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ entry.price_per_unit === null ? '—' : `$${Number(entry.price_per_unit).toFixed(2)}${entry.unit_type === 'unit' ? '/unit' : '/kg'}` }}
          </dd>
          <dd v-if="entry.unit_type !== 'unit' && entry.price_per_100g !== null" class="text-xs text-gray-500 dark:text-gray-400">
            ${{ Number(entry.price_per_100g).toFixed(2) }} per 100g
          </dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Date checked</dt>
          <dd class="text-lg text-gray-900 dark:text-white">{{ entry.purchased_at ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Total paid</dt>
          <dd class="text-lg text-gray-900 dark:text-white">{{ entry.total_price === null ? '—' : `$${entry.total_price.toFixed(2)}` }}</dd>
          <dd v-if="entry.priced_as_case" class="text-xs text-gray-500 dark:text-gray-400">For the whole case</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500 dark:text-gray-400">Quantity</dt>
          <dd class="text-lg text-gray-900 dark:text-white">{{ entry.qty === null ? '—' : formatQuantity(entry.qty, entry.unit_type ?? 'g') }}</dd>
        </div>
      </dl>

      <div>
        <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Details</h2>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">Ingredient</dt>
            <dd class="mt-1 text-sm">
              <Link :href="route('admin.costing.ingredients.show', entry.ingredient_id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                {{ entry.ingredient_name }}
              </Link>
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">Source</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ entry.brand ? `${entry.provider} — ${entry.brand}` : entry.provider }}</dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">SKU</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ entry.sku || '—' }}</dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500 dark:text-gray-400">Logged</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ entry.logged_at ?? '—' }}</dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-sm text-gray-500 dark:text-gray-400">Notes</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ entry.notes || 'No notes' }}</dd>
          </div>
        </dl>
      </div>

      <!-- Needs words, so a labelled control rather than a pill icon. -->
      <Link
        :href="route('admin.costing.price-history.create', { clone: entry.id })"
        :class="secondaryButtonClass"
      >
        Log a new price from this one
      </Link>
    </div>
  </AdminShowShell>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import AdminShowShell from '@/Components/Admin/AdminShowShell.vue'
import IconButton from '@/Components/IconButton.vue'
import { formatQuantity } from '../Shared/formatWeight'
import { backLinkClass, dangerIconActionClass, iconActionClass, secondaryButtonClass } from '../Shared/formClasses'
import { DELETE_ICON, EDIT_ICON } from '../Shared/showIcons'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface Entry {
  id: number
  ingredient_id: number
  ingredient_name: string | null
  unit_type: 'g' | 'unit' | null
  provider: string
  brand: string | null
  purchased_at: string | null
  logged_at: string | null
  qty: number | null
  priced_as_case: boolean
  total_price: number | null
  price_per_unit: number | null
  price_per_100g: number | null
  sku: string | null
  notes: string | null
}

const props = defineProps<{ entry: Entry }>()
const { confirmDialog } = useConfirmDialog()

const indexUrl = route('admin.costing.price-history.index')

const destroy = async () => {
  const confirmed = await confirmDialog({
    title: 'Delete Price Entry',
    message: 'Delete this price entry? This cannot be undone.',
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (confirmed) router.delete(route('admin.costing.price-history.destroy', props.entry.id))
}
</script>
