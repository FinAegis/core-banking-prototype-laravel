<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\InvestorInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notify the founder when a new investor inquiry is submitted at /invest.
 *
 * Queued so submission latency stays low and a transient SMTP outage does
 * not surface a 500 to a prospective investor. The InvestorController must
 * already have persisted the row before queuing this; if Mail later fails,
 * the row remains for manual triage.
 */
class InvestorInquirySubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public InvestorInquiry $inquiry,
    ) {
    }

    /**
     * @param mixed $notifiable
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $i = $this->inquiry;

        $pathLabels = [
            'licensed'      => 'Licensed (MiCA + EMI)',
            'non_custodial' => 'Non-custodial SaaS',
            'both'          => 'Both / undecided',
        ];
        $investingAsLabels = [
            'angel'         => 'Angel',
            'vc'            => 'VC',
            'family_office' => 'Family office',
            'other'         => 'Other',
        ];
        $checkSizeLabels = [
            'under_25k' => 'Under €25k',
            '25k_100k'  => '€25k–€100k',
            '100k_500k' => '€100k–€500k',
            '500k_plus' => '€500k+',
        ];

        return (new MailMessage())
            ->subject('New investor inquiry — ' . $i->name . ' (' . ($pathLabels[$i->path_of_interest] ?? $i->path_of_interest) . ')')
            ->greeting('New investor inquiry')
            ->line('Name: ' . $i->name)
            ->line('Email: ' . $i->email)
            ->line('LinkedIn: ' . $i->linkedin_url)
            ->line('Investing as: ' . ($investingAsLabels[$i->investing_as] ?? $i->investing_as))
            ->line('Path of interest: ' . ($pathLabels[$i->path_of_interest] ?? $i->path_of_interest))
            ->line('Check size: ' . ($checkSizeLabels[$i->check_size_range] ?? $i->check_size_range))
            ->line('Questions:')
            ->line($i->questions !== null && $i->questions !== '' ? $i->questions : '(none)')
            ->action('Open LinkedIn profile', $i->linkedin_url)
            ->line('Submitted at ' . $i->created_at->toDateTimeString() . ' (UTC).')
            ->line('IP hash: ' . $i->ip_hash)
            ->line('User agent: ' . $i->user_agent);
    }
}
