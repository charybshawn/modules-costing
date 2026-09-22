<template>
  <div class="pb-36 md:pt-6 md:pb-6">
    <div>
      <!-- Row-tap stock drawer lives inside CostingModuleNav's own bottom
           bar (its #drawer slot) rather than floating separately -- grows
           directly out of it as one unit. Only relevant on mobile
           (stockIngredient still drives StockAdjustModal's desktop dialog
           below), but harmless to pass unconditionally: the slot content
           itself is `md:hidden` internally via CostingModuleNav's own
           wrapper, same as the bar it attaches to. -->
      <CostingModuleNav :drawer-open="stockIngredient !== null" @close-drawer="closeStockModal">
        <template #drawer>
          <InventoryStockDrawer :ingredient="stockIngredient" @close="closeStockModal" />
        </template>
      </CostingModuleNav>
      <AdminMobileHeader title="Inventory" />

      <div class="hidden md:flex md:items-center md:justify-between mb-6">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Inventory</h1>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Current stock on hand. Update before each production run -- the Production Planner deducts from these amounts.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            @click="openBulkModal"
            class="tap-target-touch inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700"
          >
            Bulk Update Stock
          </button>
          <button
            type="button"
            @click="openAddItemModal"
            class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
          >
            + Add Item
          </button>
          <Link :href="route('admin.costing.inventory.adjustments')" class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            View History
          </Link>
          <Link :href="route('admin.costing.ingredients.index')" class="tap-target-touch inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
            Ingredients
          </Link>
        </div>
      </div>

      <!-- Mobile-only hero block: headline stat + quick-action icon tiles,
           matching Ingredients/Index.vue's own hero shell exactly (same
           colored card, w-16 h-16 rounded-2xl tiles, Archivo Black label).
           Three tiles rather than Ingredients' one -- Add Item, Bulk Update
           Stock, and View History all lack a bottom-nav equivalent, unlike
           "Ingredients" (dropped here, same reasoning Ingredients used to
           drop its own Price History button: one tap away via
           CostingModuleNav's bottom bar). justify-around rather than
           -center: with more than one tile, -center would cluster them
           together instead of spacing them across the row. -->
      <div class="md:hidden mb-6 rounded-lg bg-gray-200 dark:bg-amber-500 px-5 pt-[30px] pb-[20px]">
        <div class="text-center">
          <div class="text-sm font-bold text-gray-800">Total Items</div>
          <div class="mt-1 text-4xl font-extrabold text-emerald-600">{{ props.ingredients.length }}</div>
        </div>
        <div class="mt-[30px] flex items-center justify-around">
          <div class="flex flex-col items-center gap-3">
            <button
              type="button"
              @click="openAddItemModal"
              class="tap-target-touch w-16 h-16 rounded-2xl bg-white dark:bg-gray-900 shadow-md dark:shadow-[0_4px_10px_rgba(0,0,0,0.5)] flex items-center justify-center"
            >
              <svg class="w-11 h-11 text-amber-500 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </button>
            <span class="text-xs font-['Archivo_Black'] uppercase tracking-wide text-amber-500 dark:text-white leading-tight text-center">Add<br>Item</span>
          </div>
          <div class="flex flex-col items-center gap-3">
            <button
              type="button"
              @click="openBulkModal"
              class="tap-target-touch w-16 h-16 rounded-2xl bg-white dark:bg-gray-900 shadow-md dark:shadow-[0_4px_10px_rgba(0,0,0,0.5)] flex items-center justify-center"
            >
              <svg class="w-11 h-11 text-amber-500 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
              </svg>
            </button>
            <span class="text-xs font-['Archivo_Black'] uppercase tracking-wide text-amber-500 dark:text-white leading-tight text-center">Bulk<br>Update</span>
          </div>
          <Link :href="route('admin.costing.inventory.adjustments')" class="flex flex-col items-center gap-3">
            <span class="tap-target-touch w-16 h-16 rounded-2xl bg-white dark:bg-gray-900 shadow-md dark:shadow-[0_4px_10px_rgba(0,0,0,0.5)] flex items-center justify-center">
              <svg class="w-11 h-11 text-amber-500 dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </span>
            <span class="text-xs font-['Archivo_Black'] uppercase tracking-wide text-amber-500 dark:text-white leading-tight text-center">View<br>History</span>
          </Link>
        </div>
      </div>

      <!-- No overflow-hidden: it breaks DataTable's sticky toolbar by
           pinning it inside this box instead of the viewport. -->
      <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
        <DataTable
          :columns="columns"
          :items="ingredients"
          :sort-field="sortField"
          :sort-direction="sortDirection"
          searchable
          search-placeholder="Search inventory..."
          empty-message="No ingredients yet."
          table-id="costing-inventory"
          item-key="ingredient_id"
          mobile-row-style="line"
          row-clickable
          :extra-filter-count="recipeFilterId !== null ? 1 : 0"
          @sort="handleSort"
          @row-click="openStockModal"
          @clear-filters="recipeFilterId = null"
        >
          <!-- Recipe is DataTable's filters-extra slot rather than a
               filterOnly column, same as Ingredients/Index.vue: an
               ingredient can be in several recipes (belongsToMany), so
               matching is "does this ingredient's recipe_ids include the
               selected recipe", not the exact-equality/one-of-several
               checks DataTable's select/multiselect filter types do
               internally. -->
          <template #filters-extra>
            <div>
              <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Recipe</label>
              <select
                v-model="recipeFilterId"
                class="w-full text-base sm:text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                <option :value="null">Any</option>
                <option v-for="recipe in props.recipes" :key="recipe.id" :value="recipe.id">{{ recipe.name }}</option>
              </select>
            </div>
          </template>

          <!-- Single-line mobile row: item (truncates) + on hand quantity --
               matches Ingredients/Index.vue's own compact mobile row.
               Tapping the row opens the Stock modal (row-clickable, above)
               rather than navigating -- there's no Edit page here. -->
          <template #mobile-card="{ item }">
            <div class="flex items-center gap-3 min-w-0">
              <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</span>
              <span class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ formatQuantity(item.on_hand, item.unit_type) }}</span>
            </div>
          </template>

          <template #cell-name="{ item }">
            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ item.name }}</div>
          </template>

          <template #cell-category="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.category ?? '—' }}</span>
          </template>

          <template #cell-source_count="{ item }">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.source_count }}</span>
          </template>

          <template #cell-on_hand="{ item }">
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ formatQuantity(item.on_hand, item.unit_type) }}</span>
          </template>
        </DataTable>
      </div>

      <!-- Desktop only -- mobile's equivalent is InventoryStockDrawer,
           mounted up top inside CostingModuleNav's #drawer slot instead
           (see that usage's own comment). isDesktop gates it (rather than
           both mounting and relying on CSS to hide one) since a hidden
           <dialog> still calling showModal() is unreliable.

           No @updated handler needed -- each mutation inside (recount/
           adjust) already POSTs through a route that redirect()->back()s
           to this exact page, which Inertia follows and merges fresh
           props from, `ingredients` included. A second, manual
           router.reload() here was pure redundancy that raced the first
           request's own flash-message session aging, firing the success
           toast a second time. -->
      <StockAdjustModal v-if="isDesktop" :ingredient="stockIngredient" @close="closeStockModal" />

      <!-- Bulk Update Stock modal -->
      <Modal :show="showBulkModal" max-width="2xl" @close="closeBulkModal">
        <form @submit.prevent="submitBulk" class="p-6">
          <h2 class="text-lg font-medium text-gray-900 dark:text-white">Bulk Update Stock</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update several ingredients' stock in one go.</p>

          <FormErrorSummary :errors="bulkForm.errors" class="mt-4" />

          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mode</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <label
                :class="[
                  'relative flex cursor-pointer rounded-lg border p-3 focus:outline-none',
                  bulkForm.mode === 'adjust' ? 'border-indigo-600 ring-1 ring-indigo-600 bg-indigo-50 dark:bg-indigo-900/30' : 'border-gray-300 dark:border-gray-600'
                ]"
              >
                <input v-model="bulkForm.mode" type="radio" value="adjust" class="sr-only" />
                <div>
                  <span class="block text-sm font-medium text-gray-900 dark:text-white">Adjust</span>
                  <span class="block text-xs text-gray-500 dark:text-gray-400">Adds to or subtracts from current stock (received, correction, spoilage).</span>
                </div>
              </label>
              <label
                :class="[
                  'relative flex cursor-pointer rounded-lg border p-3 focus:outline-none',
                  bulkForm.mode === 'recount' ? 'border-indigo-600 ring-1 ring-indigo-600 bg-indigo-50 dark:bg-indigo-900/30' : 'border-gray-300 dark:border-gray-600'
                ]"
              >
                <input v-model="bulkForm.mode" type="radio" value="recount" class="sr-only" />
                <div>
                  <span class="block text-sm font-medium text-gray-900 dark:text-white">Inventory Recount</span>
                  <span class="block text-xs text-gray-500 dark:text-gray-400">Replaces current stock with the entered amount (a physical count).</span>
                </div>
              </label>
            </div>
          </div>

          <div v-if="bulkForm.mode === 'adjust'" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason</label>
              <select v-model="bulkForm.reason" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm">
                <option value="received">Stock received (bought more)</option>
                <option value="correction">Correction (mistake, spoilage, shrinkage)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes (optional, applies to whole batch)</label>
              <input v-model="bulkForm.notes" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" placeholder="e.g. Weekly shopping run" />
            </div>
          </div>

          <div class="mt-4 space-y-3 max-h-80 overflow-y-auto">
            <div v-for="(row, index) in bulkForm.items" :key="index">
              <div class="flex flex-wrap items-center gap-3">
                <select
                  v-model.number="row.ingredient_id"
                  required
                  @change="onIngredientChange(row)"
                  class="flex-1 min-w-[10rem] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm"
                >
                  <option :value="null" disabled>Select an ingredient&hellip;</option>
                  <option
                    v-for="ingredient in availableIngredients(row.ingredient_id)"
                    :key="ingredient.ingredient_id"
                    :value="ingredient.ingredient_id"
                  >
                    {{ ingredient.name }} ({{ formatQuantity(ingredient.on_hand, ingredient.unit_type) }} on hand){{ ingredient.source_count === 0 ? ' -- no sources yet' : '' }}
                  </option>
                </select>
                <select
                  v-model.number="row.package_size_id"
                  required
                  :disabled="sourcesFor(row.ingredient_id).length === 0"
                  class="flex-1 min-w-[10rem] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm disabled:opacity-50"
                >
                  <option :value="null" disabled>{{ sourcesFor(row.ingredient_id).length === 0 ? 'No sources yet' : 'Select a source…' }}</option>
                  <option v-for="source in sourcesFor(row.ingredient_id)" :key="source.id" :value="source.id">
                    {{ source.provider }}{{ source.brand ? ' — ' + source.brand : '' }} ({{ formatQuantity(source.package_size, ingredientFor(row.ingredient_id)?.unit_type ?? 'g') }}/pkg{{ source.units_per_case > 1 ? `, case of ${source.units_per_case}` : '' }})
                  </option>
                </select>
                <input
                  v-model.number="row.packages"
                  type="number"
                  inputmode="decimal"
                  :min="bulkForm.mode === 'recount' ? 0 : undefined"
                  step="0.01"
                  required
                  :disabled="row.package_size_id === null"
                  :placeholder="bulkForm.mode === 'adjust' ? 'Packages (+/-)' : 'Packages on hand'"
                  class="w-full sm:w-36 flex-1 sm:flex-none min-w-0 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm disabled:opacity-50"
                />
                <IconButton
                  type="button"
                  @click="removeRow(index)"
                  :disabled="bulkForm.items.length === 1"
                  class="text-gray-400 dark:text-gray-500 hover:text-red-600 dark:hover:text-red-400 disabled:opacity-30 disabled:hover:text-gray-400 dark:disabled:hover:text-gray-500"
            label="Remove row"
          >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </IconButton>
              </div>
              <p v-if="row.package_size_id !== null" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ packageSizeHint(row) }}
              </p>
            </div>
          </div>

          <button
            type="button"
            @click="addRow"
            :disabled="bulkForm.items.length >= props.ingredients.length"
            class="mt-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 disabled:opacity-40 disabled:hover:text-indigo-600 dark:disabled:hover:text-indigo-400"
          >
            + Add another ingredient
          </button>

          <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="closeBulkModal" class="tap-target-touch bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
              Cancel
            </button>
            <button type="submit" :disabled="bulkForm.processing" class="tap-target-touch bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
              <span v-if="bulkForm.processing">Saving...</span>
              <span v-else>Save</span>
            </button>
          </div>
        </form>
      </Modal>

      <!-- Add Item modal -- for something not in the system at all yet.
           An existing ingredient that just needs a new source still uses
           the per-item Stock modal (opened via the ingredient name above),
           not this. -->
      <Modal :show="showAddItemModal" max-width="lg" @close="closeAddItemModal">
        <form @submit.prevent="submitAddItem" class="p-6">
          <h2 class="text-lg font-medium text-gray-900 dark:text-white">Add Item</h2>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">For something not in the system yet -- creates the ingredient, its first source, and a starting count in one go.</p>

          <FormErrorSummary :errors="addItemForm.errors" class="mt-4" />

          <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
              <input v-model="addItemForm.name" type="text" required autofocus placeholder="e.g. Cream Cheese" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category</label>
              <input v-model="addItemForm.category" type="text" placeholder="e.g. Dairy & Eggs" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Measured In *</label>
              <select v-model="addItemForm.unit_type" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm">
                <option value="g">Grams (priced per kg)</option>
                <option value="unit">Units (priced per unit)</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Waste % *</label>
              <input v-model.number="addItemForm.waste_percent" type="number" inputmode="decimal" min="1" max="100" step="0.01" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">100 = no waste, 95 = 5% trim loss. Adjust later on the Ingredients page if unsure.</p>
            </div>
          </div>

          <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">First Source</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Provider *</label>
                <input v-model="addItemForm.provider" type="text" required placeholder="e.g. GFS" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Brand</label>
                <input v-model="addItemForm.brand" type="text" placeholder="Optional" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Size of 1 Package *</label>
                <input v-model.number="addItemForm.package_size" type="number" inputmode="decimal" min="0.01" step="0.01" required :placeholder="addItemForm.unit_type === 'unit' ? 'e.g. 1' : 'e.g. 3500'" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ addItemForm.unit_type === 'unit' ? 'ONE individual package -- e.g. 1 lid, 1 bag. Never the case total, even if sold by the case (use Units Per Case for that).' : 'Grams in ONE package -- e.g. 3.5kg = 3500. Never the case total.' }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Units Per Case</label>
                <input v-model.number="addItemForm.units_per_case" type="number" inputmode="numeric" min="1" step="1" placeholder="1" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Purchasing info only -- how many of the above come in one case. Doesn't change how stock is counted. Leave at 1 if not sold by the case.</p>
              </div>
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Packages On Hand</label>
                <input v-model.number="addItemForm.packages" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base sm:text-sm" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  <template v-if="addItemQuantityPreview">= {{ addItemQuantityPreview }}</template>
                  <template v-else>Leave blank to add this item with zero stock for now. Always individual packages, not cases.</template>
                </p>
              </div>
            </div>
          </div>

          <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="button" @click="closeAddItemModal" class="tap-target-touch bg-gray-200 dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600">
              Cancel
            </button>
            <button type="submit" :disabled="addItemForm.processing" class="tap-target-touch bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
              <span v-if="addItemForm.processing">Adding...</span>
              <span v-else>Add Item</span>
            </button>
          </div>
        </form>
      </Modal>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useMediaQuery } from '@vueuse/core'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AdminMobileHeader from '@/Components/Admin/AdminMobileHeader.vue'
