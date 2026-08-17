<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One observer audits every admin-context create/edit/delete on content
 * models (spec §5.9). Outside an authenticated admin session (webhook jobs,
 * scheduler) AuditLogger no-ops, so bot traffic never floods the audit log.
 * Meta records WHICH fields changed, never their values.
 */
final class AuditsAdminMutations
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function created(Model $model): void
    {
        $this->audit->log($this->action($model, 'created'), $model);
    }

    public function updated(Model $model): void
    {
        $changed = array_values(array_diff(
            array_keys($model->getChanges()),
            ['updated_at', 'created_at'],
        ));

        if ($changed === []) {
            return;
        }

        $this->audit->log($this->action($model, 'updated'), $model, ['changed' => $changed]);
    }

    public function deleted(Model $model): void
    {
        $this->audit->log($this->action($model, 'deleted'), $model);
    }

    private function action(Model $model, string $verb): string
    {
        return Str::snake(class_basename($model)).'.'.$verb;
    }
}
