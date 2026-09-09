<template>
  <div v-if="count > 0" class="flex items-center justify-between gap-3 px-6 py-3 bg-indigo-50 dark:bg-indigo-900/20 border-b border-gray-200 dark:border-gray-700">
    <span class="text-sm text-indigo-700 dark:text-indigo-300">{{ count }} {{ count === 1 ? singular : plural }} selected</span>
    <div class="flex items-center gap-2">
      <slot />
      <button type="button" @click="$emit('clear')" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
        Clear
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
// Pairs with DataTable's existing `selectable`/`selected-ids` support (see
// Admin/Categories/Index.vue in the host app for the pattern this mirrors)
// -- a small shared bar so every costing table's bulk-action row doesn't
// re-implement the same "N selected / actions / Clear" markup separately.
interface Props {
  count: number
  singular: string
  plural: string
}

defineProps<Props>()
defineEmits<{ clear: [] }>()
</script>
