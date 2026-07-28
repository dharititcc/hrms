<?php

namespace App\Http\Controllers\API;

use App\Enums\RsvpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Meeting\IndexMeetingRequest;
use App\Http\Requests\Meeting\StoreMeetingRequest;
use App\Http\Requests\Meeting\UpdateMeetingRequest;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Services\MeetingService;
use App\Support\WorkspaceUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingController extends Controller
{
    public function __construct(private readonly MeetingService $service) {}

    public function index(IndexMeetingRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Meeting::class);

        return MeetingResource::collection(
            $this->service->list($request->user()->workspaceOwnerId(), $request->validated()),
        )->response();
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $this->authorize('create', Meeting::class);

        return (new MeetingResource($this->service->create($request->user(), $request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Meeting $meeting): MeetingResource
    {
        $this->authorize('view', $meeting);

        return new MeetingResource($this->service->find($meeting));
    }

    public function update(UpdateMeetingRequest $request, Meeting $meeting): MeetingResource
    {
        $this->authorize('update', $meeting);

        return new MeetingResource($this->service->update($meeting, $request->user(), $request->validated()));
    }

    public function reschedule(Request $request, Meeting $meeting): MeetingResource
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'string', 'max:64', 'timezone'],
        ]);

        return new MeetingResource($this->service->reschedule($meeting, $request->user(), $validated));
    }

    public function cancel(Request $request, Meeting $meeting): MeetingResource
    {
        $this->authorize('update', $meeting);

        return new MeetingResource($this->service->cancel($meeting, $request->user()));
    }

    public function duplicate(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);
        $this->authorize('create', Meeting::class);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        return (new MeetingResource($this->service->duplicate($this->service->find($meeting), $request->user(), $validated)))
            ->response()
            ->setStatusCode(201);
    }

    public function invite(Request $request, Meeting $meeting): MeetingResource
    {
        $this->authorize('invite', $meeting);

        $validated = $request->validate([
            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', Rule::in(WorkspaceUsers::idsFor($request->user()->workspaceOwnerId()))],
            'guests' => ['nullable', 'array'],
            'guests.*.email' => ['required', 'email', 'max:255'],
            'guests.*.name' => ['nullable', 'string', 'max:255'],
        ]);

        return new MeetingResource($this->service->invite(
            $meeting,
            $validated['participant_ids'] ?? [],
            $validated['guests'] ?? [],
        ));
    }

    /** Answers for the caller only; nobody can RSVP on another's behalf. */
    public function respond(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('respond', $meeting);

        $validated = $request->validate([
            'rsvp' => ['required', Rule::enum(RsvpStatus::class)],
        ]);

        $participant = $this->service->respond($meeting, $request->user(), RsvpStatus::from($validated['rsvp']));

        return response()->json(['data' => ['rsvp' => $participant->rsvp->value, 'responded_at' => $participant->responded_at?->toISOString()]]);
    }

    public function attendance(Request $request, Meeting $meeting): MeetingResource
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'participants' => ['nullable', 'array'],
            'participants.*' => ['boolean'],
            'guests' => ['nullable', 'array'],
            'guests.*' => ['boolean'],
        ]);

        return new MeetingResource($this->service->recordAttendance(
            $meeting,
            $validated['participants'] ?? [],
            $validated['guests'] ?? [],
        ));
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorize('delete', $meeting);
        $this->service->delete($meeting);

        return response()->json(['message' => 'Meeting deleted.']);
    }
}
