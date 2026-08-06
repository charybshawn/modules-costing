<template>
  <div class="py-6">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Update Stock: {{ ingredient.name }}</h1>
          </div>
          <Link :href="route('admin.costing.inventory.index')" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">&larr; Back</Link>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-6">
          <FormErrorSummary :errors="form.errors" />

          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stock on Hand</label>
            <p class="text-sm text-gray-500 dark:text-gray-400">Count what's physically in front of you -- no conversion needed.</p>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead>
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Source</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"># on Hand</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subtotal</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="row in countRows" :key="row.key">
                  <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                    {{ row.label }}
                    <span class="text-gray-500 dark:text-gray-400">({{ row.package_size }}{{ unitSuffix }} each)</span>
                  </td>
                  <td class="px-3 py-2">
                    <input v-model.number="brandCounts[row.key]" type="number" min="0" step="1" class="w-20 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                  </td>
                  <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">{{ ((brandCounts[row.key] || 0) * row.package_size).toFixed(2) }}{{ unitSuffix }}</td>
                </tr>
                <tr>
                  <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">Loose / partial</td>
                  <td class="px-3 py-2" colspan="2">
                    <input v-model.number="looseAmount" type="number" min="0" step="0.01" class="w-24 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    {{ unitSuffix }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="rounded-md bg-gray-50 dark:bg-gray-700/50 p-4">
            <p class="text-sm text-gray-700 dark:text-gray-300">
              On Hand: <span class="font-semibold">{{ (form.counted_on_hand ?? 0).toFixed(2) }} {{ ingredient.unit_type === 'unit' ? 'unit(s)' : 'g' }}</span>
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
            <input v-model="form.notes" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Expiry dates, location notes, etc." />
          </div>

          <div class="flex items-center justify-end pt-6 border-t border-gray-200 dark:border-gray-700">
            <Link :href="route('admin.costing.inventory.index')" class="bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">Cancel</Link>
            <button type="submit" :disabled="form.processing" class="ml-3 bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
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
import { computed, reactive, ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { usePersistedForm } from '@/composables/usePersistedForm'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'

defineOptions({ layout: AdminLayout })

interface PackageSizeOption {
  provider: string
  brand: string | null
  package_size: number
}

interface Props {
  ingredient: { id: number; name: string; unit_type: 'g' | 'unit' }
  inventory: { counted_on_hand: number | null; notes: string | null }
  package_sizes: PackageSizeOption[]
  weight_per_unit: number | null
}

const props = defineProps<Props>()

const unitSuffix = computed(() => (props.ingredient.unit_type === 'unit' ? ' unit(s)' : 'g'))

// The walk-in count table's rows: one per known per-brand package size,
// plus a generic "Other" row sourced from Ingredient.weight_per_unit (the
// ingredient-level "typical size" -- reused here rather than duplicated,
// since it already exists for exactly this purpose). If neither exists,
// the table degrades to just the Loose/partial row below -- equivalent to
// typing the raw total directly, no separate code path needed.
interface CountRow {
  key: string
  label: string
  package_size: number
}

const countRows = computed<CountRow[]>(() => {
  const rows: CountRow[] = props.package_sizes.map((size) => ({
    key: `${size.provider}|${size.brand ?? ''}`,
    label: size.brand ? `${size.provider} -- ${size.brand}` : size.provider,
    package_size: size.package_size,
  }))

  if (props.weight_per_unit !== null) {
    rows.push({ key: '__other__', label: 'Other (typical size)', package_size: props.weight_per_unit })
  }

  return rows
})

interface FormData {
  counted_on_hand: number | null
  notes: string
}

const initialData: FormData = {
  counted_on_hand: props.inventory.counted_on_hand,
  notes: props.inventory.notes ?? '',
}

const form = usePersistedForm<FormData>(initialData, {
  key: `costing-inventory-edit-${props.ingredient.id}`,
  initialData,
})

// Starts empty every visit -- this is a fresh physical count, not a
// running balance. Not touching any row (e.g. an edit that only changes
// Notes) never fires the watcher below, so form.counted_on_hand stays
// exactly what was loaded -- nothing gets zeroed out just by opening this
// page.
const brandCounts = reactive<Record<string, number>>({})
const looseAmount = ref(0)

const brandCountTotal = computed(() =>
  countRows.value.reduce((sum, row) => sum + (brandCounts[row.key] || 0) * row.package_size, 0) + (looseAmount.value || 0)
)

watch(brandCountTotal, (total) => {
  form.counted_on_hand = Math.round(total * 100) / 100
})

const submit = () => {
  form.put(route('admin.costing.inventory.update', props.ingredient.id))
}
</script>
