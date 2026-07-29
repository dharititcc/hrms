<?php

namespace App\Services;

use App\Models\AuditLog;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /** Never written to the log, even if they appear in a model's changes. */
    private const REDACTED = ['password', 'remember_token', 'api_token'];

    /** Stands in for a value too sensitive to keep, where the change still matters. */
    private const WITHHELD = '[redacted]';

    public function log(string $action, Model $model, array $metadata = []): ?AuditLog
    {
        $ownerId = $model->getAttribute('owner_id') ?? Auth::user()?->workspaceOwnerId();

        // Models outside a workspace (e.g. the User itself) are not logged here.
        if ($ownerId === null) {
            return null;
        }

        return AuditLog::create([
            'owner_id' => $ownerId,
            'user_id' => Auth::id(),
            'action' => $action,
            'entity' => $model->getMorphClass(),
            'entity_id' => $model->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * Builds the before/after payload for an update, dropping noise and secrets.
     *
     * @return array{changed: list<string>, old: array<string, mixed>, new: array<string, mixed>}|null
     */
    public function changeSet(Model $model): ?array
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);

        foreach (self::REDACTED as $key) {
            unset($changes[$key]);
        }

        if ($changes === []) {
            return null;
        }

        $original = $model->getOriginal();

        return [
            'changed' => array_keys($changes),
            'old' => $this->present(array_intersect_key($original, $changes), $model),
            'new' => $this->present($changes, $model),
        ];
    }

    /**
     * Normalises values, withholding those a model marks as sensitive.
     *
     * The attribute name stays in "changed" — that somebody's bank account was
     * altered is exactly what an audit trail is for — but the number itself is
     * not copied into a JSON column that is far easier to read than the
     * encrypted original.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function present(array $values, Model $model): array
    {
        $sensitive = method_exists($model, 'redactedAuditAttributes')
            ? $model->redactedAuditAttributes()
            : [];

        $presented = [];

        foreach ($values as $key => $value) {
            $presented[$key] = in_array($key, $sensitive, strict: true)
                ? self::WITHHELD
                : $this->normalize($value);
        }

        return $presented;
    }

    /** Enums and dates must become scalars before they hit the JSON column. */
    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            is_scalar($value), is_null($value) => $value,
            default => (string) $value,
        };
    }
}
