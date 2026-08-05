<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\LanguageMapping;
use App\Models\UniqueDialogueText;
use App\Traits\SortsVnLists;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

function invokeSmokeMethod(object $object, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionClass($object);
    $methodReflection = $reflection->getMethod($method);
    $methodReflection->setAccessible(true);

    return $methodReflection->invokeArgs($object, $arguments);
}

it('covers VN list sorting for collections and paginators', function () {
    $sorter = new class
    {
        use SortsVnLists;

        public function sort($lists)
        {
            return $this->sortListsByType($lists);
        }
    };

    $lists = collect([
        (object) ['type' => 'custom', 'name' => 'Zeta'],
        (object) ['type' => 'completed', 'name' => 'Completed'],
        (object) ['type' => 'reading', 'name' => 'Reading'],
        (object) ['type' => 'custom', 'name' => 'Alpha'],
    ]);

    expect($sorter->sort($lists)->pluck('name')->all())->toBe(['Reading', 'Completed', 'Alpha', 'Zeta']);

    $paginator = new LengthAwarePaginator($lists, 4, 10);
    expect($sorter->sort($paginator)->getCollection()->pluck('name')->all())->toBe(['Reading', 'Completed', 'Alpha', 'Zeta']);
});

it('covers language mapping lookup precedence and unique dialogue text metadata accessors', function () {
    $game = Game::factory()->create();
    LanguageMapping::create([
        'game_id' => null,
        'game_language_key' => 'default',
        'iso_code' => 'eng',
    ]);
    LanguageMapping::create([
        'game_id' => $game->id,
        'game_language_key' => 'default',
        'iso_code' => 'deu',
    ]);

    expect(LanguageMapping::getIsoCodeForKey('default'))->toBe('eng')
        ->and(LanguageMapping::getIsoCodeForKey('default', $game->id))->toBe('deu')
        ->and(LanguageMapping::getIsoCodeForKey('missing', $game->id))->toBeNull()
        ->and((new LanguageMapping)->language())->toBeInstanceOf(BelongsTo::class)
        ->and((new LanguageMapping)->game())->toBeInstanceOf(BelongsTo::class);

    $text = new UniqueDialogueText(['text_content' => 'Hello world']);
    $text->searchMetadata = [
        'usage_count' => 3,
        'games_count' => 2,
        'game_ids' => [1, 2],
        'game_names' => ['A', 'B'],
        'version_ids' => [10],
        'character_ids' => [20],
        'character_names' => ['Hero'],
        'languages' => ['eng'],
    ];

    expect($text->dialogueLines())->toBeInstanceOf(HasMany::class)
        ->and($text->usage_count)->toBe(3)
        ->and($text->games_count)->toBe(2)
        ->and($text->game_ids)->toBe([1, 2])
        ->and($text->game_names)->toBe(['A', 'B'])
        ->and($text->version_ids)->toBe([10])
        ->and($text->character_ids)->toBe([20])
        ->and($text->character_names)->toBe(['Hero'])
        ->and($text->languages)->toBe(['eng'])
        ->and(invokeSmokeMethod($text, 'getTsvectorColumnForLanguage', ['german']))->toBe('search_vector_german')
        ->and(invokeSmokeMethod($text, 'getTsvectorColumnForLanguage', ['italian']))->toBe('search_vector')
        ->and(invokeSmokeMethod($text, 'getLanguageConfig', ['spanish']))->toBe('spanish')
        ->and(invokeSmokeMethod($text, 'getLanguageConfig', [null]))->toBe('english');
});
