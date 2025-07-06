<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\SitemapController;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate {--output= : Output file path (default: public/sitemap.xml)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the sitemap.xml file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating sitemap...');
        
        $controller = new SitemapController();
        $response = $controller->index();
        
        $outputPath = $this->option('output') ?: public_path('sitemap.xml');
        
        // Save the sitemap to file
        if (file_put_contents($outputPath, $response->getContent())) {
            $this->info('Sitemap generated successfully at: ' . $outputPath);
            
            // Also generate robots.txt
            $robotsResponse = $controller->robots();
            if (file_put_contents(public_path('robots.txt'), $robotsResponse->getContent())) {
                $this->info('robots.txt generated successfully');
            }
            
            return Command::SUCCESS;
        }
        
        $this->error('Failed to generate sitemap');
        return Command::FAILURE;
    }
}