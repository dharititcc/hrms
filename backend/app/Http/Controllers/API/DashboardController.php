<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    /**
     * The dashboard is every signed-in member's landing page, so it is not
     * gated as a whole. Each section is omitted instead when the caller lacks
     * permission for that module.
     */
    public function stats(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->summary($request->user())]);
    }
}
