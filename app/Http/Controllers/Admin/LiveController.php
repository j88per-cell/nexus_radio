<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Show;
use App\Services\LiquidsoapService;
use Inertia\Inertia;
use Inertia\Response;

class LiveController extends Controller
{
    public function index(LiquidsoapService $liquidsoap): Response
    {
        $liveShow = Show::where('status', Show::STATUS_LIVE)
            ->where('mode', Show::MODE_MANUAL)
            ->first();

        return Inertia::render('Admin/Live', [
            'liveShow' => $liveShow ? [
                'id'      => $liveShow->id,
                'name'    => $liveShow->name,
                'shuffle' => $liveShow->shuffle,
            ] : null,
            'harbor' => [
                'host'     => config('radio.harbor.host'),
                'port'     => config('radio.harbor.port'),
                'mount'    => config('radio.harbor.mount'),
                'password' => config('radio.harbor.password'),
            ],
            'muted'  => $liquidsoap->isLiveMuted(),
            'status' => $liquidsoap->liveStatus(),
        ]);
    }

    public function mute(LiquidsoapService $liquidsoap)
    {
        $liquidsoap->muteLive();

        return back();
    }

    public function unmute(LiquidsoapService $liquidsoap)
    {
        $liquidsoap->unmuteLive();

        return back();
    }
}
