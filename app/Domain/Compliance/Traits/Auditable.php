<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    /**
     * Boot the auditable trait
     */
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            if (!$model->shouldAudit()) {
                return;
            }

            AuditLog::log(
                'created',
                $model,
                null,
                $model->getAuditableAttributes(),
                $model->getAuditMetadata(),
                $model->getAuditTags()
            );
        });

        static::updated(function (Model $model) {
            if (!$model->shouldAudit() || !$model->wasChanged()) {
                return;
            }

            $old = [];
            $new = [];
            
            foreach ($model->getChanges() as $attribute => $value) {
                if (in_array($attribute, $model->getAuditableAttributes())) {
                    $old[$attribute] = $model->getOriginal($attribute);
                    $new[$attribute] = $value;
                }
            }

            if (!empty($old)) {
                AuditLog::log(
                    'updated',
                    $model,
                    $old,
                    $new,
                    $model->getAuditMetadata(),
                    $model->getAuditTags()
                );
            }
        });

        static::deleted(function (Model $model) {
            if (!$model->shouldAudit()) {
                return;
            }

            AuditLog::log(
                'deleted',
                $model,
                $model->getAuditableAttributes(),
                null,
                $model->getAuditMetadata(),
                $model->getAuditTags()
            );
        });
    }

    /**
     * Get the attributes that should be audited
     */
    public function getAuditableAttributes(): array
    {
        if (property_exists($this, 'auditable')) {
            return $this->only($this->auditable);
        }

        return $this->toArray();
    }

    /**
     * Get additional metadata for the audit log
     */
    public function getAuditMetadata(): ?array
    {
        return null;
    }

    /**
     * Get tags for the audit log
     */
    public function getAuditTags(): ?string
    {
        return strtolower(class_basename($this));
    }

    /**
     * Determine if the model should be audited
     */
    public function shouldAudit(): bool
    {
        if (property_exists($this, 'auditEnabled')) {
            return $this->auditEnabled;
        }

        return true;
    }

    /**
     * Get the audit logs for this model
     */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}