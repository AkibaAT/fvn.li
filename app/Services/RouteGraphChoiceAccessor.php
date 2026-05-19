<?php

declare(strict_types=1);

namespace App\Services;

class RouteGraphChoiceAccessor
{
    public function menuLine($choice): int
    {
        return (int) ($choice->menu_line ?? 0);
    }

    public function menuBranch($choice): ?string
    {
        $branch = trim((string) ($choice->menu_branch ?? ''));

        return $branch === '' ? null : $branch;
    }

    public function parentMenuLine($choice): int
    {
        return (int) ($choice->parent_menu_line ?? 0);
    }

    public function parentChoiceLine($choice): int
    {
        return (int) ($choice->parent_choice_line ?? 0);
    }

    public function prompt($choice): string
    {
        return trim((string) ($choice->prompt ?? ''));
    }
}
