<template>
  <div class="py-6">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <CostingModuleNav />
      <div class="md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Cost History</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            A snapshot is recorded automatically every time a production run is completed -- this is what those look like over time.
          </p>
        </div>
        <Link :href="route('admin.costing.recipes.costing')" class="mt-4 md:mt-0 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
          Costing
        </Link>
      </div>

      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <div class="flex flex-wrap gap-4 mb-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Recipe</label>
            <select v-model.number="selectedRecipeId" class="mt-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
              <option v-for="recipe in recipes" :key="recipe.id" :value="recipe.id">{{ recipe.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Metric</label>
            <select v-model="selectedMetric" class="mt-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
              <option v-for="option in metricOptions" :key="option.key" :value="option.key">{{ option.label }}</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Granularity</label>
            <select v-model="selectedGranularity" class="mt-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
              <option value="yearly">Yearly</option>
            </select>
          </div>
        </div>

        <div v-if="chartPoints.length < 2" class="py-16 text-center text-sm text-gray-500 dark:text-gray-400">
          No cost history yet for this recipe -- snapshots are created automatically each time a production run is completed.
        </div>
        <div v-else class="h-96">
          <Line :data="chartData" :options="chartOptions" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Legend,
} from 'chart.js'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend)

defineOptions({ layout: AdminLayout })

interface RecipeOption {
  id: number
  name: string
}

interface SnapshotRow {
  id: number
  recipe_id: number
  recipe_name: string
  jars_produced: number
  created_at: string
  raw_cost: number
  buffered_cost: number
  actual_cost_per_jar: number
  food_cost_percent: number | null
  any_stale: boolean
  any_missing: boolean
}

interface Props {
  recipes: RecipeOption[]
  snapshots: SnapshotRow[]
}

const props = defineProps<Props>()

const selectedRecipeId = ref<number | null>(props.recipes[0]?.id ?? null)

type MetricKey = 'food_cost_percent' | 'raw_cost' | 'buffered_cost' | 'actual_cost_per_jar'

const metricOptions: Array<{ key: MetricKey; label: string }> = [
  { key: 'food_cost_percent', label: 'Food Cost %' },
  { key: 'raw_cost', label: 'Raw Cost' },
  { key: 'buffered_cost', label: 'Buffered Cost' },
  { key: 'actual_cost_per_jar', label: 'Cost per Jar' },
]

const selectedMetric = ref<MetricKey>('food_cost_percent')

type Granularity = 'daily' | 'weekly' | 'monthly' | 'yearly'

const selectedGranularity = ref<Granularity>('monthly')

// Groups a date into a bucket key for the chosen granularity. Weekly buckets
// use the Monday of that week; the others truncate to the obvious unit.
const bucketKey = (date: Date, granularity: Granularity): string => {
  const year = date.getFullYear()
  const month = date.getMonth() + 1
  const day = date.getDate()

  if (granularity === 'yearly') return `${year}`
  if (granularity === 'monthly') return `${year}-${String(month).padStart(2, '0')}`
  if (granularity === 'daily') return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`

  // Weekly: bucket by the Monday of the ISO week.
  const monday = new Date(date)
  const dayOfWeek = (monday.getDay() + 6) % 7 // Mon=0 .. Sun=6
  monday.setDate(monday.getDate() - dayOfWeek)
  return `${monday.getFullYear()}-${String(monday.getMonth() + 1).padStart(2, '0')}-${String(monday.getDate()).padStart(2, '0')}`
}

// Snapshots for the selected recipe, in chronological order, bucketed by
// the chosen granularity -- the last snapshot within each bucket wins (the
// standard "as of end of period" convention for a cost trend line).
const chartPoints = computed(() => {
  const filtered = props.snapshots
    .filter((s) => s.recipe_id === selectedRecipeId.value)
    .sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())

  const buckets = new Map<string, SnapshotRow>()
  for (const snapshot of filtered) {
    const key = bucketKey(new Date(snapshot.created_at), selectedGranularity.value)
    buckets.set(key, snapshot) // later entries overwrite earlier ones within the same bucket
  }

  return [...buckets.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([key, snapshot]) => ({ key, snapshot }))
})

const selectedMetricLabel = computed(() => metricOptions.find((m) => m.key === selectedMetric.value)?.label ?? '')

const chartData = computed(() => ({
  labels: chartPoints.value.map((p) => p.key),
  datasets: [
    {
      label: selectedMetricLabel.value,
      data: chartPoints.value.map((p) => p.snapshot[selectedMetric.value] ?? null),
      borderColor: '#4f46e5',
      backgroundColor: '#4f46e5',
      tension: 0.2,
      spanGaps: true,
    },
  ],
}))

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
}
</script>
