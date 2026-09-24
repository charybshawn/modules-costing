// Shared Tailwind classes for the costing forms' raw inputs, so every field
// gets the same look plus the 16px mobile font size (text-base sm:text-sm)
// that stops iOS Safari zooming on focus (FORM_DESIGN.md → Mobile forms).
export const inputClass =
  'mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 text-base sm:text-sm'

// Subdued icon actions (header ×, #mobile-actions): FORM_DESIGN.md → Actions.
export const iconActionClass =
  'rounded-md text-gray-400 hover:text-gray-600 active:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-300 dark:active:bg-gray-700'
export const dangerIconActionClass =
  'rounded-md text-gray-400 hover:text-red-600 active:bg-red-50 dark:text-gray-500 dark:hover:text-red-400 dark:active:bg-red-900/20'

// Desktop #header button group.
export const secondaryButtonClass =
  'tap-target-touch inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
export const dangerOutlineButtonClass =
  'tap-target-touch inline-flex items-center px-3 py-2 border border-red-300 dark:border-red-700 rounded-md text-sm font-medium text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'
export const backLinkClass =
  'hidden md:inline-flex tap-target-touch items-center text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white text-sm'
