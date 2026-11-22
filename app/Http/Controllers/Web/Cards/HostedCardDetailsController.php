<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Cards;

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardLifecycle;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * The card-details page the app opens in a WebView (GET issued/{card}/sensitive).
 * Interlace Widget.js shows the card number, expiry and security code in its
 * own frames, with a token Lepo issues when the page opens; our servers never
 * see those values. The signed link is valid for 5 minutes and opens once.
 */
final class HostedCardDetailsController extends Controller
{
    public function __construct(private readonly LepoCardLifecycle $cards)
    {
    }

    public function show(Request $request, string $card): Response
    {
        if (! $request->hasValidRelativeSignature()) {
            return $this->page('expired', 403);
        }

        $owned = Card::query()->whereKey($card)->where('issuer', 'lepo')->first();
        abort_if($owned === null || ! $owned->isOpenAtProvider(), 404);

        // One open per link: a reload or a forwarded link shows nothing.
        $nonce = (string) $request->query('n', '');
        if ($nonce === '' || ! Cache::add("cards:details:nonce:{$nonce}", true, now()->addMinutes(6))) {
            return $this->page('used', 403);
        }

        try {
            $token = $this->cards->widgetToken($owned)['token'];
        } catch (CardProviderException) {
            return $this->page('unavailable', 502);
        }

        return $this->page('ready', 200, [
            'token'     => $token,
            'scriptUrl' => (string) config('cardissuance.issuers.lepo.widget_script_url'),
        ]);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function page(string $state, int $status = 200, array $data = []): Response
    {
        return response()
            ->view('cards.card-details', ['state' => $state, 'brand' => (string) config('brand.name', 'Zelta'), ...$data], $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