import DataTable, { type Column } from '@/Components/Admin/DataTable.vue'
import Modal from '@/Components/Modal.vue'
import FormErrorSummary from '@/Components/Admin/FormErrorSummary.vue'
import StockAdjustModal, { type StockIngredient } from '../Shared/StockAdjustModal.vue'
import InventoryStockDrawer from '../Shared/InventoryStockDrawer.vue'
import CostingModuleNav from '../Shared/CostingModuleNav.vue'
import { formatQuantity } from '../Shared/formatWeight'
import IconButton from '@/Components/IconButton.vue'

defineOptions({ layout: (h, page) => h(AdminLayout, { wide: true, hideBreadcrumbOnMobile: true }, () => page) })

interface IngredientSource {
  id: number
  provider: string
  brand: string | null
  package_size: number
  units_per_case: number
}

interface InventoryRow {
  ingredient_id: number
  name: string
  category: string | null
  unit_type: 'g' | 'unit'
  on_hand: number
  source_count: number
  sources: IngredientSource[]
  preferred_source_id: number | null
  recipe_ids: number[]
}

interface RecipeOption {
  id: number
  name: string
}

interface Props {
  ingredients: InventoryRow[]
  recipes: RecipeOption[]
}

const props = defineProps<Props>()

const recipeFilterId = ref<number | null>(null)

