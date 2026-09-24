<template>
  <div>
    <CostingModuleNav />
    <SettingsPageShell
      title="Costing Settings"
      description="Configuration for this module only. These don't affect the site-wide Settings page."
      :tabs="false"
    >
      <template #mobile-header>
        <AdminMobileHeader title="Costing Settings">
          <template #subtitle>
            <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" :error="saveProblem" />
          </template>
        </AdminMobileHeader>
      </template>

      <template #status>
        <SaveIndicator :processing="form.processing" :recently-successful="form.recentlySuccessful" :error="saveProblem" />
      </template>

      <!-- pb-28 on mobile clears CostingModuleNav's fixed bottom bar. -->
      <form class="space-y-6 pb-28 md:pb-0" @submit.prevent>
        <FormErrorSummary v-if="Object.keys(form.errors).length > 0" :errors="form.errors" />

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price update reminder (days) *</label>
          <input
            v-model.number="form.staleness_days"
            type="number"
            inputmode="numeric"
            min="1"
            max="90"
            step="1"
            required
            :class="[inputClass, '!w-32']"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Between 1 and 90. A logged price only counts toward an ingredient's current cost, and toward recipe/production
            costing, while it's this many days old or newer. Older prices are flagged "needs update" on the Ingredients and
            Price History tables (and drop out of costing) until someone logs a fresher one.
          </p>
          <InputError :message="form.errors.staleness_days" />
        </div>

        <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Batch code prefix <span class="font-normal text-gray-500 dark:text-gray-400">Optional</span></label>
          <input
            v-model="form.batch_code_prefix"
            type="text"
            maxlength="10"
            placeholder="e.g. CP"
            :class="[inputClass, '!w-32 uppercase']"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Letters and numbers only, up to 10. A new production run's default name is generated from this as
            <span class="font-mono">{{ batchCodeExample }}</span>: your facility/brand code, the run date, and a same-day
            sequence number. Leave blank for just <span class="font-mono">YYMMDD-01</span>. Always editable afterward.
          </p>
          <InputError :message="form.errors.batch_code_prefix" />
        </div>
      </form>
    </SettingsPageShell>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { usePersistedForm } from '@/composables/usePersistedForm'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import SaveIndicator from '@/Components/Admin/SaveIndicator.vue'
import SettingsPageShell from '@/Components/Admin/Settings/SettingsPageShell.vue'
import InputError from '@/Components/InputError.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import { inputClass } from '../Shared/formClasses'

defineOptions({ layout: (h, page) => h(AdminLayout, { hideBreadcrumbOnMobile: true }, () => page) })

interface Props {
  staleness_days: number
  batch_code_prefix: string | null
}

const props = defineProps<Props>()

interface FormData {
  staleness_days: number
  batch_code_prefix: string
}

const initialData: FormData = {
  staleness_days: props.staleness_days,
  batch_code_prefix: props.batch_code_prefix ?? '',
}

// Settings save as they change (FORM_DESIGN.md → Settings pages).
const form = usePersistedForm<FormData>(initialData, {
  key: 'costing-settings',
  initialData,
  autosave: {
    url: () => route('admin.costing.settings.update'),
    requiredFields: ['staleness_days'],
    enabled: (): boolean => prefixValid.value,
  },
})

const prefixValid = computed(() => /^[A-Za-z0-9]*$/.test(form.batch_code_prefix ?? ''))

const saveProblem = computed(() => {
  if (form.processing || !form.isDirty) return null
  if (!form.staleness_days) return 'Not saved: enter a number of days'
  if (!prefixValid.value) return 'Not saved: letters and numbers only'
  if (form.hasErrors) return 'Not saved: fix the errors below'
  return null
})

const batchCodeExample = computed(() => `${(form.batch_code_prefix || 'CP').toUpperCase()}-260910-01`)
</script>
