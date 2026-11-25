<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a withdrawal's audit trail. Append-only. It never holds the
 * emailed code, the verification token or a provider payload.
 *
 * @property int $id
 * @property string $withdrawal_id
 * @property Carbon $at
 * @property string $actor user:{id}, job, webhook or operator:{email}
 * @property string $action started, call_started, call_finished, status_changed, code_sent, code_resent, code_wrong, resolved
 * @property int|null $partner_http_status
 * @property string|null $from_status
 * @property string|null $to_status
 * @property string|null $note
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class CardWithdrawalEvent extends Model
{
    use UsesTenantConnection;

    public $timestamps = false;

    protected $table = 'card_withdrawal_events';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'at'                  => 'datetime',
            'partner_http_status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CardWithdrawal, $this>
     */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(CardWithdrawal::class, 'withdrawal_id');
    }
}
