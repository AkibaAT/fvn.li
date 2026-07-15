<?php

declare(strict_types=1);

namespace App\Services;

class RouteGraphIdFactory
{
    public const MAX_SYNTHETIC_ID_LENGTH = 255;

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
        $suffix = ':ending';
        $id = $labelName . $suffix;
        if (mb_strlen($id) <= self::MAX_SYNTHETIC_ID_LENGTH) {
            return $id;
        }

        $hashSuffix = ':' . substr(hash('sha256', $labelName), 0, 16) . $suffix;
        $prefixLength = self::MAX_SYNTHETIC_ID_LENGTH - mb_strlen($hashSuffix);

        return mb_substr($labelName, 0, $prefixLength) . $hashSuffix;
    }
}