const sortField = ref('name')
const sortDirection = ref<'asc' | 'desc'>('asc')

const handleSort = (field: string) => {
  if (sortField.value === field) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortDirection.value = 'asc'
  }
}

const ingredients = computed(() => {
  const field = sortField.value as keyof InventoryRow
  const dir = sortDirection.value === 'asc' ? 1 : -1
  const result = recipeFilterId.value === null
    ? props.ingredients
    : props.ingredients.filter((i) => i.recipe_ids.includes(recipeFilterId.value as number))
  return [...result].sort((a, b) => String(a[field] ?? '').localeCompare(String(b[field] ?? '')) * dir)
})

const columns: Column[] = [
  { key: 'name', label: 'Ingredient', sortable: true },
  { key: 'category', label: 'Category', sortable: true, hideable: true, filterable: true },
  { key: 'source_count', label: 'Sources', hideable: true },
  { key: 'on_hand', label: 'On Hand' },
]

// Stock modal -- markup/logic lives in the shared component; this page
// only owns which ingredient (if any) it's open for, same pattern as
// Ingredients/Index.vue's AvailablePricesModal. isDesktop picks which
// shell renders it (StockAdjustModal's dialog vs InventoryStockDrawer's
// bottom sheet) -- matches DataTable's own 'md' breakpoint.
const isDesktop = useMediaQuery('(min-width: 768px)')
const stockIngredient = ref<StockIngredient | null>(null)

