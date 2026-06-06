<?php

declare(strict_types=1);

namespace App\Services;

class RouteGraphIdFactory
{
    public function edgeIdentity($edge): string
    {
        if (! empty($edge->id)) {
            return 'id:' . $edge->id;
        }

        return implode('|', [
            (string) ($edge->from_label ?? ''),
            (string) ($edge->to_label ?? ''),
            (string) ($edge->edge_type ?? ''),
            (string) ($edge->condition ?? ''),
            (string) ($edge->file_path ?? ''),
            (string) ($edge->line_number ?? ''),
        ]);
    }

    public function routeEdgeId($edge): string
    {
        if (! empty($edge->id)) {
            return 'route_edge:' . $edge->id;
        }

        return 'route_edge:' . md5($this->edgeIdentity($edge));
    }

    public function generatedChoiceEdgeId(string $choiceId, $edge): string
    {
        if (! empty($edge->id)) {
            return $choiceId . ':route_edge:' . $edge->id;
        }

        return $choiceId . ':route_edge:' . md5($this->edgeIdentity($edge));
    }

    public function syntheticEndingId(string $labelName): string
    {
        return $labelName . ':ending';
    }
}
