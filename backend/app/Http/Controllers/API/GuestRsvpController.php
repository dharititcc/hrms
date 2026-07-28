<?php

namespace App\Http\Controllers\API;

use App\Enums\RsvpStatus;
use App\Http\Controllers\Controller;
use App\Models\MeetingGuest;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public endpoints for external invitees, who have no account.
 *
 * Authorisation is the unguessable invite token alone, so the responses expose
 * only what an invitee needs to decide: never the attendee list, notes,
 * transcript or anything else about the workspace. Routes are rate limited.
 */
class GuestRsvpController extends Controller
{
    public function __construct(private readonly MeetingService $service) {}

    public function show(string $token): JsonResponse
    {
        $guest = $this->resolve($token);
        $meeting = $guest->meeting;

        return response()->json(['data' => [
            'title' => $meeting->title,
            'agenda' => $meeting->agenda,
            'starts_at' => $meeting->starts_at?->toISOString(),
            'ends_at' => $meeting->ends_at?->toISOString(),
            'timezone' => $meeting->timezone,
            'duration_minutes' => $meeting->durationMinutes(),
            'status' => $meeting->status->value,
            'location' => $meeting->location,
            'meeting_link' => $meeting->meeting_link,
            'guest_name' => $guest->name,
            'guest_email' => $guest->email,
            'rsvp' => $guest->rsvp->value,
        ]]);
    }

    public function respond(Request $request, string $token): JsonResponse
    {
        $guest = $this->resolve($token);

        $validated = $request->validate([
            'rsvp' => ['required', Rule::enum(RsvpStatus::class)],
        ]);

        $guest = $this->service->respondAsGuest($guest, RsvpStatus::from($validated['rsvp']));

        return response()->json([
            'message' => 'Response recorded.',
            'data' => ['rsvp' => $guest->rsvp->value, 'responded_at' => $guest->responded_at?->toISOString()],
        ]);
    }

    private function resolve(string $token): MeetingGuest
    {
        // A soft-deleted meeting leaves its invite links dead rather than
        // resolving to a meeting that no longer exists.
        return MeetingGuest::query()
            ->where('invite_token', $token)
            ->whereHas('meeting')
            ->with('meeting')
            ->firstOrFail();
    }
}