const openStockModal = (item: InventoryRow) => {
  stockIngredient.value = { id: item.ingredient_id, name: item.name, unit_type: item.unit_type }
}

const closeStockModal = () => {
  stockIngredient.value = null
}

// Bulk Update Stock modal
interface BulkFormData {
  mode: 'adjust' | 'recount'
  reason: 'received' | 'correction'
  notes: string
  items: Array<{ ingredient_id: number | null; package_size_id: number | null; packages: number | null }>
}

const showBulkModal = ref(false)

const bulkForm = useForm<BulkFormData>({
  mode: 'adjust',
  reason: 'received',
  notes: '',
  items: [{ ingredient_id: null, package_size_id: null, packages: null }],
})

const openBulkModal = () => {
  bulkForm.reset()
  bulkForm.clearErrors()
  showBulkModal.value = true
}

const closeBulkModal = () => {
  showBulkModal.value = false
}

// Dashboard's "Log a Purchase" card deep-links here with ?open_bulk=1 --
// the bulk form already defaults to adjust/received, exactly that flow.
onMounted(() => {
  if (new URLSearchParams(window.location.search).get('open_bulk') === '1') {
    openBulkModal()
  }
})

const addRow = () => {
  bulkForm.items.push({ ingredient_id: null, package_size_id: null, packages: null })
}

