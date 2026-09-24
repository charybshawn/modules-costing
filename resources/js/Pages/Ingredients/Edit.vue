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
        <AdminMobileHeader title="Edit Ingredient" :on-back="() => shellRef?.guardNavigation(indexUrl)">
          <template #subtitle>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" :error="saveProblem" />
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
        <IconButton label="Delete ingredient" :class="dangerIconActionClass" @click="destroy">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </IconButton>
      </template>

      <template #header>
        <Link :href="indexUrl" :class="backLinkClass" @click.prevent="shellRef?.guardNavigation(indexUrl)">
          &larr; Back to Ingredients
        </Link>

        <div class="hidden md:flex flex-wrap items-center justify-between gap-3 mt-2 mb-6">
          <div class="flex items-center gap-3 min-w-0">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white truncate">{{ ingredient.name }}</h1>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" :error="saveProblem" />
          </div>
          <div class="flex items-center gap-3">
            <button type="button" :class="dangerOutlineButtonClass" @click="destroy">Delete Ingredient</button>
            <button type="button" :class="secondaryButtonClass" @click="closeEditor">Cancel</button>
          </div>
        </div>

        <FormErrorSummary v-if="Object.keys(form.errors).length > 0" :errors="form.errors" class="mb-6" />
      </template>

      <template #section-details>
        <IngredientFields :form="form" :categories="categories" />
      </template>

      <template #section-sources>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
          Where you buy this and what you're paying. Pick a wholesaler/brand as preferred to lock it in for recipes and the production planner, regardless of price.
        </p>
        <!-- mx-2: SourcesTable bleeds -mx-6 (sized for a p-6 card); the
             section body pads px-4, so this makes it flush with its edges. -->
        <div class="mx-2">
          <SourcesTable :ingredient="{ id: ingredient.id, name: ingredient.name, unit_type: ingredient.unit_type }" />
        </div>
      </template>
    </ResponsiveFormSections>
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
import IngredientFields, { type IngredientFormData } from '../Shared/IngredientFields.vue'
import SourcesTable from '../Shared/SourcesTable.vue'
import { backLinkClass, dangerIconActionClass, dangerOutlineButtonClass, iconActionClass, secondaryButtonClass } from '../Shared/formClasses'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface Ingredient {
  id: number
  name: string
  category: string | null
  unit_type: 'g' | 'unit'
  waste_percent: number
  byproduct_name: string | null
  notes: string | null
}

interface Props {
  ingredient: Ingredient
  categories: string[]
}

const props = defineProps<Props>()
const { confirmDialog } = useConfirmDialog()

const sections: FormSection[] = [
  { key: 'details', title: 'Details' },
  { key: 'sources', title: 'Sources & Prices', shortTitle: 'Sources' },
]

const shellRef = ref<InstanceType<typeof ResponsiveFormSections> | null>(null)
const indexUrl = route('admin.costing.ingredients.index')
const updateUrl = () => route('admin.costing.ingredients.update', props.ingredient.id)

const initialData: IngredientFormData = {
  name: props.ingredient.name,
  category: props.ingredient.category ?? '',
  unit_type: props.ingredient.unit_type,
  waste_percent: props.ingredient.waste_percent,
  byproduct_name: props.ingredient.byproduct_name ?? '',
  notes: props.ingredient.notes ?? '',
}

// What the × -> "Discard Changes" restores: the ingredient as it was when
// this page opened (autosave has already saved every edit since).
const openingData = JSON.parse(JSON.stringify(initialData)) as IngredientFormData

const form = usePersistedForm<IngredientFormData>(initialData, {
  key: `costing-ingredient-edit-${props.ingredient.id}`,
  initialData,
  autosave: {
    url: updateUrl,
    requiredFields: ['name', 'unit_type', 'waste_percent'],
    onSuccess: (): void => markSaved(),
  },
})

// Header × (Keep Editing / Keep … / Discard …) and session tracking.
// Sources and prices save through their own requests, so Discard only
// reverts the ingredient's own fields -- or, for an ingredient created this
// session, deletes it along with them.
const { initialStep, markSaved, closeEditor } = useAutosaveSession({
  form,
  noun: 'ingredient',
  exitUrl: () => indexUrl,
  updateUrl,
  discardDraftUrl: () => route('admin.costing.ingredients.discard-draft', props.ingredient.id),
  discardPayload: () => ({ ...openingData }),
})

const saveProblem = computed(() => {
  if (form.processing || !form.isDirty) return null
  if (!form.name?.trim()) return 'Not saved: name is required'
  if (form.hasErrors) return 'Not saved: fix the errors below'
  return null
})

const destroy = async () => {
  const confirmed = await confirmDialog({
    title: 'Delete Ingredient',
    message: `Delete "${props.ingredient.name}"? This also removes its price history and inventory record.`,
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!confirmed) return
  form.cancelAutosave()
  form.clearPersistedData()
  router.delete(route('admin.costing.ingredients.destroy', props.ingredient.id))
}
</script>
