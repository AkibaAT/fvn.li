<?php

declare(strict_types=1);

use App\Services\MeilisearchEmbedderConfigurator;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

function embedderConfiguratorFor(Indexes $index): MeilisearchEmbedderConfigurator
{
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index')->with('games')->andReturn($index);
    $client->shouldReceive('waitForTask')
        ->andReturnUsing(fn (int $uid): array => $uid === 7
            ? ['status' => 'succeeded']
            : ['status' => 'failed', 'error' => ['message' => 'model appears to be unsupported']]);

    return new MeilisearchEmbedderConfigurator($client);
}

beforeEach(function () {
    config([
        'scout.meilisearch.index-embedders' => [
            'games' => [
                'default' => [
                    'source' => 'huggingFace',
                    'model' => 'BAAI/bge-base-en-v1.5',
                ],
            ],
        ],
    ]);
});

it('leaves an embedder alone when the configured keys already match', function () {
    $index = Mockery::mock(Indexes::class);
    // Meilisearch reports the defaults it filled in alongside the configured keys.
    $index->shouldReceive('getEmbedders')->once()->andReturn([
        'default' => [
            'source' => 'huggingFace',
            'model' => 'BAAI/bge-base-en-v1.5',
            'pooling' => 'useModel',
            'documentTemplateMaxBytes' => 400,
        ],
    ]);
    $index->shouldNotReceive('updateEmbedders');

    expect(embedderConfiguratorFor($index)->ensure())
        ->toBe([['index' => 'games', 'status' => 'unchanged', 'model' => 'BAAI/bge-base-en-v1.5']]);
});

it('applies the embedder when the model differs', function () {
    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('getEmbedders')->once()->andReturn([
        'default' => ['source' => 'huggingFace', 'model' => 'nomic-ai/nomic-embed-text-v1.5'],
    ]);
    $index->shouldReceive('updateEmbedders')->once()->andReturn(['taskUid' => 7]);

    expect(embedderConfiguratorFor($index)->ensure())
        ->toBe([['index' => 'games', 'status' => 'applied', 'model' => 'BAAI/bge-base-en-v1.5']]);
});

it('reports the Meilisearch error when the embedder task fails', function () {
    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('getEmbedders')->once()->andReturn([]);
    $index->shouldReceive('updateEmbedders')->once()->andReturn(['taskUid' => 99]);

    expect(embedderConfiguratorFor($index)->ensure())->toBe([[
        'index' => 'games',
        'status' => 'failed',
        'model' => 'BAAI/bge-base-en-v1.5',
        'message' => 'model appears to be unsupported',
    ]]);
});

it('treats an index with no embedders at all as needing one', function () {
    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('getEmbedders')->once()->andReturn(null);
    $index->shouldReceive('updateEmbedders')->once()->andReturn(['taskUid' => 7]);

    expect(embedderConfiguratorFor($index)->ensure())
        ->toBe([['index' => 'games', 'status' => 'applied', 'model' => 'BAAI/bge-base-en-v1.5']]);
});
