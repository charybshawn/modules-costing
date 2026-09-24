<template>
  <div>
    <ResponsiveFormSections
      ref="shellRef"
      :sections="sections"
      :dirty="form.isDirty"
      :initial-step="initialStep"
      @discard="form.clearPersistedData()"
    >
      <template #mobile-header>
        <AdminMobileHeader title="Edit Price" :on-back="() => shellRef?.guardNavigation(showUrl())">
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
        </AdminMobileHeader>
      </template>

      <template #mobile-actions>
        <IconButton label="View price entry" :class="iconActionClass" @click="shellRef?.guardNavigation(showUrl())">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
        </IconButton>
        <IconButton label="Delete price entry" :class="dangerIconActionClass" @click="destroy">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
          </svg>
        </IconButton>
      </template>

      <template #header>
        <Link :href="showUrl()" :class="backLinkClass" @click.prevent="shellRef?.guardNavigation(showUrl())">
          &larr; Back to Entry
        </Link>
        <div class="hidden md:flex flex-wrap items-center justify-between gap-3 mt-2 mb-6">
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Edit Price Entry</h1>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" :error="saveProblem" />
          </div>
          <div class="flex items-center gap-3">
            <button type="button" :class="secondaryButtonClass" @click="shellRef?.guardNavigation(showUrl())">View Entry</button>
            <button type="button" :class="dangerOutlineButtonClass" @click="destroy">Delete Entry</button>
            <button type="button" :class="secondaryButtonClass" @click="closeEditor">Cancel</button>
          </div>
        </div>

        <FormErrorSummary v-if="Object.keys(form.errors).length > 0" :errors="form.errors" class="mb-6" />
      </template>

      <template #section-entry>
        <PriceEntryFields
          :form="form"
          :entry="entry"
          :ingredients="ingredients"
          missing-source-hint="Pick a source to save -- this entry's original source was likely removed."
        />
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
import SaveIndicator from '@/Components/Admin/SaveIndicator.vue'
import ResponsiveFormSections, { type FormSection } from '@/Components/Admin/ResponsiveFormSections.vue'
import IconButton from '@/Components/IconButton.vue'
import PriceEntryFields from '../Shared/PriceEntryFields.vue'
import { usePriceEntry, type PriceEntryFormData, type PriceEntryIngredient } from '../Shared/usePriceEntry'
import { backLinkClass, dangerIconActionClass, dangerOutlineButtonClass, iconActionClass, secondaryButtonClass } from '../Shared/formClasses'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface Entry {
  id: number
  ingredient_id: number
  package_size_id: number | null
  purchased_at: string | null
  qty: number | null
  priced_as_case: boolean
  total_price: number | null
  sku: string | null
  notes: string | null
}

interface Props {
  entry: Entry
  ingredients: PriceEntryIngredient[]
}

const props = defineProps<Props>()
const { confirmDialog } = useConfirmDialog()

const sections: FormSection[] = [{ key: 'entry', title: 'Price' }]

const shellRef = ref<InstanceType<typeof ResponsiveFormSections> | null>(null)
const indexUrl = route('admin.costing.price-history.index')
const showUrl = () => route('admin.costing.price-history.show', props.entry.id)
const updateUrl = () => route('admin.costing.price-history.update', props.entry.id)

const initialData: PriceEntryFormData = {
  ingredient_id: props.entry.ingredient_id,
  package_size_id: props.entry.package_size_id ?? '',
  purchased_at: props.entry.purchased_at ?? '',
  qty: props.entry.qty,
  priced_as_case: props.entry.priced_as_case,
  total_price: props.entry.total_price,
  sku: props.entry.sku ?? '',
  notes: props.entry.notes ?? '',
}

// What the × -> "Discard Changes" restores.
const openingData = JSON.parse(JSON.stringify(initialData)) as PriceEntryFormData

const form = usePersistedForm<PriceEntryFormData>(initialData, {
  key: `costing-price-history-edit-${props.entry.id}`,
  initialData,
  autosave: {
    url: updateUrl,
    requiredFields: ['ingredient_id', 'package_size_id'],
    enabled: (): boolean => !entry.addingSource,
    onSuccess: (): void => markSaved(),
  },
})

// Seeded from form.qty (not props.entry.qty) so a restored localStorage
// draft is what's shown; stored in grams, so shown in g.
const entry = usePriceEntry(form, () => props.ingredients, { initialGrams: form.qty, defaultUnit: 'g' })

const { initialStep, markSaved, closeEditor } = useAutosaveSession({
  form,
  noun: 'entry',
  exitUrl: () => indexUrl,
  updateUrl,
  discardDraftUrl: () => route('admin.costing.price-history.discard-draft', props.entry.id),
  discardPayload: () => ({ ...openingData }),
})

const saveProblem = computed(() => {
  if (form.processing || !form.isDirty) return null
  if (!form.ingredient_id || !form.package_size_id) return 'Not saved: pick an ingredient and source'
  if (form.hasErrors) return 'Not saved: fix the errors below'
  return null
})

const destroy = async () => {
  const confirmed = await confirmDialog({
    title: 'Delete Price Entry',
    message: 'Delete this price entry? This cannot be undone.',
    confirmLabel: 'Delete',
    variant: 'danger',
  })
  if (!confirmed) return
  form.cancelAutosave()
  form.clearPersistedData()
  router.delete(route('admin.costing.price-history.destroy', props.entry.id))
}
</script>
