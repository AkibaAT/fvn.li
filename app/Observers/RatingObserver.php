<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Rating;
use Illuminate\Support\Facades\DB;

class RatingObserver
{
    public function created(Rating $rating): void
    {
        DB::table('raters')
            ->where('id', $rating->rater_id)
            ->update(['weight_calculated_at' => null]);
    }

    public function updated(Rating $rating): void
    {
        if ($rating->isDirty('rating')) {
            DB::table('raters')
                ->where('id', $rating->rater_id)
                ->update(['weight_calculated_at' => null]);
        }
    }
}
