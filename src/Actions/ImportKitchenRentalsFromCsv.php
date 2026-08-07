<?php

namespace Cultpantry\Costing\Actions;

use Carbon\Carbon;
use Cultpantry\Costing\Models\KitchenRental;
use Illuminate\Http\UploadedFile;

/**
 * Imports a FoodCorridor "all bookings" CSV export. The export is one row
 * per Space/Equipment reserved, not one row per booking -- a single kitchen
 * booking with two pieces of equipment attached is three rows sharing the
 * same Booking Title and calendar day. Rows are grouped back into one
 * KitchenRental per real booking day before upserting.
 *
 * Grouping is by (Booking Title, Kitchen Name, calendar date of Start
 * Time) rather than an exact Start/End Time match -- FoodCorridor lets an
 * Equipment row carry a narrower or offset time range than the Space row
 * it's attached to (e.g. the space books 08:00-15:00 but an attached blast
 * chiller only books 08:00-13:00), so matching on exact timestamps would
 * incorrectly split one real booking into several rows. Booking Title stays
 * part of the key because two different customers can book the same venue
 * on the same day.
 */
class ImportKitchenRentalsFromCsv
{
    /**
     * @return array{created: int, updated: int}
     */
    public function handle(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return ['created' => 0, 'updated' => 0];
        }

        $header = fgetcsv($handle, escape: '\\');
        if ($header === false) {
            fclose($handle);
            return ['created' => 0, 'updated' => 0];
        }
        $header = array_map(fn (string $col) => trim($col), $header);

        /** @var array<string, array<int, array{data: array<string, string>, starts_at: Carbon, ends_at: Carbon|null}>> $groups */
        $groups = [];

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $data = array_combine($header, $row);

            $startsAt = $this->parseDate($data['Start Time'] ?? '');
            if (!$startsAt) {
                continue;
            }
            $endsAt = $this->parseDate($data['End Time'] ?? '');

            $key = implode('|', [
                $data['Booking Title'] ?? '',
                $data['Kitchen Name'] ?? '',
                $startsAt->toDateString(),
            ]);

            $groups[$key] ??= [];
            $groups[$key][] = ['data' => $data, 'starts_at' => $startsAt, 'ends_at' => $endsAt];
        }

        fclose($handle);

        $created = 0;
        $updated = 0;

        foreach ($groups as $rows) {
            $spaceEntry = collect($rows)->first(fn (array $r) => ($r['data']['Calendar Type'] ?? null) === 'Space');
            $spaceRow = ($spaceEntry ?? $rows[0])['data'];

            // Each equipment row keeps its own starts_at/ends_at -- as
            // established above, it isn't always the same window as the
            // Space row it's grouped with, and the UI shows that per-item
            // range whenever it actually differs from the slot's own.
            $equipment = collect($rows)
                ->filter(fn (array $r) => ($r['data']['Calendar Type'] ?? null) === 'Equipment')
                ->map(fn (array $r) => [
                    'name' => $r['data']['Calendar Name'] ?? '',
                    'starts_at' => $r['starts_at']->toDateTimeString(),
                    'ends_at' => ($r['ends_at'] ?? $r['starts_at'])->toDateTimeString(),
                ])
                ->filter(fn (array $e) => $e['name'] !== '')
                ->unique('name')
                ->values()
                ->all();

            // The Space row's own times are the canonical booked window for
            // the slot; fall back to the earliest start / latest end across
            // the group if a booking somehow has no Space row at all.
            if ($spaceEntry) {
                $startsAt = $spaceEntry['starts_at'];
                $endsAt = $spaceEntry['ends_at'] ?? $startsAt;
            } else {
                $startsAt = collect($rows)->pluck('starts_at')->min();
                $endsAt = collect($rows)->pluck('ends_at')->filter()->max() ?? $startsAt;
            }

            $rental = KitchenRental::updateOrCreate(
                [
                    'booking_title' => $spaceRow['Booking Title'] ?? '',
                    'venue_name' => $spaceRow['Kitchen Name'] ?? null,
                    // Every row in this group was bucketed by its own
                    // starts_at date, so they all share one calendar day --
                    // safe to read it off the canonical $startsAt.
                    'booking_date' => $startsAt->toDateString(),
                ],
                [
                    'booked_for' => $spaceRow['Booked For'] ?? null,
                    'status' => $spaceRow['Booking Status'] ?? null,
                    'space_name' => $spaceRow['Calendar Name'] ?? '',
                    'equipment' => $equipment,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt ?? $startsAt,
                    'booking_length' => is_numeric($spaceRow['Booking Length'] ?? null) ? (float) $spaceRow['Booking Length'] : null,
                ],
            );

            $rental->wasRecentlyCreated ? $created++ : $updated++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * FoodCorridor dates look like "Sep  4, 2026 08:00" -- a double space
     * before single-digit days that Carbon's format parser won't tolerate
     * as-is, so whitespace is collapsed first.
     */
    private function parseDate(string $value): ?Carbon
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value));
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('M j, Y H:i', $normalized);
        } catch (\Exception) {
            return null;
        }
    }
}
