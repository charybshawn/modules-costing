// How much product a run's batches make, and how much of it the chosen fills
// use. Mirrors CheckRunAllocation on the server (volume = the recipe's yield
// in grams x batch size x batches; each filled unit takes its size's fill
// weight), so the modal can show a shortfall while the user is still typing.

export interface AllocationSize {
  id: number
  label: string
  fill_size_g: number
}

export interface AllocationRecipe {
  id: number
  name: string
  yield_grams: number
  sizes: AllocationSize[]
}

export interface RecipeAllocation {
  recipe_id: number
  recipe_name: string
  volume_g: number
  allocated_g: number
  remaining_g: number
  fits: boolean
  message: string | null
  sizes: Array<AllocationSize & { units: number; maxMore: number }>
}

const grams = (value: number): string => (+value.toFixed(2)).toLocaleString('en-US')

export function analyseAllocations(
  recipes: AllocationRecipe[],
  batches: Array<{ recipe_id: number; batches: number }>,
  batchSize: number,
  allocations: Array<{ recipe_size_id: number; units: number }>,
  requireFills: boolean,
): RecipeAllocation[] {
  const unitsBySize = new Map(allocations.map((row) => [row.recipe_size_id, Math.max(0, Math.floor(Number(row.units) || 0))]))

  return batches
    .filter((row) => Number(row.batches) > 0)
    .flatMap((row) => {
      const recipe = recipes.find((r) => r.id === row.recipe_id)
      if (!recipe) return []

      const volume = recipe.yield_grams * (Number(batchSize) || 0) * Number(row.batches)
      const sizes = recipe.sizes.map((size) => ({ ...size, units: unitsBySize.get(size.id) ?? 0, maxMore: 0 }))
      const allocated = sizes.reduce((sum, size) => sum + size.units * size.fill_size_g, 0)
      const remaining = volume - allocated
      const fits = remaining >= -0.005

      let message: string | null = null
      if (!fits) {
        const parts = sizes.filter((size) => size.units > 0).map((size) => `${size.units} x ${grams(size.fill_size_g)}g`).join(' + ')
        message = `${recipe.name}: ${parts} needs ${grams(allocated)}g but its batches make ${grams(volume)}g -- short by ${grams(allocated - volume)}g. Add batches or fill fewer units.`
      } else if (requireFills && allocated <= 0) {
        message = `${recipe.name} has batches but no fills allocated -- choose how many of each size to fill.`
      }

      for (const size of sizes) {
        size.maxMore = size.fill_size_g > 0 ? Math.max(0, Math.floor(Math.max(remaining, 0) / size.fill_size_g)) : 0
      }

      return [{
        recipe_id: recipe.id,
        recipe_name: recipe.name,
        volume_g: volume,
        allocated_g: allocated,
        remaining_g: remaining,
        fits: fits && message === null,
        message,
        sizes,
      }]
    })
}

export const formatGrams = grams
