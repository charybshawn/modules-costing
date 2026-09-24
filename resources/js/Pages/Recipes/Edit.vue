<template>
  <div>
    <ResponsiveFormSections
      ref="shellRef"
      :sections="sections"
      :dirty="form.isDirty"
      :initial-step="initialStep"
      @discard="form.clearPersistedData()"
    >
      <template #mobile-header="{ currentStepIndex, goToStep }">
        <AdminMobileHeader title="Edit Recipe" :on-back="() => shellRef?.guardNavigation(indexUrl)">
          <template #subtitle>
            <SaveIndicator
              v-if="form.processing || form.recentlySuccessful || saveProblem"
              :processing="form.processing"
              :recently-successful="form.recentlySuccessful"
              :error="saveProblem"
            />
            <span v-else class="max-w-full truncate text-xs text-gray-500 dark:text-gray-400">{{ recipe.name }}</span>
          </template>
          <template #actions>
            <IconButton label="Cancel editing" :class="iconActionClass" @click="closeEditor">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </IconButton>
          </template>
          <template #bottom>
            <FormStepNav :sections="sections" :current-index="currentStepIndex" @select="goToStep" />
          </template>
        </AdminMobileHeader>
      </template>

      <template #mobile-actions>
        <IconButton label="Delete recipe" :class="dangerIconActionClass" @click="destroy">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </IconButton>
      </template>

      <template #header>
        <Link :href="indexUrl" :class="backLinkClass" @click.prevent="shellRef?.guardNavigation(indexUrl)">&larr; Back to Recipes</Link>
        <div class="hidden md:flex flex-wrap items-center justify-between gap-3 mt-2 mb-6">
          <div class="flex items-center gap-3 min-w-0">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white truncate">{{ recipe.name }}</h1>
            <span
              class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
              :class="form.is_active
                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'"
            >
              {{ form.is_active ? 'Active' : 'Inactive' }}
            </span>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" :error="saveProblem" />
          </div>
          <div class="flex items-center gap-3">
            <button type="button" :class="dangerOutlineButtonClass" @click="destroy">Delete Recipe</button>
            <button type="button" :class="secondaryButtonClass" @click="closeEditor">Cancel</button>
          </div>
        </div>

        <FormErrorSummary v-if="Object.keys(form.errors).length > 0" :errors="form.errors" class="mb-6" />
      </template>

      <template #section-details>
        <RecipeDetailsFields
          :form="form"
          :duplicate-name="isDuplicateName"
          :finished-good-label="finishedGoodOption?.label ?? null"
        />
      </template>

      <template #section-ingredients>
        <RecipeLinesFields :rows="form.ingredients" :pool="ingredients" noun="Ingredient" />
        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
          Cost per jar: <span class="font-semibold text-gray-900 dark:text-white tabular-nums">${{ costPerJar.total.toFixed(2) }}</span>
          <span v-if="costPerJar.anyStale || costPerJar.anyMissing" class="text-amber-600 dark:text-amber-500"> (estimate)</span>
        </p>
      </template>

      <template #section-byproducts>
        <RecipeLinesFields
          :rows="form.byproducts"
          :pool="byproductIngredients"
          noun="Byproduct"
          description="Free, always assumed sufficient: not costed or tracked in inventory, just documents the recipe."
          empty-pool-hint="No ingredient has a byproduct yet. Name one on the ingredient to offer it here."
        />
      </template>

      <template #section-cost>
        <p v-if="!costBreakdown.length" class="text-sm text-gray-500 dark:text-gray-400">Add ingredients to see the cost per jar.</p>

        <dl v-else class="divide-y divide-gray-100 dark:divide-gray-700">
          <div v-for="line in costBreakdown" :key="line.ingredientId" class="flex items-start justify-between gap-3 py-2 text-sm">
            <div class="min-w-0">
              <dt class="font-medium text-gray-900 dark:text-white truncate">{{ line.name }}</dt>
              <dd class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {{ line.quantity }}{{ line.unit }}
                <span class="mx-1">&middot;</span>
                <button type="button" class="tap-target-touch inline-flex items-center font-medium" :class="priceIndicatorClass(line.ingredientId)" @click="openPricesModal(line.ingredientId)">
                  {{ priceIndicatorLabel(line.ingredientId) }}
                </button>
              </dd>
            </div>
            <dd class="shrink-0 text-gray-900 dark:text-white tabular-nums">{{ line.subtotal === null ? '—' : `$${line.subtotal.toFixed(2)}` }}</dd>
          </div>
        </dl>

        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-900 dark:text-white">Cost per jar</span>
            <span class="text-lg font-semibold text-gray-900 dark:text-white tabular-nums">${{ costPerJar.total.toFixed(2) }}</span>
          </div>
          <p v-if="costPerJar.anyStale || costPerJar.anyMissing" class="mt-2 text-xs text-amber-600 dark:text-amber-500">
            Estimate only: {{ costPerJar.anyMissing ? 'one or more ingredients have no logged price' : 'one or more ingredients are using a price that needs updating' }}. Tap a price above to fix it.
          </p>
        </div>
      </template>
    </ResponsiveFormSections>

    <AvailablePricesModal :ingredient="pricesIngredient" @close="pricesIngredient = null" />
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { usePersistedForm } from '@/composables/usePersistedForm'
import { useConfirmDialog } from '@/composables/useConfirmDialog'
import { useAutosaveSession } from '@/composables/useAutosaveSession'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import FormStepNav from '@/Components/Admin/FormStepNav.vue'
import SaveIndicator from '@/Components/Admin/SaveIndicator.vue'
import ResponsiveFormSections, { type FormSection } from '@/Components/Admin/ResponsiveFormSections.vue'
import IconButton from '@/Components/IconButton.vue'
import AvailablePricesModal, { type PricesIngredient } from '../Shared/AvailablePricesModal.vue'
import type { FinishedGoodOption } from '../Shared/FinishedGoodPicker.vue'
import RecipeDetailsFields, { type RecipeFormData } from '../Shared/RecipeDetailsFields.vue'
import RecipeLinesFields from '../Shared/RecipeLinesFields.vue'
import { backLinkClass, dangerIconActionClass, dangerOutlineButtonClass, iconActionClass, secondaryButtonClass } from '../Shared/formClasses'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface IngredientOption {
  id: number
  name: string
  unit_type: 'g' | 'unit'
  byproduct_name: string | null
  status: 'ok' | 'no_price_this_week'
  weekly_price: number | null
  effective_price: number | null
  stale_price: number | null
  stale_effective_price: number | null
}

