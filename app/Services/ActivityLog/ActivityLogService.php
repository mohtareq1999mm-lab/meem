<?php

namespace App\Services\ActivityLog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityLogService
{
    public function logCreated(Model $subject, string $logName = 'default', ?string $description = null): ?Activity
    {
        $resource = Str::snake(class_basename($subject));
        $description ??= __('activity.' . $resource . '_created') ?: class_basename($subject) . ' created';

        return $this->log($subject, 'created', $logName, $description);
    }

    public function logUpdated(Model $subject, array $extraProperties = [], string $logName = 'default', ?string $description = null): ?Activity
    {
        $dirty = $subject->getDirty();
        if (empty($dirty) && empty($extraProperties)) {
            return null;
        }

        $original = $subject->getOriginal();
        $oldValues = [];
        $newValues = [];

        foreach ($dirty as $key => $newValue) {
            if (in_array($key, ['updated_at', 'remember_token', 'created_at'])) {
                continue;
            }
            $oldValues[$key] = array_key_exists($key, $original) ? $original[$key] : null;
            $newValues[$key] = $newValue;
        }

        $oldValues = array_merge($oldValues, $extraProperties['old'] ?? []);
        $newValues = array_merge($newValues, $extraProperties['new'] ?? []);

        if (empty($oldValues) && empty($newValues)) {
            return null;
        }

        $resource = Str::snake(class_basename($subject));
        $description ??= __('activity.' . $resource . '_updated') ?: class_basename($subject) . ' updated';

        return activity($logName)
            ->performedOn($subject)
            ->withProperties(['old' => $oldValues, 'new' => $newValues])
            ->event('updated')
            ->log($description);
    }

    public function logStatusChange(Model $subject, string $oldStatus, string $newStatus, string $description, string $logName = 'default'): ?Activity
    {
        return activity($logName)
            ->performedOn($subject)
            ->withProperties([
                'old' => ['status' => $oldStatus],
                'new' => ['status' => $newStatus],
                'previous_status' => $oldStatus,
                'new_status' => $newStatus,
            ])
            ->event('statusChanged')
            ->log($description);
    }

    public function logDeleted(Model $subject, string $logName = 'default', ?string $description = null): ?Activity
    {
        $resource = Str::snake(class_basename($subject));
        $description ??= __('activity.' . $resource . '_deleted') ?: class_basename($subject) . ' deleted';

        return $this->log($subject, 'deleted', $logName, $description);
    }

    public function logRestored(Model $subject, string $logName = 'default', ?string $description = null): ?Activity
    {
        $resource = Str::snake(class_basename($subject));
        $description ??= __('activity.' . $resource . '_restored') ?: class_basename($subject) . ' restored';

        return $this->log($subject, 'restored', $logName, $description);
    }

    public function logForceDeleted(Model $subject, string $logName = 'default', ?string $description = null): ?Activity
    {
        $resource = Str::snake(class_basename($subject));
        $description ??= __('activity.' . $resource . '_force_deleted') ?: class_basename($subject) . ' force deleted';

        return $this->log($subject, 'forceDeleted', $logName, $description);
    }

    public function logCustom(Model $subject, string $event, string $description, array $properties = [], string $logName = 'default'): ?Activity
    {
        return activity($logName)
            ->performedOn($subject)
            ->withProperties($properties)
            ->event($event)
            ->log($description);
    }

    public function logCustomWithoutSubject(string $event, string $description, array $properties = [], string $logName = 'default'): ?Activity
    {
        return activity($logName)
            ->withProperties($properties)
            ->event($event)
            ->log($description);
    }

    private function log(Model $subject, string $event, string $logName, string $description): ?Activity
    {
        return activity($logName)
            ->performedOn($subject)
            ->event($event)
            ->log($description);
    }
}
