<template>
  <div class="py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Recipes</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Flavours and their ingredient weights per jar.</p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
          <Link :href="route('admin.costing.recipes.grid')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Grid View
          </Link>
          <Link :href="route('admin.costing.production-planner.runs')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Production Runs
          </Link>
          <Link :href="route('admin.costing.recipes.costing')" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Costing
          </Link>
          <Link :href="route('admin.costing.recipes.create')" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Add Recipe
          </Link>
        </div>
      </div>

      <div v-if="$page.props.flash?.success" class="mb-6 rounded-md bg-green-50 dark:bg-green-900/20 p-4">
        <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ $page.props.flash.success }}</p>
      </div>

      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden">
        <DataTable
          :columns="columns"
          :items="recipes"
          :actions="tableActions"
          searchable
          search-placeholder="Search recipes..."
          empty-message="No recipes yet."
          empty-action-label="Add your first recipe"
          :empty-action-href="route('admin.costing.recipes.create')"
          table-id="costing-recipes"
          item-key="id"
          @action="handleAction"
        >
          <template #cell-ingredients_count="{ item }">
            <span class="text-sm text-gray-900 dark:text-white">{{ item.ingredients_count }} ingredient(s)</span>
          </template>
        </DataTable>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DataTable, { type Column, type Action } from '@/Components/Admin/DataTable.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

defineOptions({ layout: AdminLayout })

interface Recipe {
  id: number
  name: string
  notes: string | null
  ingredients_count: number
}

interface Props {
  recipes: Recipe[]
}

defineProps<Props>()

const columns: Column[] = [
  { key: 'name', label: 'Flavour', sortable: true },
  { key: 'ingredients_count', label: 'Ingredients' },
  { key: 'notes', label: 'Notes', hideable: true },
]

const tableActions: Action[] = [
  { name: 'edit', icon: 'edit', color: 'indigo', label: 'Edit', href: (item) => route('admin.costing.recipes.edit', item.id) },
  { name: 'delete', icon: 'delete', color: 'red', label: 'Delete' },
]

const handleAction = (action: string, item: Recipe) => {
  if (action === 'delete') {
    if (confirm(`Delete "${item.name}"? This also removes it from any production runs.`)) {
      router.delete(route('admin.costing.recipes.destroy', item.id), { preserveScroll: true })
    }
  }
}
</script>
