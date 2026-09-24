<template>
  <div class="pb-36 md:pt-6 md:pb-6">
    <div>
      <CostingModuleNav />
      <AdminMobileHeader title="Ingredients" />

      <!-- Desktop: title + actions band. Mobile: AdminMobileHeader above
           covers the title and the ActionShelf below the actions. -->
      <div class="hidden md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Ingredients</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Master catalogue of ingredients. Pricing columns are calculated automatically from Price History.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('admin.costing.price-history.index')"
            class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
          >
            Price History
          </Link>
          <Link
            :href="route('admin.costing.ingredients.create')"
            class="tap-target-touch inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
          >
            + Add Ingredient
          </Link>
        </div>
      </div>

      <!-- Mobile: icon shelf over the headline stats (same pattern as the
           host's dashboard/index pages). -->
      <ActionShelf class="md:hidden" overlay="always">
        <ShelfAction icon="plus" label="Add ingredient" :href="route('admin.costing.ingredients.create')" />
        <ShelfAction icon="tag" label="Prices needing an update" :href="route('admin.costing.price-history.index', { needs_update: 1 })" :count="needsUpdateCount" attention />
      </ActionShelf>
      <StatHero
        class="md:hidden -mt-4"
        :headline="{ label: 'Ingredients', value: props.ingredients.length }"
        :stats="[
          { label: 'Need a price update', value: needsUpdateCount, tone: needsUpdateCount > 0 ? 'warning' : 'default' },
          { label: 'Categories', value: categoryCount },
        ]"
      />
      <hr class="md:hidden border-gray-200 dark:border-gray-700" />

      <!-- No overflow-hidden here: it establishes a containing block for
           DataTable's sticky toolbar, pinning it at a fixed offset inside
           this box instead of sticking to the viewport (confirmed live --
           the toolbar rendered mid-card on mobile). Matches Admin/Products/
           Index.vue's own wrapper, which omits it for the same reason. -->
      <div class="bg-white dark:bg-gray-800 md:shadow-sm md:rounded-lg">
        <BulkActionsBar :count="selectedIds.length" singular="ingredient" plural="ingredients" @clear="selectedIds = []">
          <button type="button" @click="bulkDelete" class="tap-target-touch px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-md hover:bg-red-700">
            Delete
          </button>
        </BulkActionsBar>
        <DataTable
          :columns="columns"
          :items="ingredients"
          :sort-field="sortField"
          :sort-direction="sortDirection"
          selectable
          v-model:selected-ids="selectedIds"
          searchable
          search-placeholder="Search ingredients..."
          empty-message="No ingredients yet."
          empty-action-label="Add your first ingredient"
          :empty-action-href="route('admin.costing.ingredients.create')"
          table-id="costing-ingredients"
          item-key="id"
          mobile-row-style="line"
          :row-href="(item) => route('admin.costing.ingredients.show', item.id)"
          :extra-filter-count="recipeFilterId !== null ? 1 : 0"
          @sort="handleSort"
          @clear-filters="recipeFilterId = null"
        >
          <!-- Recipe is DataTable's filters-extra slot rather than a
               filterOnly column: an ingredient can be in several recipes
               (belongsToMany), so matching is "does this ingredient's
               recipe_ids include the selected recipe", not the
               exact-equality/one-of-several checks DataTable's
               select/multiselect filter types do internally. -->
          <template #filters-extra>
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Recipe</label>
              <select
                v-model="recipeFilterId"
                class="w-full text-base sm:text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                <option :value="null">Any</option>
                <option v-for="recipe in props.recipes" :key="recipe.id" :value="recipe.id">{{ recipe.name }}</option>
              </select>
            </div>
          </template>

          <!-- Single-line mobile row: ingredient (truncates) + category only,
               plus the stale-price dot -- pricing detail, sources, and every
               other column drop from the compact view entirely rather than
               wrapping to a second line; tapping the row opens Show
               (row-href, above), which has sources and prices plus edit
               and delete. -->
          <template #mobile-card="{ item }">
            <div class="flex items-center gap-3 min-w-0">
              <span class="min-w-0 flex-1 inline-flex items-center gap-1.5 truncate text-sm font-medium text-gray-900 dark:text-white">
                <span class="truncate">{{ item.name }}</span>
                <span
                  v-if="item.status !== 'ok'"
                  class="w-1.5 h-1.5 rounded-full bg-amber-500 dark:bg-amber-400 flex-shrink-0"
                  :title="`No price logged in the last ${props.staleness_days} days -- needs update`"
                ></span>
              </span>
              <span class="shrink-0 truncate max-w-[40%] text-sm text-gray-500 dark:text-gray-400">{{ item.category ?? '—' }}</span>
            </div>
          </template>

          <template #cell-name="{ item }">
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</div>
          </template>

          <template #cell-category="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.category ?? '—' }}</span>
          </template>

          <template #cell-waste_percent="{ item }">
            <span class="text-sm text-gray-900 dark:text-white">{{ item.waste_percent }}%</span>
          </template>

          <template #cell-weekly_price="{ item }">
            <div class="inline-flex items-center gap-1.5">
              <span class="text-sm font-medium text-gray-900 dark:text-white">
                <span v-if="item.status === 'ok'">${{ Number(item.weekly_price).toFixed(2) }}{{ item.unit_type === 'unit' ? '/unit' : '/kg' }}</span>
                <span v-else-if="item.stale_price !== null">${{ Number(item.stale_price).toFixed(2) }}{{ item.unit_type === 'unit' ? '/unit' : '/kg' }}</span>
                <span v-else class="italic font-normal text-gray-500 dark:text-gray-400">no price logged</span>
              </span>
              <span
                v-if="item.status !== 'ok' && item.stale_price !== null"
                class="w-1.5 h-1.5 rounded-full bg-amber-500 dark:bg-amber-400 flex-shrink-0"
                :title="`No price logged in the last ${props.staleness_days} days -- needs update`"
              ></span>
            </div>
          </template>

          <template #cell-price_per_100g="{ item }">
            <span v-if="item.status === 'ok' && item.price_per_100g !== null" class="text-sm text-gray-900 dark:text-white">
              ${{ Number(item.price_per_100g).toFixed(2) }}
            </span>
            <span v-else-if="item.status !== 'ok' && item.stale_price_per_100g !== null" class="text-sm text-gray-500 dark:text-gray-400">
              ${{ Number(item.stale_price_per_100g).toFixed(2) }}
            </span>
            <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>
          </template>

          <template #cell-effective_price="{ item }">
            <span v-if="item.status === 'ok'" class="text-sm text-gray-900 dark:text-white">
              ${{ Number(item.effective_price).toFixed(2) }}
            </span>
            <span v-else-if="item.stale_effective_price !== null" class="text-sm text-gray-500 dark:text-gray-400">
              ${{ Number(item.stale_effective_price).toFixed(2) }}
            </span>
            <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>
          </template>

          <template #cell-source_count="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">
              {{ item.source_count }} source{{ item.source_count !== 1 ? 's' : '' }}
            </span>
          </template>

          <template #cell-purchase_unit="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.purchase_unit ?? '—' }}</span>
          </template>
        </DataTable>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import ActionShelf from '@/Components/Admin/ActionShelf.vue'
