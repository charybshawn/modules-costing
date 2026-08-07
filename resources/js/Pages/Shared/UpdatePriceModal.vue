<template>
  <Modal :show="props.entry !== null" max-width="md" @close="$emit('close')">
    <form v-if="props.entry" @submit.prevent="submit" class="p-6">
      <h2 class="text-lg font-medium text-gray-900">Update Price</h2>
      <p class="mt-1 text-sm text-gray-500">
        Logs a new entry today for {{ props.entry.ingredient_name }} from {{ props.entry.provider }}<span v-if="props.entry.brand"> ({{ props.entry.brand }})</span>, same quantity as before -- just the new price.
      </p>

      <div v-if="props.entry.qty !== null" class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">
        Weight/quantity: <span class="font-medium">{{ formatQuantity(props.entry.qty ?? 0, props.entry.unit_type ?? 'g') }}</span>
      </div>

      <FormErrorSummary :errors="form.errors" class="mt-4" />

      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700">New Total Price ($) *</label>
        <input
          v-model.number="form.total_price"
          type="number"
          min="0"
          step="0.01"
          required
          autofocus
          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
        />
      </div>

      <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200">
        <button type="button" @click="$emit('close')" class="bg-gray-200 py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50">
          Cancel
        </button>
        <button type="submit" :disabled="form.processing" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
          <span v-if="form.processing">Saving...</span>
          <span v-else>Save</span>
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import Modal from '@/Components/Modal.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import { formatQuantity } from './formatWeight'

export interface UpdatePriceEntry {
  id: number
  ingredient_name: string
  provider: string
  brand: string | null
  qty: number | null
  unit_type: 'g' | 'unit' | null
}

interface Props {
  entry: UpdatePriceEntry | null
}

const props = defineProps<Props>()

const emit = defineEmits<{ close: []; updated: [] }>()

const form = useForm<{ total_price: number | null }>({ total_price: null })

watch(
  () => props.entry,
  (entry) => {
    if (entry) {
      form.reset()
      form.clearErrors()
    }
  },
)

const submit = () => {
  if (!props.entry) return
  form.post(route('admin.costing.price-history.update-price', props.entry.id), {
    preserveScroll: true,
    onSuccess: () => emit('updated'),
  })
}
</script>
