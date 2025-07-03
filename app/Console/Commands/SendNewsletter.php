<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Email\SubscriberEmailService;

class SendNewsletter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'newsletter:send
                            {subject : The subject of the newsletter}
                            {--source= : Filter by subscriber source (blog, cgo, etc.)}
                            {--tags=* : Filter by subscriber tags}
                            {--dry-run : Show who would receive the email without sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send newsletter to subscribers';

    /**
     * Execute the console command.
     */
    public function handle(SubscriberEmailService $emailService)
    {
        $subject = $this->argument('subject');
        $source = $this->option('source');
        $tags = $this->option('tags');
        $dryRun = $this->option('dry-run');
        
        // Read content from stdin or prompt
        $content = $this->ask('Enter newsletter content (Markdown supported)', '');
        
        if (empty($content)) {
            $this->error('Newsletter content cannot be empty.');
            return 1;
        }
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No emails will be sent');
            $this->info('Newsletter Details:');
            $this->info("Subject: $subject");
            $this->info("Source filter: " . ($source ?: 'All sources'));
            $this->info("Tag filters: " . ($tags ? implode(', ', $tags) : 'No tag filters'));
            
            // Show preview of who would receive
            $query = \App\Models\Subscriber::active();
            
            if ($source) {
                $query->bySource($source);
            }
            
            if (!empty($tags)) {
                $query->where(function ($q) use ($tags) {
                    foreach ($tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                });
            }
            
            $count = $query->count();
            $this->info("Would send to: $count subscribers");
            
            if ($this->confirm('Show email addresses?')) {
                $query->pluck('email')->each(function ($email) {
                    $this->line("  - $email");
                });
            }
            
            return 0;
        }
        
        // Confirm before sending
        if (!$this->confirm("Send newsletter to subscribers? Subject: $subject")) {
            $this->info('Newsletter cancelled.');
            return 0;
        }
        
        $this->info('Sending newsletter...');
        
        try {
            $sentCount = $emailService->sendNewsletter($subject, $content, $tags, $source);
            
            $this->info("Newsletter sent successfully to $sentCount subscribers!");
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Failed to send newsletter: ' . $e->getMessage());
            return 1;
        }
    }
}
