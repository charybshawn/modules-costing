// A unit's recipe weighs more than the unit is filled with; the extra
// fills more units. Prorate the recipe cost to what's in one filled unit --
// same math as CalculateRecipeCost's actual_cost_per_jar. No fill (or no
// weighed ingredients) means each unit is costed at the full recipe.
export function costPerFilledUnit(batchCost: number, batchGrams: number, fillGrams: number | null): number {
  if (fillGrams === null || !(fillGrams > 0) || batchGrams <= 0) return batchCost
  return (batchCost / batchGrams) * fillGrams
}
