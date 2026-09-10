<template>
  <Modal :show="show" max-width="md" @close="close">
    <form @submit.prevent="submit" class="p-6">
      <h2 class="text-lg font-medium text-gray-900 dark:text-white">New Production Run</h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Pick what kind of kitchen session this is and when it's happening -- everything else (batches, notes, a rental slot) is set up next.
      </p>

      <p v-if="error" class="mt-4 text-sm text-red-600 dark:text-red-400">{{ error }}</p>

      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
        <select v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
          <option v-for="type in runTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
        </select>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ typeHint }}</p>
      </div>

      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date *</label>
        <input v-model="form.run_date" type="date" required autofocus class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
      </div>

      <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
        <button type="button" @click="close" class="tap-target-touch bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
          Cancel
        </button>
        <button type="submit" :disabled="creating" class="tap-target-touch bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
          <span v-if="creating">Creating...</span>
          <span v-else>Create</span>
        </button>
      </div>
    </form>
  </Modal>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import Modal from '@/Components/Modal.vue'

interface Props {
  show: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{ close: []; created: [productionRunId: number] }>()

const runTypes = [
  { value: 'production', label: 'Production' },
  { value: 'prep', label: 'Ingredient Prep' },
  { value: 'development', label: 'R&D' },
]

const typeHints: Record<string, string> = {
  production: 'A real batch run -- flavours, batch size, inventory deducted on completion.',
  prep: 'An in-house component prep session (e.g. caramelizing onions) -- just a date and notes, no batches.',
  development: 'Flavour testing / R&D -- optionally note which recipes, no production commitment.',
}
const typeHint = computed(() => typeHints[form.value.type] ?? '')

const defaultForm = () => ({ type: 'production', run_date: '' })
const form = ref(defaultForm())
const creating = ref(false)
const error = ref<string | null>(null)

watch(
  () => props.show,
  (show) => {
    if (show) {
      form.value = defaultForm()
      error.value = null
    }
  },
)

const close = () => {
  if (creating.value) return
  emit('close')
}

const submit = async () => {
  creating.value = true
  error.value = null
  try {
    const { data } = await axios.post(route('admin.costing.production-planner.store'), { ...form.value })
    emit('created', data.production_run_id)
  } catch (e) {
    error.value = 'Could not create this run -- check the date and try again.'
  } finally {
    creating.value = false
  }
}
</script>
