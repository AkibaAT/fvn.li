<?php

declare(strict_types=1);

use App\Http\Requests\AddGameToCustomListRequest;
use App\Http\Requests\ToggleAllUpdatesRequest;
use App\Models\DiscordServerMember;
use App\Models\DiscordServerTag;
use App\Models\Game;
use App\Models\LanguageMapping;
use App\Models\NotificationHistory;
use App\Models\ProcessedEvent;
use App\Models\PushSubscription;
use App\Models\UniqueDialogueText;
use App\Models\UserNotificationPreferences;
use App\Models\UserPreference;
use App\Models\VersionFileType;
use App\Models\VersionRouteEdge;
use App\Models\VersionRouteLabel;
use App\Models\VersionRouteMenuChoice;
use App\Models\VersionRoutePath;
use App\Models\VersionRouteVariable;
use App\Models\VersionRouteVariableChange;
use App\Services\HelperService;
use App\Traits\HasSortableColumns;
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

it('covers request validation helpers and byte formatting utilities', function () {
    expect((new AddGameToCustomListRequest)->authorize())->toBeTrue()
        ->and((new AddGameToCustomListRequest)->rules())->toBe([
            'game_id' => ['required', 'exists:games,id'],
        ])
        ->and((new ToggleAllUpdatesRequest)->authorize())->toBeTrue()
        ->and((new ToggleAllUpdatesRequest)->rules())->toBe([
            'receive_updates' => ['required', 'boolean'],
        ])
        ->and(HelperService::formatBytes(0))->toBe('0 B')
        ->and(HelperService::formatBytes(1536, 1))->toBe('1.5 KB')
        ->and(HelperService::formatBytes(1048576))->toBe('1 MB');
});

it('covers sortable column and VN list sorting traits', function () {
    $sortable = new class
    {
        use HasSortableColumns;

        public const AVAILABLE_SORT_FIELDS = ['name', 'english_word_count', 'custom_field'];
    };

    expect($sortable->getAvailableSortFields())->toBe(['name', 'english_word_count', 'custom_field'])
        ->and($sortable->getAvailableSortFieldsWithLabels())->toBe([
            'name' => 'Name',
            'english_word_count' => 'Word Count',
            'custom_field' => 'Custom field',
        ])
        ->and(invokeSmokeMethod($sortable, 'getSortLabelLowercase', ['rating_count']))->toBe('rating count');

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

it('covers simple relation models and query scopes without database writes', function () {
    expect((new DiscordServerMember)->discordServer())->toBeInstanceOf(BelongsTo::class)
        ->and((new DiscordServerMember)->user())->toBeInstanceOf(BelongsTo::class)
        ->and(DiscordServerMember::admins()->toRawSql())->toContain('"is_admin" = 1')
        ->and(DiscordServerMember::nonAdmins()->toRawSql())->toContain('"is_admin" = 0')
        ->and(DiscordServerMember::linked()->toRawSql())->toContain('"user_id" is not null')
        ->and(DiscordServerMember::unlinked()->toRawSql())->toContain('"user_id" is null')
        ->and((new DiscordServerTag)->discordServer())->toBeInstanceOf(BelongsTo::class)
        ->and(DiscordServerTag::subscribed()->toRawSql())->toContain('"is_subscribed" = 1')
        ->and(DiscordServerTag::unsubscribed()->toRawSql())->toContain('"is_subscribed" = 0')
        ->and((new NotificationHistory)->user())->toBeInstanceOf(BelongsTo::class)
        ->and((new NotificationHistory)->game())->toBeInstanceOf(BelongsTo::class)
        ->and((new NotificationHistory)->gameVersion())->toBeInstanceOf(BelongsTo::class)
        ->and((new ProcessedEvent)->game())->toBeInstanceOf(BelongsTo::class)
        ->and((new PushSubscription)->user())->toBeInstanceOf(BelongsTo::class)
        ->and((new UserNotificationPreferences)->user())->toBeInstanceOf(BelongsTo::class)
        ->and((new UserPreference)->user())->toBeInstanceOf(BelongsTo::class)
        ->and((new VersionFileType)->category())->toBeInstanceOf(BelongsTo::class);
});

it('covers route graph relation models and casts', function () {
    expect((new VersionRouteEdge)->gameVersion())->toBeInstanceOf(BelongsTo::class)
        ->and((new VersionRouteLabel(['is_ending' => true, 'returns_to_caller' => false]))->is_ending)->toBeTrue()
        ->and((new VersionRouteLabel)->gameVersion())->toBeInstanceOf(BelongsTo::class)
        ->and((new VersionRouteMenuChoice([
            'translations' => ['en' => 'Go'],
            'prompt_translations' => ['en' => 'Choose'],
            'menu_condition_stack' => ['seen_intro'],
        ]))->translations)->toBe(['en' => 'Go'])
        ->and((new VersionRouteMenuChoice)->gameVersion())->toBeInstanceOf(BelongsTo::class)
        ->and((new VersionRoutePath(['path_labels' => ['start'], 'choices' => [['text' => 'Go']]]))->path_labels)->toBe(['start'])
        ->and((new VersionRoutePath)->gameVersion())->toBeInstanceOf(BelongsTo::class)
        ->and((new VersionRouteVariable)->gameVersion())->toBeInstanceOf(BelongsTo::class)
        ->and((new VersionRouteVariableChange(['condition_stack' => ['a', 'b']]))->condition_stack)->toBe(['a', 'b'])
        ->and((new VersionRouteVariableChange)->gameVersion())->toBeInstanceOf(BelongsTo::class);
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
