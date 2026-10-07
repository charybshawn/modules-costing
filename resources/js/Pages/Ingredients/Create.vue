<template>
  <div>
    <ResponsiveFormSections
      ref="shellRef"
      :sections="sections"
      :dirty="form.isDirty"
      @discard="form.clearPersistedData()"
      @close="leave"
    >
      <template #mobile-header>
        <AdminMobileHeader title="New Ingredient" :on-back="leave">
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
        </AdminMobileHeader>
      </template>

      <template #header>
        <Link :href="indexUrl" :class="backLinkClass" @click.prevent="leave">&larr; Back to Ingredients</Link>
        <div class="hidden md:flex flex-wrap items-center justify-between gap-3 mt-2 mb-2">
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">New Ingredient</h1>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" :error="saveProblem" />
          </div>
          <button type="button" :class="primaryButtonClass" :disabled="form.processing" @click="saveAndClose">Save</button>
        </div>
        <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
          Saved as you go. Sources and prices can be added once it has a name, unless it's made in-house.
        </p>

        <FormErrorSummary v-if="Object.keys(form.errors).length > 0" :errors="form.errors" class="mb-6" />
      </template>

      <template #section-details>
        <IngredientFields :form="form" :categories="categories" />
        <div v-if="form.is_house_made" class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
          <h3 class="text-base font-medium text-gray-900 dark:text-white mb-4">Made from</h3>
          <HouseMadeFields :form="form" :pool="componentPool" />
        </div>
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
import SaveIndicator from '@/Components/Admin/SaveIndicator.vue'
import ResponsiveFormSections, { type FormSection } from '@/Components/Admin/ResponsiveFormSections.vue'
import IconButton from '@/Components/IconButton.vue'
import HouseMadeFields, { type ComponentOption } from '../Shared/HouseMadeFields.vue'
import IngredientFields, { type IngredientFormData } from '../Shared/IngredientFields.vue'
import { backLinkClass, iconActionClass, primaryButtonClass } from '../Shared/formClasses'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface Props {
  categories: string[]
  componentPool: ComponentOption[]
}

defineProps<Props>()

// Single section: no step bar (the Sources step only exists once the
// ingredient does -- on the Edit page this form hands off to).
const sections: FormSection[] = [{ key: 'details', title: 'Details' }]

const shellRef = ref<InstanceType<typeof ResponsiveFormSections> | null>(null)
const indexUrl = route('admin.costing.ingredients.index')

const form = usePersistedForm<IngredientFormData>({
  name: '',
  category: '',
  unit_type: 'g',
  waste_percent: 100,
  byproduct_name: '',
  notes: '',
  is_house_made: false,
  yield_g: null,
  components: [],
}, {
  key: 'costing-ingredient-create',
  // Autosave creates the ingredient: the first save POSTs to store, whose
  // `stay` branch redirects to the new ingredient's Edit page, and that
  // page's PUT autosave takes over from there.
  autosave: {
    method: 'post',
    url: () => `${route('admin.costing.ingredients.store')}?step=${shellRef.value?.currentStepIndex ?? 0}`,
    requiredFields: ['name'],
    // Don't create an ingredient named after the first keystroke.
    enabled: (): boolean => nameLongEnough.value,
  },
})

// Half-filled made-from rows stay on screen but aren't sent.
form.transform((data) => ({
  ...data,
  components: data.components.filter((row) => row.ingredient_id !== null && row.quantity_per_jar !== null),
}))

const nameLongEnough = computed<boolean>(() => (form.name ?? '').trim().length >= 2)

// Nothing saved yet, so leaving goes through the shell's unsaved-changes
// guard rather than the autosave session.
const leave = () => shellRef.value?.guardNavigation(indexUrl)

// Desktop Save: create the record now and go back to the index (the store
// action's non-`stay` branch), rather than waiting on autosave.
const saveAndClose = (): void => {
  form.cancelAutosave()
  form.post(route('admin.costing.ingredients.store'))
}

const saveProblem = computed(() => {
  if (form.processing || !form.isDirty) return null
  if (!nameLongEnough.value) return 'Not saved yet: add a name (2+ characters)'
  if (form.hasErrors) return 'Not saved: fix the errors below'
  return null
})
</script>
