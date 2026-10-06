<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<GameVersion>
 */
class GameVersionBuilder extends Builder
{
    /**
     * @param  array<int, string>|string  $columns
     * @return array<int, GameVersion>
     */
    public function getModels($columns = ['*'])
    {
        if ($columns === ['*'] && $this->query->columns === null && empty($this->query->joins)) {
            $columns = $this->model->qualifyColumns(GameVersion::eagerColumns());
        }

        return parent::getModels($columns);
    }
}
