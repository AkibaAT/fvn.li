<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VnList;
use App\Policies\VnListPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new VnListPolicy;
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

});
