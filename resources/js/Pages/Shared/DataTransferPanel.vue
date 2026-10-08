<template>
  <section id="import-export" class="scroll-mt-20 bg-white dark:bg-gray-800 md:rounded-lg md:shadow-sm md:border md:border-gray-200 md:dark:border-gray-700">
    <h2 class="px-4 md:px-5 pt-5 pb-1 text-base font-semibold text-gray-900 dark:text-white">Import / Export</h2>
    <p class="px-4 md:px-5 text-sm text-gray-500 dark:text-gray-400">
      Move costing data between installs or keep a backup, as one JSON file. Records are matched by name.
    </p>

    <div class="grid gap-6 md:grid-cols-2 px-4 md:px-5 py-5">
      <!-- Export -->
      <div>
        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Export</h3>
        <fieldset class="mt-2 space-y-1">
          <legend class="sr-only">Sections to export</legend>
          <label v-for="section in SECTIONS" :key="section.key" class="tap-target-touch flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input v-model="exportSections" type="checkbox" :value="section.key" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
            {{ section.label }}
          </label>
        </fieldset>
        <a
          :href="exportUrl"
          :class="[primaryButton, exportSections.length ? '' : 'pointer-events-none opacity-50']"
          :aria-disabled="!exportSections.length"
          class="mt-3"
        >Download export</a>
      </div>

      <!-- Import -->
      <div>
        <h3 class="text-sm font-medium text-gray-900 dark:text-white">Import</h3>

        <fieldset class="mt-2 space-y-1">
          <legend class="sr-only">If something already exists</legend>
          <label class="tap-target-touch flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input v-model="mode" type="radio" value="merge" class="mt-0.5 border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" />
            <span><span class="font-medium">Merge</span>: update what exists, add what's new, delete nothing</span>
          </label>
          <label class="tap-target-touch flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input v-model="mode" type="radio" value="wipe" class="mt-0.5 border-gray-300 dark:border-gray-600 text-red-600 focus:ring-red-500 dark:bg-gray-700" />
            <span><span class="font-medium">Wipe &amp; replace</span>: empty the chosen sections first, then fill them from the file</span>
          </label>
        </fieldset>

        <label class="mt-3 block">
          <span class="sr-only">Export file to import</span>
          <input
            ref="fileInput"
            type="file"
            accept="application/json,.json"
            class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 dark:file:bg-gray-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 dark:file:text-gray-200"
            @change="chooseFile"
          />
        </label>
        <p v-if="loading" class="mt-2 text-sm text-gray-500 dark:text-gray-400">Reading file…</p>
        <p v-if="error" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ error }}</p>
      </div>
    </div>

    <!-- Preview: nothing has changed yet. -->
    <div v-if="upload" class="border-t border-gray-200 dark:border-gray-700 px-4 md:px-5 py-5">
      <h3 class="text-sm font-medium text-gray-900 dark:text-white">Preview: nothing has changed yet</h3>
      <p v-if="upload.exported_at" class="text-xs text-gray-500 dark:text-gray-400">File exported {{ upload.exported_at }}</p>

      <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-700">
        <li v-for="section in sectionsInFile" :key="section.key" class="py-3">
          <label class="flex items-start gap-3">
            <input v-model="importSections" type="checkbox" :value="section.key" class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700" @change="refreshPreview" />
            <span class="min-w-0 flex-1 text-sm">
              <span class="font-medium text-gray-900 dark:text-white">{{ section.label }}</span>
              <span v-if="preview[section.key]" class="block text-gray-600 dark:text-gray-400">
                {{ describe(section.key) }}
              </span>
              <span v-if="mode === 'wipe' && importSections.includes(section.key) && preview[section.key]?.would_delete" class="block text-red-600 dark:text-red-400">
                Removes {{ preview[section.key].would_delete }} existing first.{{ WIPE_NOTES[section.key] ? ` ${WIPE_NOTES[section.key]}` : '' }}
              </span>
              <ul v-if="preview[section.key]?.problems.length" class="mt-1 space-y-0.5 text-amber-700 dark:text-amber-400">
                <li v-for="problem in preview[section.key].problems.slice(0, 5)" :key="problem">{{ problem }}</li>
                <li v-if="preview[section.key].problems.length > 5">…and {{ preview[section.key].problems.length - 5 }} more</li>
              </ul>
            </span>
          </label>
        </li>
      </ul>

      <div class="mt-4 flex flex-wrap items-center gap-3">
        <button type="button" :class="mode === 'wipe' ? dangerButton : primaryButton" :disabled="!importSections.length || importing" @click="runImport">
          {{ mode === 'wipe' ? 'Wipe & import' : 'Import' }}
        </button>
        <button type="button" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white" @click="reset">Cancel</button>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { useConfirmDialog } from '@/composables/useConfirmDialog'

