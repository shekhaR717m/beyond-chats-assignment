<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;

class ArticleScraper
{
    private Client $client;
    private int $timeout;
    private int $maxRetries;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => config('app.scraper_timeout', 30),
        ]);
        $this->timeout = config('app.scraper_timeout', 30);
        $this->maxRetries = config('app.scraper_max_retries', 3);
    }

    /**
     * Scrape the 5 oldest articles from BeyondChats blog
     * Returns array of articles with title, url, and content
     */
    public function scrapeOldestArticles(int $limit = 5): array
    {
        $baseUrl = 'https://beyondchats.com/blogs/';
        $articles = [];

        try {
            // Fetch the blog listing page
            $response = $this->fetchWithRetry($baseUrl);
            $html = $response->getBody()->getContents();

            $crawler = new Crawler($html);

            // CSS selector for article cards on Beyond Chats blog
            // Adjust based on actual DOM structure
            $articleElements = $crawler->filter('article, .blog-post, .post-card, [data-article]');

            if ($articleElements->count() === 0) {
                // Fallback: try more generic selectors
                $articleElements = $crawler->filter('a[href*="/blogs/"]')->reduce(function (Crawler $node) {
                    $href = $node->attr('href');
                    return $href && !str_contains($href, 'category') && !str_contains($href, 'feed');
                });
            }

            // Extract article links
            $articleLinks = [];
            $articleElements->each(function (Crawler $node) use (&$articleLinks, $baseUrl) {
                $link = $node->attr('href') ?? $node->filter('a')->attr('href');
                if ($link && str_starts_with($link, 'http')) {
                    $articleLinks[] = $link;
                } elseif ($link) {
                    $articleLinks[] = rtrim($baseUrl, '/') . '/' . ltrim($link, '/');
                }
            });

            // Remove duplicates and limit
            $articleLinks = array_unique($articleLinks);
            $articleLinks = array_slice($articleLinks, 0, $limit);

            // Scrape each article
            foreach ($articleLinks as $url) {
                try {
                    $article = $this->scrapeArticleContent($url);
                    if ($article && $article['title'] && $article['content']) {
                        $articles[] = $article;
                    }
                } catch (\Exception $e) {
                    \Log::warning("Failed to scrape article: {$url}", ['error' => $e->getMessage()]);
                    continue;
                }
            }

            return array_slice($articles, 0, $limit);

        } catch (\Exception $e) {
            \Log::error('ArticleScraper error', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Scrape individual article content
     */
    private function scrapeArticleContent(string $url): ?array
    {
        try {
            $response = $this->fetchWithRetry($url);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);

            // Extract title
            $title = $crawler->filter('h1, .article-title, meta[property="og:title"]')->first();
            if ($title->nodeName() === 'meta') {
                $titleText = $title->attr('content');
            } else {
                $titleText = trim($title->text());
            }

            // Extract content
            $content = '';
            $contentSelectors = [
                'article',
                '[role="main"]',
                '.post-content',
                '.article-content',
                '.entry-content',
            ];

            foreach ($contentSelectors as $selector) {
                $contentNode = $crawler->filter($selector)->first();
                if ($contentNode->count() > 0) {
                    $content = $this->extractTextContent($contentNode);
                    break;
                }
            }

            if (!$content) {
                $content = $this->extractTextContent($crawler->filter('body')->first());
            }

            // Clean content
            $content = $this->cleanContent($content);

            return [
                'title' => trim($titleText),
                'url' => $url,
                'content' => $content,
                'slug' => Str::slug($titleText),
            ];

        } catch (\Exception $e) {
            \Log::warning("Article scrape failed: {$url}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extract plain text from HTML node
     */
    private function extractTextContent(Crawler $node): string
    {
        // Remove script and style tags
        $node->filter('script, style, noscript')->each(function (Crawler $n) {
            // Mark for removal by replacing with placeholder
            $n->getNode(0)?->parentNode?->removeChild($n->getNode(0));
        });

        return trim(preg_replace('/\s+/', ' ', $node->text()));
    }

    /**
     * Clean and normalize article content
     */
    private function cleanContent(string $content): string
    {
        // Remove multiple spaces
        $content = preg_replace('/\s+/', ' ', $content);
        // Remove special characters but keep punctuation
        $content = preg_replace('/[^\p{L}\p{N}\s\.\,\!\?\-]/u', '', $content);
        return trim($content);
    }

    /**
     * Fetch URL with retry logic
     */
    private function fetchWithRetry(string $url, int $attempt = 1)
    {
        try {
            return $this->client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
                'allow_redirects' => true,
                'timeout' => $this->timeout,
            ]);
        } catch (RequestException $e) {
            if ($attempt < $this->maxRetries) {
                sleep(2 ** $attempt); // Exponential backoff
                return $this->fetchWithRetry($url, $attempt + 1);
            }
            throw $e;
        }
    }
} 