import ShelfAction from '@/Components/Admin/ShelfAction.vue'
import StatHero from '@/Components/Admin/StatHero.vue'
import DataTable, { type Column } from '@/Components/Admin/DataTable.vue'
import BulkActionsBar from '../Shared/BulkActionsBar.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface IngredientRow {
  id: number
  name: string
  category: string | null
  unit_type: 'g' | 'unit'
  waste_percent: number
  weekly_price: number | null
  effective_price: number | null
  source_count: number
  purchase_unit: string | null
  status: 'ok' | 'no_price_this_week'
  stale_price: number | null
  stale_effective_price: number | null
  price_per_100g: number | null
  stale_price_per_100g: number | null
  recipe_ids: number[]
}

interface RecipeOption {
  id: number
  name: string
}

interface Props {
  ingredients: IngredientRow[]
  recipes: RecipeOption[]
  staleness_days: number
}

const props = defineProps<Props>()
const { confirmDialog } = useConfirmDialog()

const recipeFilterId = ref<number | null>(null)

const needsUpdateCount = computed(() => props.ingredients.filter((i) => i.status !== 'ok').length)
const categoryCount = computed(() => new Set(props.ingredients.map((i) => i.category).filter(Boolean)).size)

const sortField = ref('name')
const sortDirection = ref<'asc' | 'desc'>('asc')

const handleSort = (field: string) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
}

const ingredients = computed(() => {
  const field = sortField.value as keyof IngredientRow
  const dir = sortDirection.value === 'asc' ? 1 : -1
  const result = recipeFilterId.value === null
    ? props.ingredients
    : props.ingredients.filter((i) => i.recipe_ids.includes(recipeFilterId.value as number))
  return [...result].sort((a, b) => String(a[field] ?? '').localeCompare(String(b[field] ?? '')) * dir)
})

const columns: Column[] = [
  { key: 'name', label: 'Ingredient', sortable: true },
  { key: 'category', label: 'Category', sortable: true, hideable: true, filterable: true },
  { key: 'weekly_price', label: '$/kg', hideable: true },
  { key: 'price_per_100g', label: '$/100g', hideable: true },
  { key: 'waste_percent', label: 'Waste %', hideable: true },
  { key: 'effective_price', label: 'Effective $/kg', hideable: true },
  { key: 'source_count', label: 'Sources', hideable: true },
  { key: 'purchase_unit', label: 'Purchase Unit', hideable: true },
]

// Per-row actions (Price History, Edit, Delete) are gone -- the row itself
// opens Show (row-href, above), whose actions pill has edit and delete. Bulk delete (BulkActionsBar, below) is a separate
// selection-driven mechanism and still applies.
const selectedIds = ref<number[]>([])

const bulkDelete = async () => {
  if (selectedIds.value.length === 0) return
  if (!(await confirmDialog({
    title: 'Delete Ingredients',
    message: `Delete ${selectedIds.value.length} ingredient${selectedIds.value.length === 1 ? '' : 's'}? This also removes their price history and inventory records.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  }))) return

  router.post(route('admin.costing.ingredients.bulk-action'), { action: 'delete', ids: selectedIds.value }, {
    preserveScroll: true,
    onSuccess: () => { selectedIds.value = [] },
  })
}
</script>
