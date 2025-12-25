<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProcessLatestArticleCommand extends Command
{
    protected $signature = 'process:latest';
    protected $description = 'Simulate processing the latest ungenerated article and create a generated version';

    public function handle()
    {
        $article = Article::where('status', 'original')
            ->whereDoesntHave('generatedVersions')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$article) {
            $this->error('No ungenerated articles found.');
            return Command::FAILURE;
        }

        $generated = Article::create([
            'title' => $article->title . ' (AI Rewritten)',
            'slug' => Str::slug($article->title . ' ai rewritten') . '-' . time(),
            'original_url' => $article->original_url,
            'original_content' => $article->original_content,
            'generated_content' => 'This is a simulated AI rewrite of the article.',
            'generated_from_id' => $article->id,
            'status' => 'generated',
        ]);

        $this->info('Generated article created: ' . $generated->id);
        return Command::SUCCESS;
    }
}
