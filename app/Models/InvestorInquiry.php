<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Public investor lead-capture submission from /invest.
 *
 * Append-only: the table has `created_at` only (no `updated_at`).
 * IP addresses are never stored raw; `ip_hash` is sha256(ip + app.key).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $email
 * @property string      $linkedin_url
 * @property string      $investing_as
 * @property string      $path_of_interest
 * @property string      $check_size_range
 * @property string|null $questions
 * @property string      $ip_hash
 * @property string      $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 */
class InvestorInquiry extends Model
{
    /**
     * Disable Eloquent's `updated_at` — the table has only `created_at`.
     */
    public const UPDATED_AT = null;

    /**
     * @var string
     */
    protected $table = 'investor_inquiries';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'linkedin_url',
        'investing_as',
        'path_of_interest',
        'check_size_range',
        'questions',
        'ip_hash',
        'user_agent',
    ];
}
