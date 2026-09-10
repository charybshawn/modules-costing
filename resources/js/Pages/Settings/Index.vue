<template>
  <div class="py-6">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Costing Settings</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Configuration for this module only -- these don't affect the site-wide Settings page.
        </p>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <form @submit.prevent="submit" class="p-6 space-y-6">
          <FormErrorSummary :errors="form.errors" />

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Price update reminder (days)</label>
            <input
              v-model.number="form.staleness_days"
              type="number" inputmode="numeric"
              min="1"
              max="90"
              step="1"
              class="mt-1 block w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
              A logged price only counts toward an ingredient's current cost, and toward recipe/production costing,
              while it's this many days old or newer. Once it's older, it's flagged "needs update" on the Ingredients
              and Price History tables (and drops out of costing) until someone logs a fresher one.
            </p>
          </div>

          <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Batch code prefix</label>
            <input
              v-model="form.batch_code_prefix"
              type="text"
              maxlength="10"
              placeholder="e.g. CP"
              class="mt-1 block w-32 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase"
            />
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
              Letters/numbers only. A new production run's default name is generated from this as
              <span class="font-mono">{{ batchCodeExample }}</span> -- your facility/brand code, the run date, and a
              same-day sequence number -- so every batch gets a distinct, traceable code without typing one by hand.
              Leave blank to generate just <span class="font-mono">YYMMDD-01</span>. Always editable afterward.
            </p>
          </div>

          <div class="flex items-center justify-end pt-6 border-t border-gray-200 dark:border-gray-700">
            <button
              type="submit"
              :disabled="form.processing"
              class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
            >
              <span v-if="form.processing">Saving...</span>
              <span v-else>Save</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

defineOptions({ layout: AdminLayout })

interface Props {
  staleness_days: number
  batch_code_prefix: string | null
}

const props = defineProps<Props>()

const form = useForm({
  staleness_days: props.staleness_days,
  batch_code_prefix: props.batch_code_prefix ?? '',
})

const batchCodeExample = computed(() => `${(form.batch_code_prefix || 'CP').toUpperCase()}-260910-01`)

const submit = () => {
  form.put(route('admin.costing.settings.update'), { preserveScroll: true })
}
</script>
