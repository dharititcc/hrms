<?php

namespace App\Http\Controllers\API;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAbility(Ability::View), 403);

        return response()->json(['data' => $this->service->summary($request->user())]);
    }
}
