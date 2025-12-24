<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ScrapeArticlesCommand extends Command
{
    protected $signature = 'articles:scrape';
    protected $description = 'Scrape articles and store them in the database';

    public function handle()
    {
        $this->info('Scraping articles...');
        // TODO: call ArticleScraper service
        return 0;
    }
}