interface Recipe {
  id: number
  name: string
  notes: string | null
  product_id: number | null
  min_stock_threshold: number | null
  is_active: boolean
  ingredients: Array<{ ingredient_id: number; quantity_per_jar: number }>
  byproducts: Array<{ ingredient_id: number; quantity_per_jar: number }>
}

interface Props {
  recipe: Recipe
  ingredients: IngredientOption[]
  finishedGoodOption: FinishedGoodOption | null
  existingRecipeNames: string[]
}

const props = defineProps<Props>()
const { confirmDialog } = useConfirmDialog()

const sections: FormSection[] = [
  { key: 'details', title: 'Details' },
  { key: 'ingredients', title: 'Ingredients (per jar)', shortTitle: 'Ingredients' },
  { key: 'byproducts', title: 'Byproducts (per jar)', shortTitle: 'Byproducts' },
  { key: 'cost', title: 'Costing Breakdown', shortTitle: 'Cost' },
]

const shellRef = ref<InstanceType<typeof ResponsiveFormSections> | null>(null)
const indexUrl = route('admin.costing.recipes.index')
const updateUrl = () => route('admin.costing.recipes.update', props.recipe.id)

const byproductIngredients = props.ingredients.filter((i) => i.byproduct_name)

const initialData: RecipeFormData = {
  name: props.recipe.name,
  notes: props.recipe.notes ?? '',
  product_id: props.recipe.product_id,
  min_stock_threshold: props.recipe.min_stock_threshold,
  is_active: props.recipe.is_active,
  ingredients: props.recipe.ingredients.map((row) => ({ ...row })),
  byproducts: props.recipe.byproducts.map((row) => ({ ...row })),
}

// What the × -> "Discard Changes" restores.
const openingData = JSON.parse(JSON.stringify(initialData)) as RecipeFormData

const form = usePersistedForm<RecipeFormData>(initialData, {
  key: `costing-recipe-edit-${props.recipe.id}`,
  initialData,
  autosave: {
    url: updateUrl,
    requiredFields: ['name'],
    enabled: (): boolean => !isDuplicateName.value,
    onSuccess: (): void => markSaved(),
  },
})

// Half-filled rows (just added, not picked or no quantity yet) stay on
// screen but aren't sent -- on every save, autosave included.
form.transform((data) => ({
  ...data,
  ingredients: data.ingredients.filter((row) => row.ingredient_id !== null && row.quantity_per_jar !== null),
  byproducts: data.byproducts.filter((row) => row.ingredient_id !== null && row.quantity_per_jar !== null),
}))

