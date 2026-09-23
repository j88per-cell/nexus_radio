<?php

namespace Database\Seeders;

use App\Models\Show;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ShowSeeder extends Seeder
{
    public function run(): void
    {
        Show::updateOrCreate(
            ['name' => 'Free Play'],
            [
                'description'    => 'Continuous shuffle from the full library. Runs 24/7 unless a scheduled show takes over.',
                'theme'          => null,
                'mode'           => Show::MODE_AUTO,
                'status'         => Show::STATUS_ACTIVE,
                'priority'       => 0,
                'recurrence'     => Show::RECURRENCE_INTERVAL,
                'interval_hours' => 12,
                'next_run_at'    => Carbon::now(),
            ]
        );
    }
}
