<template>
  <ResponsiveModal :show="props.ingredient !== null" max-width="2xl" @close="$emit('close')">
    <template #desktop>
      <div v-if="props.ingredient" class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Sources -- {{ props.ingredient.name }}</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          Pick a wholesaler/brand to lock in as the preferred source for recipes and the production planner, regardless of price.
        </p>

        <SourcesTable :ingredient="props.ingredient" />

        <div class="mt-6 flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
          <button type="button" @click="$emit('close')" class="tap-target-touch bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
            Close
          </button>
        </div>
      </div>
    </template>

    <template #mobile>
      <div v-if="props.ingredient" class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto px-4 -mt-2">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Sources -- {{ props.ingredient.name }}</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Pick a wholesaler/brand to lock in as the preferred source for recipes and the production planner, regardless of price.
          </p>

          <!-- Every row here is inline-editable (rename, resize, reprice),
               not a plain lookup, so this stays 'card' (SourcesTable's own
               default) rather than 'line' -- a full-screen takeover has
               plenty of vertical room, unlike a bottom sheet, so there's
               no space pressure forcing the denser style anyway. -->
          <SourcesTable :ingredient="props.ingredient" />
        </div>

        <div class="shrink-0 p-4 pb-[calc(1rem+env(safe-area-inset-bottom))] border-t border-gray-200 dark:border-gray-700">
          <button type="button" @click="$emit('close')" class="tap-target-touch w-full bg-white dark:bg-gray-700 py-3 px-4 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-200">
            Close
          </button>
        </div>
      </div>
    </template>
  </ResponsiveModal>
</template>

<script setup lang="ts">
import ResponsiveModal from '@/Components/ResponsiveModal.vue'
import SourcesTable, { type SourcesIngredient } from './SourcesTable.vue'

export type PricesIngredient = SourcesIngredient

interface Props {
  ingredient: PricesIngredient | null
}

const props = defineProps<Props>()

defineEmits<{ close: [] }>()
</script>
