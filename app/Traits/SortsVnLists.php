<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Trait for sorting VN Lists in a standardized order.
 */
trait SortsVnLists
{
    /**
     * Standard list type order priority.
     *
     * @var array<string, int>
     */
    protected array $typeOrder = [
        'plan_to_read' => 1,
        'reading' => 2,
        'completed' => 3,
        'on_hold' => 4,
        'dropped' => 5,
    ];

    /**
     * Sort lists by type order (plan_to_read, reading, completed, on_hold, dropped)
     * followed by custom lists in alphabetical order.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator  $lists
     * @return \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator
     */
    protected function sortListsByType($lists)
    {
        if ($lists instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $lists->setCollection($lists->getCollection()->sortBy(function ($list) {
                if (isset($this->typeOrder[$list->type])) {
                    return $this->typeOrder[$list->type];
                }

                // Custom lists come after standard types, ordered alphabetically
                return 1000 . $list->name;
            }));
        } else {
            $lists = $lists->sort(function ($a, $b) {
                // If both are standard types, use the predefined order
                if (isset($this->typeOrder[$a->type]) && isset($this->typeOrder[$b->type])) {
                    return $this->typeOrder[$a->type] <=> $this->typeOrder[$b->type];
                }

                // If only one is a standard type, it comes first
                if (isset($this->typeOrder[$a->type])) {
                    return -1;
                }
                if (isset($this->typeOrder[$b->type])) {
                    return 1;
                }

                // Both are custom lists, sort alphabetically by name
                return $a->name <=> $b->name;
            });
        }

        return $lists;
    }
}
