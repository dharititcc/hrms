<?php

namespace App\Repositories;

use App\Enums\Action;
use App\Enums\Module;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MeetingRepository
{
    private const WITH = ['host', 'organizer', 'participants.user', 'guests', 'tags'];

    public function paginateForOwner(int $ownerId, array $filters = [], ?User $viewer = null): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, ['starts_at', 'title', 'created_at'], strict: true)
            ? $filters['sort']
            : 'starts_at';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return Meeting::query()
            ->where('owner_id', $ownerId)
            ->with(self::WITH)
            // Without meetings.view-all — a Client — only meetings they are
            // part of, host, or organise.
            ->when(
                $viewer !== null && ! $viewer->hasPermission(Module::Meetings, Action::ViewAll),
                fn ($query) => $query->where(function ($query) use ($viewer): void {
                    $query->whereHas('participants', fn ($q) => $q->where('user_id', $viewer->id))
                        ->orWhere('host_id', $viewer->id)
                        ->orWhere('organizer_id', $viewer->id);
                }),
            )
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('agenda', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, array $status) => $query->whereIn('status', $status))
            ->when($filters['type'] ?? null, fn ($query, array $type) => $query->whereIn('type', $type))
            ->when($filters['host_id'] ?? null, fn ($query, int $hostId) => $query->where('host_id', $hostId))
            ->when(
                $filters['participant_id'] ?? null,
                fn ($query, int $userId) => $query->where(function ($query) use ($userId): void {
                    // "My meetings" means ones I attend or run, not only those
                    // I was formally added to as a participant.
                    $query->whereHas('participants', fn ($participants) => $participants->where('user_id', $userId))
                        ->orWhere('host_id', $userId)
                        ->orWhere('organizer_id', $userId);
                }),
            )
            ->when(
                ($filters['from'] ?? null) && ($filters['to'] ?? null),
                fn ($query) => $query->between($filters['from'], $filters['to']),
            )
            ->when(($filters['period'] ?? null) === 'upcoming', fn ($query) => $query->upcoming())
            ->when(($filters['period'] ?? null) === 'past', fn ($query) => $query->past())
            ->orderBy($sort, $direction)
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    public function loadDetails(Meeting $meeting): Meeting
    {
        return $meeting->load(self::WITH);
    }
}