const removeRow = (index: number) => {
  if (bulkForm.items.length > 1) {
    bulkForm.items.splice(index, 1)
  }
}

// Ingredients already picked in another row shouldn't be selectable again
// (matches the server's 'distinct' validation on ingredient_id).
const availableIngredients = (currentValue: number | null) => {
  const chosen = new Set(bulkForm.items.map((row) => row.ingredient_id).filter((id) => id !== null && id !== currentValue))
  return props.ingredients.filter((i) => !chosen.has(i.ingredient_id))
}

const ingredientFor = (ingredientId: number | null): InventoryRow | null => {
  if (ingredientId === null) return null
  return props.ingredients.find((i) => i.ingredient_id === ingredientId) ?? null
}

const sourcesFor = (ingredientId: number | null): IngredientSource[] => ingredientFor(ingredientId)?.sources ?? []

// Picking a new ingredient resets the source/quantity below it -- a
// previously-chosen source almost certainly doesn't belong to the newly
// picked ingredient. Preselects the ingredient's preferred (else first)
// source as a starting point, same default StockAdjustModal's own picker
// would land on, but the user can still change it to any real source.
const onIngredientChange = (row: { ingredient_id: number | null; package_size_id: number | null; packages: number | null }) => {
  const ingredient = ingredientFor(row.ingredient_id)
  row.package_size_id = ingredient?.preferred_source_id ?? ingredient?.sources[0]?.id ?? null
  row.packages = null
}