type SectionKey = 'ingredients' | 'price_history' | 'inventory' | 'recipes' | 'production_runs'

interface SectionPreview {
  rows: number
  new: number
  existing: number
  would_delete: number
  problems: string[]
}

const SECTIONS: Array<{ key: SectionKey; label: string }> = [
  { key: 'ingredients', label: 'Ingredients (with sources and house-made recipes)' },
  { key: 'price_history', label: 'Price history' },
  { key: 'inventory', label: 'Inventory (stock on hand)' },
  { key: 'recipes', label: 'Recipes' },
  { key: 'production_runs', label: 'Production runs' },
]

// What else a wipe takes with it, beyond the section itself.
const WIPE_NOTES: Partial<Record<SectionKey, string>> = {
  ingredients: 'Also removes their sources, stock, price history and recipe lines.',
  recipes: 'Also removes their batches from production runs.',
}

const primaryButton = 'tap-target-touch inline-flex items-center px-4 py-2 rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50'
const dangerButton = 'tap-target-touch inline-flex items-center px-4 py-2 rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:opacity-50'

const { confirmDialog } = useConfirmDialog()

// Export
const exportSections = ref<SectionKey[]>(SECTIONS.map((s) => s.key))
const exportUrl = computed(() => route('admin.costing.data.export', { sections: exportSections.value }))

// Import
const mode = ref<'merge' | 'wipe'>('merge')
const fileInput = ref<HTMLInputElement | null>(null)
const loading = ref(false)
const importing = ref(false)
const error = ref<string | null>(null)
const upload = ref<{ token: string; exported_at: string | null; sections: SectionKey[] } | null>(null)
const preview = ref<Partial<Record<SectionKey, SectionPreview>>>({})
const importSections = ref<SectionKey[]>([])

const sectionsInFile = computed(() => SECTIONS.filter((s) => upload.value?.sections.includes(s.key)))

const describe = (key: SectionKey) => {
  const p = preview.value[key]
  if (!p) return ''
  if (key === 'inventory') return `${p.existing} stock figure${p.existing === 1 ? '' : 's'} to set`
  if (key === 'price_history') return `${p.new} new price${p.new === 1 ? '' : 's'}${p.existing ? `, ${p.existing} already here (skipped)` : ''}`
  return `${p.new} new, ${p.existing} already here (${mode.value === 'wipe' ? 'replaced' : 'updated'})`
}

const chooseFile = async (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  error.value = null
  loading.value = true
  try {
    const data = new FormData()
    data.append('file', file)
    data.append('mode', mode.value)
    const response = await axios.post(route('admin.costing.data.preview'), data)
    upload.value = { token: response.data.token, exported_at: response.data.exported_at, sections: response.data.sections }
    preview.value = response.data.preview
    importSections.value = [...response.data.sections]
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'That file couldn\'t be read.'
    upload.value = null
  } finally {
    loading.value = false
  }
}

// Section choice or mode changed: preview again from the held upload.
const refreshPreview = async () => {
  if (!upload.value || !importSections.value.length) return
  try {
    const response = await axios.post(route('admin.costing.data.repreview'), {
      token: upload.value.token,
      mode: mode.value,
      sections: importSections.value,
    })
    preview.value = { ...preview.value, ...response.data.preview }
  } catch (e: any) {
    error.value = e.response?.data?.message ?? 'Couldn\'t refresh the preview.'
  }
}
watch(mode, refreshPreview)

const runImport = async () => {
  if (!upload.value) return
  if (mode.value === 'wipe') {
    const removing = importSections.value
      .map((key) => ({ key, count: preview.value[key]?.would_delete ?? 0 }))
      .filter((s) => s.count)
      .map((s) => `${SECTIONS.find((x) => x.key === s.key)?.label}: ${s.count}`)
    const confirmed = await confirmDialog({
      title: 'Wipe and replace?',
      message: `This permanently removes the existing data in the chosen sections before importing${removing.length ? ` (${removing.join('; ')})` : ''}. It can't be undone.`,
      confirmLabel: 'Wipe & import',
      variant: 'danger',
    })
    if (!confirmed) return
  }

  importing.value = true
  router.post(route('admin.costing.data.import'), {
    token: upload.value.token,
    mode: mode.value,
    sections: importSections.value,
  }, {
    preserveScroll: true,
    onSuccess: () => reset(),
    onFinish: () => { importing.value = false },
  })
}

const reset = () => {
  upload.value = null
  preview.value = {}
  importSections.value = []
  error.value = null
  if (fileInput.value) fileInput.value.value = ''
}
</script>
