<template>
  <Modal :show="props.ingredient !== null" max-width="2xl" @close="$emit('close')">
    <div v-if="props.ingredient" class="p-6">
      <h2 class="text-lg font-medium text-gray-900 dark:text-white">Sources -- {{ props.ingredient.name }}</h2>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Pick a wholesaler/brand to lock in as the preferred source for recipes and the production planner, regardless of price.
      </p>

      <SourcesTable :ingredient="props.ingredient" />

      <div class="mt-6 flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
        <button type="button" @click="$emit('close')" class="bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
          Close
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import Modal from '@/Components/Modal.vue'
import SourcesTable, { type SourcesIngredient } from './SourcesTable.vue'

export type PricesIngredient = SourcesIngredient

interface Props {
  ingredient: PricesIngredient | null
}

const props = defineProps<Props>()

defineEmits<{ close: [] }>()
</script>
