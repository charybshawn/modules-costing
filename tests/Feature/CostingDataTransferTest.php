<?php

use App\Models\User;
use Cultpantry\Costing\Actions\ExportCostingData;
use Cultpantry\Costing\Actions\ImportCostingData;
use Cultpantry\Costing\Database\Seeders\CostingDatabaseSeeder;
use Cultpantry\Costing\Models\Ingredient;
use Cultpantry\Costing\Models\PackageSize;
use Cultpantry\Costing\Models\PriceHistoryEntry;
use Cultpantry\Costing\Models\ProductionRun;
use Cultpantry\Costing\Models\Recipe;
use Illuminate\Http\UploadedFile;

describe('costing import / export', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->customer = User::factory()->create(['role' => 'customer']);
        $this->seed(CostingDatabaseSeeder::class);
    });

    $allSections = ExportCostingData::SECTIONS;

    it('exports only the chosen sections, referring to records by name', function () {
        $document = app(ExportCostingData::class)->handle(['recipes', 'ingredients']);

        expect($document['format'])->toBe(ExportCostingData::FORMAT);
        expect($document['sections'])->toBe(['ingredients', 'recipes']);
        expect($document)->not->toHaveKey('price_history');

        $butter = collect($document['ingredients'])->firstWhere('name', 'Apple Butter');
        expect($butter['is_house_made'])->toBeTrue();
        expect(collect($butter['made_from'])->pluck('ingredient'))->toContain('Apples Pink Ladies');

        $apple = collect($document['recipes'])->firstWhere('name', 'Autumn Apple Cinnamon');
        expect(collect($apple['lines'])->pluck('ingredient'))->toContain('Apple Butter');
    });

    it('round-trips everything through wipe & replace', function () use ($allSections) {
        $run = ProductionRun::create(['name' => 'CP-RT-01', 'batch_size' => 20, 'run_date' => now()->toDateString()]);
        $run->recipes()->sync([Recipe::where('name', 'Autumn Apple Cinnamon')->value('id') => ['batches' => 4]]);

        $before = [
            'ingredients' => Ingredient::count(),
            'prices' => PriceHistoryEntry::count(),
            'stock' => (float) PackageSize::sum('quantity_on_hand'),
            'recipes' => Recipe::count(),
            'runs' => ProductionRun::count(),
            'butter_components' => Ingredient::where('name', 'Apple Butter')->first()->components()->count(),
        ];

        $document = app(ExportCostingData::class)->handle($allSections);
        app(ImportCostingData::class)->apply($document, $allSections, 'wipe', $this->admin->id);

        expect([
            'ingredients' => Ingredient::count(),
            'prices' => PriceHistoryEntry::count(),
            'stock' => (float) PackageSize::sum('quantity_on_hand'),
            'recipes' => Recipe::count(),
            'runs' => ProductionRun::count(),
            'butter_components' => Ingredient::where('name', 'Apple Butter')->first()->components()->count(),
        ])->toBe($before);

        $imported = ProductionRun::where('name', 'CP-RT-01')->first();
        expect((int) $imported->recipes->firstOrFail()->pivot->batches)->toBe(4);
    });

    it('merges without duplicating prices, updating what exists and adding what is new', function () {
        $document = app(ExportCostingData::class)->handle(['ingredients', 'price_history']);
        $prices = PriceHistoryEntry::count();

        // A changed note on an existing ingredient, and a brand-new one.
        $document['ingredients'][0]['notes'] = 'From the file';
        $document['ingredients'][] = ['name' => 'Imported Saffron', 'unit_type' => 'g', 'waste_percent' => 100, 'sources' => [], 'made_from' => []];

        $preview = app(ImportCostingData::class)->preview($document, ['ingredients', 'price_history'], 'merge');
        expect($preview['ingredients']['new'])->toBe(1);
        expect($preview['price_history']['new'])->toBe(0);
        expect($preview['price_history']['existing'])->toBe($prices);

        app(ImportCostingData::class)->apply($document, ['ingredients', 'price_history'], 'merge', $this->admin->id);

        expect(PriceHistoryEntry::count())->toBe($prices);
        expect(Ingredient::where('name', $document['ingredients'][0]['name'])->value('notes'))->toBe('From the file');
        expect(Ingredient::where('name', 'Imported Saffron')->exists())->toBeTrue();
    });

    it('flags references it can\'t resolve, and skips them on import', function () {
        $document = app(ExportCostingData::class)->handle(['recipes']);
        $document['recipes'][0]['lines'][] = ['ingredient' => 'Unicorn Dust', 'quantity' => 5, 'byproduct' => false];

        $preview = app(ImportCostingData::class)->preview($document, ['recipes'], 'merge');
        expect(implode(' ', $preview['recipes']['problems']))->toContain('Unicorn Dust');

        app(ImportCostingData::class)->apply($document, ['recipes'], 'merge', $this->admin->id);
        expect(Ingredient::where('name', 'Unicorn Dust')->exists())->toBeFalse();
    });

    it('downloads an export, previews an upload without changing anything, then imports it', function () {
        $this->actingAs($this->admin)
            ->get(route('admin.costing.data.export', ['sections' => ['ingredients']]))
            ->assertOk()
            ->assertHeader('content-type', 'application/json');

        $document = app(ExportCostingData::class)->handle(['ingredients']);
        $document['ingredients'][] = ['name' => 'Uploaded Vanilla', 'unit_type' => 'g', 'waste_percent' => 100];
        $file = UploadedFile::fake()->createWithContent('costing.json', json_encode($document));

        $response = $this->actingAs($this->admin)
            ->post(route('admin.costing.data.preview'), ['file' => $file, 'mode' => 'merge'])
            ->assertOk()
            ->assertJsonPath('sections', ['ingredients'])
            ->assertJsonPath('preview.ingredients.new', 1);
        expect(Ingredient::where('name', 'Uploaded Vanilla')->exists())->toBeFalse();

        $this->actingAs($this->admin)
            ->post(route('admin.costing.data.import'), ['token' => $response->json('token'), 'mode' => 'merge', 'sections' => ['ingredients']])
            ->assertRedirect()
            ->assertSessionHas('success');
        expect(Ingredient::where('name', 'Uploaded Vanilla')->exists())->toBeTrue();
    });

    it('rejects a file that isn\'t a costing export', function () {
        $file = UploadedFile::fake()->createWithContent('other.json', json_encode(['hello' => 'world']));

        $this->actingAs($this->admin)
            ->post(route('admin.costing.data.preview'), ['file' => $file, 'mode' => 'merge'])
            ->assertStatus(422);
    });

    it('is not open to customers', function () {
        $this->actingAs($this->customer)
            ->get(route('admin.costing.data.export', ['sections' => ['ingredients']]))
            ->assertForbidden();
    });
});
