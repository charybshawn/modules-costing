// A recipe's ingredients make one batch; each unit only holds the fill
// weight, and the rest goes into the next unit. Prorate the batch cost to
// what's in one unit -- same math as CalculateRecipeCost's
// actual_cost_per_jar. No fill (or no weighed ingredients) means the whole
// batch is costed as one unit.
export function costPerFilledUnit(batchCost: number, batchGrams: number, fillGrams: number | null): number {
  if (fillGrams === null || !(fillGrams > 0) || batchGrams <= 0) return batchCost
  return (batchCost / batchGrams) * fillGrams
}
