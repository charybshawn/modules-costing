<template>
  <div>
    <ResponsiveFormSections
      ref="shellRef"
      :sections="sections"
      :dirty="form.isDirty"
      @discard="form.clearPersistedData()"
    >
      <template #mobile-header>
        <AdminMobileHeader title="Log a Price" :on-back="leave">
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
        <Link :href="indexUrl" :class="backLinkClass" @click.prevent="leave">&larr; Back to Price History</Link>
        <div class="hidden md:flex flex-wrap items-center justify-between gap-3 mt-2 mb-2">
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Log a Price</h1>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" :error="saveProblem" />
          </div>
          <button type="button" :class="secondaryButtonClass" @click="leave">Cancel</button>
        </div>
        <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">Log every wholesaler you check, even if you didn't buy. Saved as soon as the price is filled in.</p>

        <div v-if="clone" class="mb-6 rounded-md bg-indigo-50 dark:bg-indigo-900/20 p-4">
          <p class="text-sm text-indigo-800 dark:text-indigo-200">Copied from a previous entry: update the price and date.</p>
        </div>

        <FormErrorSummary v-if="Object.keys(form.errors).length > 0" :errors="form.errors" class="mb-6" />
      </template>

      <template #section-entry>
        <PriceEntryFields :form="form" :entry="entry" :ingredients="ingredients" />
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
import PriceEntryFields from '../Shared/PriceEntryFields.vue'
import { usePriceEntry, type PriceEntryFormData, type PriceEntryIngredient } from '../Shared/usePriceEntry'
import { backLinkClass, iconActionClass, secondaryButtonClass } from '../Shared/formClasses'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface CloneSource {
  ingredient_id: number
  package_size_id: number
  qty: number | null
  priced_as_case: boolean
  total_price: number | null
  sku: string | null
  notes: string | null
}

interface Props {
  ingredients: PriceEntryIngredient[]
  clone: CloneSource | null
  preselectIngredientId: number | null
}

const props = defineProps<Props>()

const sections: FormSection[] = [{ key: 'entry', title: 'Price' }]

const shellRef = ref<InstanceType<typeof ResponsiveFormSections> | null>(null)
const indexUrl = route('admin.costing.price-history.index')

// Cloning carries over everything except the date, which resets to today
// since it's a new price check. preselectIngredientId (arriving via
// ?ingredient=) covers "Log a new price" from an ingredient with none yet.
const form = usePersistedForm<PriceEntryFormData>({
  ingredient_id: props.clone?.ingredient_id ?? props.preselectIngredientId ?? '',
  package_size_id: props.clone?.package_size_id ?? '',
  purchased_at: new Date().toISOString().slice(0, 10),
  qty: props.clone?.qty ?? null,
  priced_as_case: props.clone?.priced_as_case ?? false,
  total_price: props.clone?.total_price ?? null,
  sku: props.clone?.sku ?? '',
  notes: props.clone?.notes ?? '',
}, {
  key: 'costing-price-history-create',
  // Autosave logs the entry: the first save POSTs to store, whose `stay` +
  // `step` branch redirects to the new entry's Edit page, and that page's
  // PUT autosave takes over from there.
  autosave: {
    method: 'post',
    url: () => `${route('admin.costing.price-history.store')}?step=${shellRef.value?.currentStepIndex ?? 0}`,
    requiredFields: ['ingredient_id', 'package_size_id'],
    // A price entry without a price or quantity would become the
    // ingredient's "latest price" and skew its costing -- wait for both.
    enabled: (): boolean => hasPriceAndQty.value && !entry.addingSource,
  },
})

// A cloned gram-based entry's qty is stored in grams, so it's shown in g;
// only a blank form defaults the weight unit to kg.
const entry = usePriceEntry(form, () => props.ingredients, {
  initialGrams: props.clone?.qty ?? null,
  defaultUnit: props.clone?.qty != null ? 'g' : 'kg',
})

const hasPriceAndQty = computed<boolean>(() => form.total_price !== null && (form.total_price as unknown) !== '' && !!form.qty)

// Nothing saved yet, so leaving goes through the shell's unsaved-changes
// guard rather than the autosave session.
const leave = () => shellRef.value?.guardNavigation(indexUrl)

const saveProblem = computed(() => {
  if (form.processing || !form.isDirty) return null
  if (!form.ingredient_id || !form.package_size_id) return 'Not saved yet: pick an ingredient and source'
  if (!hasPriceAndQty.value) return 'Not saved yet: add the quantity and price'
  if (form.hasErrors) return 'Not saved: fix the errors below'
  return null
})
</script>
