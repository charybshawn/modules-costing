import { ref, watch, type Ref } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

export interface IngredientSource {
  id: number
  provider: string
  brand: string | null
}

export const sourceLabel = (source: IngredientSource): string =>
  source.brand ? `${source.provider} — ${source.brand}` : source.provider

/**
 * Fetches an ingredient's Sources (PackageSize rows) for a picker, and
 * exposes an "add a new source" action posting to the same upsert endpoint
 * StockAdjustModal's own add-a-source flow uses -- so a source created here
 * is the exact same row Inventory would show, not a parallel one.
 */
export function useIngredientSources(ingredientId: Ref<number | ''>) {
  const sources = ref<IngredientSource[]>([])
  const loadingSources = ref(false)

  const fetchSources = async (id: number) => {
    loadingSources.value = true
    try {
      const { data } = await axios.get(route('admin.costing.inventory.sources', id))
      sources.value = data.sources
    } finally {
      loadingSources.value = false
    }
  }

  watch(
    ingredientId,
    (id) => {
      sources.value = []
      if (id) fetchSources(id)
    },
    { immediate: true },
  )

  const addSourceSaving = ref(false)
  const addSourceError = ref<string | null>(null)

  const addSource = (id: number, provider: string, brand: string | null, packageSize: number): Promise<number | null> => {
    addSourceSaving.value = true
    addSourceError.value = null

    return new Promise((resolve) => {
      router.post(
        route('admin.costing.ingredients.set-package-size', id),
        { provider, brand, package_size: packageSize },
        {
          preserveScroll: true,
          onSuccess: async () => {
            await fetchSources(id)
            const created = sources.value.find((s) => s.provider === provider && (s.brand ?? null) === brand)
            resolve(created?.id ?? null)
          },
          onError: (errors) => {
            addSourceError.value = errors.package_size ?? errors.provider ?? 'Could not save.'
            resolve(null)
          },
          onFinish: () => {
            addSourceSaving.value = false
          },
        },
      )
    })
  }

  return { sources, loadingSources, fetchSources, addSource, addSourceSaving, addSourceError }
}