const { initialStep, markSaved, closeEditor } = useAutosaveSession({
  form,
  noun: 'recipe',
  exitUrl: () => indexUrl,
  updateUrl,
  discardDraftUrl: () => route('admin.costing.recipes.discard-draft', props.recipe.id),
  discardPayload: () => ({ ...openingData }),
})

const isDuplicateName = computed(() => {
  const name = form.name.trim().toLowerCase()
  return name.length > 0 && props.existingRecipeNames.some((existing) => existing.trim().toLowerCase() === name)
})

const saveProblem = computed(() => {
  if (form.processing || !form.isDirty) return null
  if (!form.name?.trim()) return 'Not saved: name is required'
  if (isDuplicateName.value) return 'Not saved: that name is taken'
  if (form.hasErrors) return 'Not saved: fix the errors below'
  return null
})

const findIngredient = (id: number | null) => props.ingredients.find((i) => i.id === id) ?? null

const ingredientUnit = (id: number | null) => (findIngredient(id)?.unit_type === 'unit' ? 'unit' : 'g')

// Price indicator on each cost line -- taps through to the same Available
// Prices modal used on the Ingredients page, without leaving the recipe.
const pricesIngredient = ref<PricesIngredient | null>(null)

const openPricesModal = (ingredientId: number) => {
  const ingredient = findIngredient(ingredientId)
  if (!ingredient) return
  pricesIngredient.value = { id: ingredient.id, name: ingredient.name, unit_type: ingredient.unit_type }
}

const priceIndicatorLabel = (id: number | null): string => {
  const ingredient = findIngredient(id)
  if (!ingredient) return ''
  const suffix = ingredient.unit_type === 'unit' ? '/unit' : '/kg'
  if (ingredient.status === 'ok') return `$${Number(ingredient.weekly_price).toFixed(2)}${suffix}`
  if (ingredient.stale_price !== null) return `$${Number(ingredient.stale_price).toFixed(2)}${suffix} ⚠`
  return 'no price'
}

const priceIndicatorClass = (id: number | null): string => {
  const ingredient = findIngredient(id)
  if (!ingredient) return ''
  if (ingredient.status === 'ok') return 'text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400'
  if (ingredient.stale_price !== null) return 'text-amber-600 hover:text-amber-800 dark:text-amber-500'
  return 'text-gray-400 dark:text-gray-500 italic hover:text-indigo-600 dark:hover:text-indigo-400'
}

interface CostLine {
  ingredientId: number
  name: string
  quantity: number
  unit: string
  subtotal: number | null
}

// Per-ingredient cost lines -- each main ingredient's effective $
// contribution (falling back to the stale figure when there's no fresh
// price), same math CalculateProductionPlan uses per-ingredient.
const costBreakdown = computed<CostLine[]>(() => {
  const lines: CostLine[] = []

  for (const row of form.ingredients) {
    if (row.ingredient_id === null) continue
    const ingredient = findIngredient(row.ingredient_id)
    if (!ingredient) continue

    const quantity = row.quantity_per_jar ?? 0
    const effectivePrice = ingredient.status === 'ok' ? ingredient.effective_price : ingredient.stale_effective_price
    const subtotal = effectivePrice === null || !quantity
      ? null
      : ingredient.unit_type === 'unit'
        ? quantity * effectivePrice
        : (quantity * effectivePrice) / 1000

    lines.push({
      ingredientId: ingredient.id,
      name: ingredient.name,
      quantity,
      unit: ingredientUnit(row.ingredient_id),
      subtotal,
    })
  }

  return lines
})

// Estimated cost per jar: the lines above, summed, flagging any stale or
// missing price.
const costPerJar = computed(() => {
  let total = 0
  let anyStale = false
  let anyMissing = false

  for (const line of costBreakdown.value) {
    const ingredient = findIngredient(line.ingredientId)
    if (!ingredient || !line.quantity) continue
    if (line.subtotal === null) {
      anyMissing = true
      continue
    }
    if (ingredient.status !== 'ok') anyStale = true
    total += line.subtotal
  }

  return { total, anyStale, anyMissing }
})

const destroy = async () => {
  const confirmed = await confirmDialog({
    title: 'Delete Recipe',
    message: `Delete "${props.recipe.name}"? This also removes it from any production runs.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!confirmed) return
  form.cancelAutosave()
  form.clearPersistedData()
  router.delete(route('admin.costing.recipes.destroy', props.recipe.id))
}
</script>
