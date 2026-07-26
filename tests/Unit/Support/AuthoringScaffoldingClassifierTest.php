<?php

declare(strict_types=1);

use App\Support\Routes\AuthoringScaffoldingClassifier;

/**
 * The label and path names here are taken from shipped Ren'Py releases, where
 * the developer's harnesses are still present in the build.
 */
it('recognises harness labels by name', function (string $name) {
    expect((new AuthoringScaffoldingClassifier)->isScaffolding($name, 'game/script.rpy'))->toBeTrue();
})->with([
    'test_ prefix' => ['test_controller_input'],
    '_test suffix' => ['numpad_label_test'],
    'testing_ prefix' => ['TESTING_SHADERS'],
    '_testing suffix' => ['phone_text_testing'],
    'workspace suffix' => ['mauri_test_workspace'],
    'workspace_label fragment' => ['flashback_workspace_label'],
    'debug prefix' => ['debug_camera'],
]);

it('recognises harness labels by the file they live in', function (string $path) {
    expect((new AuthoringScaffoldingClassifier)->isScaffolding('stage3_outro', $path))->toBeTrue();
})->with([
    'tests directory' => ['game/code/tests/manual_music_test.rpy'],
    'workspace file' => ['game/workspace.rpy'],
    'prefixed workspace file' => ['game/code/talk_multiple_workspace.rpy'],
    'wkspace file' => ['game/code/tests/new_press_wkspace.rpy'],
    'dump file' => ['game/dump.rpy'],
    'compiled workspace' => ['Scripts/workspace.rpyc'],
]);

it('leaves story labels alone', function (string $name, string $path) {
    expect((new AuthoringScaffoldingClassifier)->isScaffolding($name, $path))->toBeFalse();
})->with([
    'chapter intro' => ['stage4_intro', 'game/scenes/scenes_st4.rpy'],
    'side route' => ['SR_Spencer', 'Scripts/SR_Spencer.rpyc'],
    'start' => ['start', 'game/script.rpy'],
    // "contest" ends in "test" but is not a harness.
    'word ending in test' => ['contest', 'game/script.rpy'],
    // A real scene may still be authored in a file whose name mentions a pitch.
    'pitch content' => ['pitch_st4_outro', 'game/code/pitch.rpy'],
]);

it('ignores the synthetic ending suffix when classifying', function () {
    $classifier = new AuthoringScaffoldingClassifier;

    expect($classifier->isScaffolding('numpad_label_test:ending', 'game/code/ui/numpad_puzzle.rpy'))->toBeTrue()
        ->and($classifier->isScaffolding('SR_Spencer:ending', 'Scripts/SR_Spencer.rpyc'))->toBeFalse();
});
