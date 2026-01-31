<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $category
 * @property string $system_prompt
 * @property string $user_template
 * @property array|null $variables
 * @property array|null $metadata
 * @property string $version
 * @property bool $is_active
 * @property int $usage_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder active()
 * @method static \Illuminate\Database\Eloquent\Builder category(string $category)
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static static create(array $attributes = [])
 * @method static int count(string $columns = '*')
 */
class AiPromptTemplate extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use UsesTenantConnection;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public const CATEGORY_QUERY = 'query';

    public const CATEGORY_ANALYSIS = 'analysis';

    public const CATEGORY_COMPLIANCE = 'compliance';

    public const CATEGORY_CODE_GENERATION = 'code_generation';

    protected $fillable = [
        'uuid',
        'name',
        'category',
        'system_prompt',
        'user_template',
        'variables',
        'metadata',
        'version',
        'is_active',
        'usage_count',
    ];

    protected $casts = [
        'variables' => 'array',
        'metadata'  => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active'   => true,
        'usage_count' => 0,
        'version'     => '1.0',
    ];

    /**
     * Get valid categories.
     *
     * @return array<string>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_QUERY,
            self::CATEGORY_ANALYSIS,
            self::CATEGORY_COMPLIANCE,
            self::CATEGORY_CODE_GENERATION,
        ];
    }

    /**
     * Scope to filter active templates.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $category
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Render the user template with provided variables.
     *
     * @param array<string, mixed> $variables
     * @return string
     */
    public function renderUserTemplate(array $variables = []): string
    {
        $template = $this->user_template;

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, (string) $value, $template);
        }

        return $template;
    }

    /**
     * Get required variables from template.
     *
     * @return array<string>
     */
    public function getRequiredVariables(): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $this->user_template, $matches);

        return array_unique($matches[1] ?? []);
    }

    /**
     * Check if template has all required variables.
     *
     * @param array<string, mixed> $variables
     * @return bool
     */
    public function hasRequiredVariables(array $variables): bool
    {
        $required = $this->getRequiredVariables();

        foreach ($required as $key) {
            if (! array_key_exists($key, $variables)) {
                return false;
            }
        }

        return true;
    }
}
