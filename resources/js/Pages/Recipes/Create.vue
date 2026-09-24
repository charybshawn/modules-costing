<template>
  <div>
    <ResponsiveFormSections
      ref="shellRef"
      :sections="sections"
      :dirty="form.isDirty"
      @discard="form.clearPersistedData()"
    >
      <template #mobile-header="{ currentStepIndex, goToStep }">
        <AdminMobileHeader title="New Recipe" :on-back="leave">
          <template #subtitle>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" :error="saveProblem" />
          </template>
          <template #actions>
            <IconButton label="Cancel" :class="iconActionClass" @click="leave">
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

      <template #header>
        <Link :href="indexUrl" :class="backLinkClass" @click.prevent="leave">&larr; Back to Recipes</Link>
        <div class="hidden md:flex flex-wrap items-center justify-between gap-3 mt-2 mb-2">
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">New Recipe</h1>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" :error="saveProblem" />
          </div>
          <button type="button" :class="secondaryButtonClass" @click="leave">Cancel</button>
        </div>
        <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">Add each ingredient this flavour uses, with grams (or units) per jar. Saved as you go.</p>

        <FormErrorSummary v-if="Object.keys(form.errors).length > 0" :errors="form.errors" class="mb-6" />
      </template>

      <template #section-details>
        <RecipeDetailsFields :form="form" :duplicate-name="isDuplicateName" />
      </template>

      <template #section-ingredients>
        <RecipeLinesFields :rows="form.ingredients" :pool="ingredients" noun="Ingredient" />
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
    </ResponsiveFormSections>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePersistedForm } from '@/composables/usePersistedForm'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import FormStepNav from '@/Components/Admin/FormStepNav.vue'
import SaveIndicator from '@/Components/Admin/SaveIndicator.vue'
import ResponsiveFormSections, { type FormSection } from '@/Components/Admin/ResponsiveFormSections.vue'
import IconButton from '@/Components/IconButton.vue'
import RecipeDetailsFields, { type RecipeFormData } from '../Shared/RecipeDetailsFields.vue'
import RecipeLinesFields, { type RecipeIngredientOption } from '../Shared/RecipeLinesFields.vue'
import { backLinkClass, iconActionClass, secondaryButtonClass } from '../Shared/formClasses'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface Props {
  ingredients: RecipeIngredientOption[]
  existingRecipeNames: string[]
}

const props = defineProps<Props>()

const sections: FormSection[] = [
  { key: 'details', title: 'Details' },
  { key: 'ingredients', title: 'Ingredients (per jar)', shortTitle: 'Ingredients' },
  { key: 'byproducts', title: 'Byproducts (per jar)', shortTitle: 'Byproducts' },
]

const shellRef = ref<InstanceType<typeof ResponsiveFormSections> | null>(null)
const indexUrl = route('admin.costing.recipes.index')

const byproductIngredients = props.ingredients.filter((i) => i.byproduct_name)

const form = usePersistedForm<RecipeFormData>({
  name: '',
  notes: '',
  product_id: null,
  min_stock_threshold: null,
  is_active: true,
  ingredients: [],
  byproducts: [],
}, {
  key: 'costing-recipe-create',
  // Autosave creates the recipe: the first save POSTs to store, whose
  // `stay` branch redirects to the new recipe's Edit page on the same step,
  // and that page's PUT autosave takes over from there.
  autosave: {
    method: 'post',
    url: () => `${route('admin.costing.recipes.store')}?step=${shellRef.value?.currentStepIndex ?? 0}`,
    requiredFields: ['name'],
    // Names are unique; don't create (or 422) mid-word or on a duplicate.
    enabled: (): boolean => nameLongEnough.value && !isDuplicateName.value,
  },
})

// Half-filled rows (just added, not picked or no quantity yet) stay on
// screen but aren't sent -- on every save, autosave included.
form.transform((data) => ({
  ...data,
  ingredients: data.ingredients.filter((row) => row.ingredient_id !== null && row.quantity_per_jar !== null),
  byproducts: data.byproducts.filter((row) => row.ingredient_id !== null && row.quantity_per_jar !== null),
}))

const nameLongEnough = computed<boolean>(() => (form.name ?? '').trim().length >= 2)

const isDuplicateName = computed(() => {
  const name = form.name.trim().toLowerCase()
  return name.length > 0 && props.existingRecipeNames.some((existing) => existing.trim().toLowerCase() === name)
})

// Nothing saved yet, so leaving goes through the shell's unsaved-changes
// guard rather than the autosave session.
const leave = () => shellRef.value?.guardNavigation(indexUrl)

const saveProblem = computed(() => {
  if (form.processing || !form.isDirty) return null
  if (!nameLongEnough.value) return 'Not saved yet: add a name (2+ characters)'
  if (isDuplicateName.value) return 'Not saved: that name is taken'
  if (form.hasErrors) return 'Not saved: fix the errors below'
  return null
})
</script>
