<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\ArticleScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ScrapeArticlesCommand extends Command
{
    protected $signature = 'scrape:articles {--limit=5 : Number of articles to scrape}';
    protected $description = 'Scrape the oldest articles from BeyondChats blog';

    public function handle()
    {
        $this->info('Starting article scraping...');

        try {
            $scraper = new ArticleScraper();
            $limit = (int) $this->option('limit');

            $articles = $scraper->scrapeOldestArticles($limit);

            if (empty($articles)) {
                $this->error('No articles found to scrape.');
                return Command::FAILURE;
            }

            $saved = 0;
            foreach ($articles as $articleData) {
                // Check if article already exists
                $existing = Article::where('slug', $articleData['slug'])->first();

                if ($existing) {
                    $this->warn("Article already exists: {$articleData['title']}");
                    continue;
                }

                try {
                    Article::create([
                        'title' => $articleData['title'],
                        'slug' => $articleData['slug'],
                        'original_url' => $articleData['url'],
                        'original_content' => $articleData['content'],
                        'status' => 'original',
                    ]);

                    $this->line("✓ Saved: {$articleData['title']}");
                    $saved++;
                } catch (\Exception $e) {
                    $this->error("Failed to save: {$articleData['title']} - {$e->getMessage()}");
                }
            }

            $this->info("Successfully saved {$saved} articles.");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Scraping failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 
