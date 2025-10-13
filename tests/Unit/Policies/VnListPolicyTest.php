<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VnList;
use App\Policies\VnListPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new VnListPolicy();
    $this->owner = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

describe('view policy', function () {
    test('allows anyone to view public lists', function () {
        $publicList = VnList::factory()->for($this->owner)->create([
            'is_public' => true,
        ]);

        expect($this->policy->view(null, $publicList))->toBeTrue()
            ->and($this->policy->view($this->otherUser, $publicList))->toBeTrue()
            ->and($this->policy->view($this->owner, $publicList))->toBeTrue();
    });

    test('only allows owner to view private lists', function () {
        $privateList = VnList::factory()->for($this->owner)->create([
            'is_public' => false,
        ]);

        expect($this->policy->view(null, $privateList))->toBeFalse()
            ->and($this->policy->view($this->otherUser, $privateList))->toBeFalse()
            ->and($this->policy->view($this->owner, $privateList))->toBeTrue();
    });

    test('handles guest users correctly for private lists', function () {
        $privateList = VnList::factory()->for($this->owner)->create([
            'is_public' => false,
        ]);

        expect($this->policy->view(null, $privateList))->toBeFalse();
    });
});

describe('update policy', function () {
    test('allows owner to update their own lists', function () {
        $list = VnList::factory()->for($this->owner)->create();

        expect($this->policy->update($this->owner, $list))->toBeTrue();
    });

    test('prevents non-owners from updating lists', function () {
        $list = VnList::factory()->for($this->owner)->create();

        expect($this->policy->update($this->otherUser, $list))->toBeFalse();
    });

    test('allows owner to update default lists', function () {
        $defaultList = VnList::factory()->for($this->owner)->create([
            'is_default' => true,
        ]);

        expect($this->policy->update($this->owner, $defaultList))->toBeTrue();
    });

    test('allows owner to update public lists', function () {
        $publicList = VnList::factory()->for($this->owner)->create([
            'is_public' => true,
        ]);

        expect($this->policy->update($this->owner, $publicList))->toBeTrue();
    });
});

describe('delete policy', function () {
    test('allows owner to delete custom lists', function () {
        $customList = VnList::factory()->for($this->owner)->create([
            'is_default' => false,
        ]);

        expect($this->policy->delete($this->owner, $customList))->toBeTrue();
    });

    test('prevents deletion of default lists', function () {
        $defaultList = VnList::factory()->for($this->owner)->create([
            'is_default' => true,
        ]);

        expect($this->policy->delete($this->owner, $defaultList))->toBeFalse();
    });

    test('prevents non-owners from deleting lists', function () {
        $list = VnList::factory()->for($this->owner)->create([
            'is_default' => false,
        ]);

        expect($this->policy->delete($this->otherUser, $list))->toBeFalse();
    });

    test('prevents deletion of default lists even if owner', function () {
        $defaultList = VnList::factory()->for($this->owner)->create([
            'is_default' => true,
            'type' => 'reading',
        ]);

        expect($this->policy->delete($this->owner, $defaultList))->toBeFalse();
    });
});

describe('edge cases', function () {
    test('handles multiple users with same list name', function () {
        $list1 = VnList::factory()->for($this->owner)->create([
            'name' => 'My Favorites',
            'is_public' => false,
        ]);

        $list2 = VnList::factory()->for($this->otherUser)->create([
            'name' => 'My Favorites',
            'is_public' => false,
        ]);

        expect($this->policy->view($this->owner, $list1))->toBeTrue()
            ->and($this->policy->view($this->owner, $list2))->toBeFalse()
            ->and($this->policy->view($this->otherUser, $list1))->toBeFalse()
            ->and($this->policy->view($this->otherUser, $list2))->toBeTrue();
    });

    test('handles public default lists correctly', function () {
        $publicDefaultList = VnList::factory()->for($this->owner)->create([
            'is_default' => true,
            'is_public' => true,
        ]);

        // Anyone can view
        expect($this->policy->view(null, $publicDefaultList))->toBeTrue()
            ->and($this->policy->view($this->otherUser, $publicDefaultList))->toBeTrue();

        // Only owner can update
        expect($this->policy->update($this->owner, $publicDefaultList))->toBeTrue()
            ->and($this->policy->update($this->otherUser, $publicDefaultList))->toBeFalse();

        // No one can delete (it's default)
        expect($this->policy->delete($this->owner, $publicDefaultList))->toBeFalse();
    });
});

