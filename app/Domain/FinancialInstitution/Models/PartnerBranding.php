<?php

declare(strict_types=1);

namespace App\Domain\FinancialInstitution\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $partner_id
 * @property string $primary_color
 * @property string $secondary_color
 * @property string|null $accent_color
 * @property string $text_color
 * @property string $background_color
 * @property string|null $logo_url
 * @property string|null $logo_dark_url
 * @property string|null $favicon_url
 * @property string $company_name
 * @property string|null $tagline
 * @property string|null $support_email
 * @property string|null $support_phone
 * @property string|null $privacy_policy_url
 * @property string|null $terms_of_service_url
 * @property string|null $custom_css
 * @property string|null $custom_js
 * @property array|null $widget_config
 * @property bool $is_active
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property FinancialInstitutionPartner $partner
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder active()
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static create(array $attributes = [])
 */
class PartnerBranding extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use UsesTenantConnection;

    protected $table = 'partner_branding';

    protected $fillable = [
        'uuid',
        'partner_id',
        'primary_color',
        'secondary_color',
        'accent_color',
        'text_color',
        'background_color',
        'logo_url',
        'logo_dark_url',
        'favicon_url',
        'company_name',
        'tagline',
        'support_email',
        'support_phone',
        'privacy_policy_url',
        'terms_of_service_url',
        'custom_css',
        'custom_js',
        'widget_config',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'widget_config' => 'array',
        'metadata'      => 'array',
        'is_active'     => 'boolean',
    ];

    protected $attributes = [
        'primary_color'    => '#1a365d',
        'secondary_color'  => '#2b6cb0',
        'text_color'       => '#1a202c',
        'background_color' => '#ffffff',
        'is_active'        => true,
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Get the partner that owns this branding.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitutionPartner::class, 'partner_id');
    }

    /**
     * Scope to filter active branding.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get CSS variables for the branding colors.
     *
     * @return array<string, string>
     */
    public function getCssVariables(): array
    {
        return [
            '--fa-primary-color'    => $this->primary_color,
            '--fa-secondary-color'  => $this->secondary_color,
            '--fa-accent-color'     => $this->accent_color ?? $this->secondary_color,
            '--fa-text-color'       => $this->text_color,
            '--fa-background-color' => $this->background_color,
        ];
    }

    /**
     * Get the CSS variables as a style string.
     */
    public function getCssVariablesString(): string
    {
        $vars = $this->getCssVariables();

        return implode('; ', array_map(
            fn ($key, $value) => "{$key}: {$value}",
            array_keys($vars),
            array_values($vars)
        ));
    }

    /**
     * Check if custom code is allowed (enterprise tier).
     */
    public function canUseCustomCode(): bool
    {
        return $this->partner->tier === 'enterprise';
    }

    /**
     * Get the branding configuration for widgets.
     *
     * @return array<string, mixed>
     */
    public function getWidgetConfig(): array
    {
        return array_merge([
            'colors'        => $this->getCssVariables(),
            'logo'          => $this->logo_url,
            'logo_dark'     => $this->logo_dark_url,
            'company_name'  => $this->company_name,
            'tagline'       => $this->tagline,
            'support_email' => $this->support_email,
        ], $this->widget_config ?? []);
    }
}
