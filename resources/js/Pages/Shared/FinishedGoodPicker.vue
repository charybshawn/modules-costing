<template>
  <div ref="rootRef" class="relative">
    <input
      v-model="query"
      type="text"
      :placeholder="placeholder ?? 'Not linked to a storefront product'"
      class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
      @focus="onFocus"
      @input="onInput"
    />
    <button
      v-if="modelValue !== null"
      type="button"
      title="Unlink"
      class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400"
      @click="clear"
    >
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>

    <div
      v-if="open"
      class="absolute z-10 mt-1 w-full max-h-60 overflow-y-auto bg-white dark:bg-gray-700 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 dark:ring-white/10"
    >
      <div v-if="loading" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">Searching...</div>
      <div v-else-if="results.length === 0" class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">No matches.</div>
      <button
        v-for="option in results"
        :key="option.id"
        type="button"
        class="block w-full text-left px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-600"
        @click="select(option)"
      >
        <span class="block text-sm text-gray-700 dark:text-gray-200">{{ option.label }}</span>
        <span v-if="option.sublabel" class="block text-xs text-gray-500 dark:text-gray-400">{{ option.sublabel }}</span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import axios from 'axios'

export interface FinishedGoodOption {
  id: number
  label: string
  sublabel?: string | null
}

interface Props {
  modelValue: number | null
  initialLabel?: string | null
  placeholder?: string
}

const props = defineProps<Props>()

const emit = defineEmits<{
  'update:modelValue': [value: number | null]
}>()

const query = ref(props.initialLabel ?? '')
const results = ref<FinishedGoodOption[]>([])
const loading = ref(false)
const open = ref(false)
const rootRef = ref<HTMLElement | null>(null)

// Keeps the displayed text in sync if the caller resets modelValue/initialLabel
// out from under us (e.g. after a save that changes the linked recipe).
watch(
  () => props.initialLabel,
  (label) => {
    if (props.modelValue !== null) {
      query.value = label ?? ''
    }
  },
)

let debounceTimer: ReturnType<typeof setTimeout> | null = null

const runSearch = async (q: string) => {
  loading.value = true
  try {
    const { data } = await axios.get(route('admin.costing.recipes.finished-goods.search'), { params: { q } })
    results.value = data.results
  } finally {
    loading.value = false
  }
}

const onInput = () => {
  // Typing invalidates whatever was previously selected until a new option
  // is clicked -- otherwise a half-edited label could be submitted while
  // modelValue still points at the old selection.
  if (props.modelValue !== null) {
    emit('update:modelValue', null)
  }
  open.value = true
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => runSearch(query.value), 275)
}

const onFocus = () => {
  open.value = true
  if (results.value.length === 0 && !loading.value) {
    runSearch(query.value)
  }
}

const select = (option: FinishedGoodOption) => {
  emit('update:modelValue', option.id)
  query.value = option.label
  open.value = false
}

const clear = () => {
  emit('update:modelValue', null)
  query.value = ''
  results.value = []
}

const onClickOutside = (event: MouseEvent) => {
  if (rootRef.value && !rootRef.value.contains(event.target as Node)) {
    open.value = false
  }
}

onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>