// The packages field is entered in whole-or-fractional packages (e.g. 0.5
// for half a package used), not the ingredient's raw base unit -- there's
// no other way to tell what "one package" of the chosen source actually
// is, or to see the resulting total, without leaving this modal.
const packageSizeHint = (row: { ingredient_id: number | null; package_size_id: number | null; packages: number | null }): string => {
  const ingredient = ingredientFor(row.ingredient_id)
  const source = ingredient?.sources.find((s) => s.id === row.package_size_id)
  if (!ingredient || !source) return ''

  const size = formatQuantity(source.package_size, ingredient.unit_type)
  const label = source.brand ? `${source.provider} — ${source.brand}` : source.provider
  const caseSuffix = source.units_per_case > 1 ? `, case of ${source.units_per_case}` : ''
  const base = `1 package = ${size}${caseSuffix} (${label})`

  if (!row.packages) return base

  // Always individual packages, never cases -- recount/adjust granularity
  // is unaffected by units_per_case, which only matters for purchasing.
  const total = row.packages * source.package_size
  const sign = total < 0 ? '−' : ''
  return `${base} → ${row.packages} × ${size} = ${sign}${formatQuantity(Math.abs(total), ingredient.unit_type)}`
}

const submitBulk = () => {
  bulkForm.post(route('admin.costing.inventory.bulk-update'), {
    preserveScroll: true,
    onSuccess: () => {
      showBulkModal.value = false
    },
  })
}

// Add Item modal -- onboards something not in the system at all yet:
// creates the Ingredient, its first Source, and an optional starting
// count in one submission (previously three separate screens). An
// existing ingredient that just needs a new source still uses the
// per-item Stock modal (openStockModal above), not this.
interface AddItemFormData {
  name: string
  category: string
  unit_type: 'g' | 'unit'
  waste_percent: number
  provider: string
  brand: string
  package_size: number | null
  units_per_case: number
  packages: number | null
}

const showAddItemModal = ref(false)

const addItemForm = useForm<AddItemFormData>({
  name: '',
  category: '',
  unit_type: 'g',
  waste_percent: 100,
  provider: '',
  brand: '',
  package_size: null,
  units_per_case: 1,
  packages: null,
})

const openAddItemModal = () => {
  addItemForm.reset()
  addItemForm.clearErrors()
  showAddItemModal.value = true
}

const closeAddItemModal = () => {
  showAddItemModal.value = false
}

const addItemQuantityPreview = computed<string | null>(() => {
  if (!addItemForm.packages || !addItemForm.package_size) return null
  return formatQuantity(addItemForm.packages * addItemForm.package_size, addItemForm.unit_type)
})

const submitAddItem = () => {
  addItemForm.post(route('admin.costing.inventory.items.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showAddItemModal.value = false
    },
  })
}
</script>
