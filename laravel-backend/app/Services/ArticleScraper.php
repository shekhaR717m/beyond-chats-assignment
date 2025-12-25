<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Str;
use Carbon\Carbon;

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
     * Scrape the N oldest articles from BeyondChats blog (default 5)
     * Strategy:
     *  - Discover the "last" page of the blog listing (rel="last" or highest page number)
     *  - Scrape article links on that last page
     *  - Fetch each article and try to extract a published date
     *  - Sort by published date (ascending) when available and return the oldest N
     */
    public function scrapeOldestArticles(int $limit = 5): array
    {
        $baseUrl = 'https://beyondchats.com/blogs/';
        $articles = [];

        try {
            // Fetch the blog listing page (to discover pagination)
            $response = $this->fetchWithRetry($baseUrl);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);

            // Attempt to find explicit rel="last" link
            $lastPageUrl = null;
            try {
                $lastRel = $crawler->filter('a[rel="last"]')->first();
                if ($lastRel->count() > 0 && $lastRel->attr('href')) {
                    $lastPageUrl = $this->absoluteUrl($lastRel->attr('href'), $baseUrl);
                }
            } catch (\Exception $e) {
                // ignore
            }

            // If rel="last" not found, look for pagination links and pick the highest page
            if (!$lastPageUrl) {
                $paginationLinks = $crawler->filter('a[href*="/blogs?page="]');
                $maxPage = 0;
                foreach ($paginationLinks as $linkNode) {
                    $href = $linkNode->getAttribute('href');
                    if (!$href) continue;
                    $parts = parse_url($href);
                    if (!empty($parts['query'])) {
                        parse_str($parts['query'], $qs);
                        if (!empty($qs['page']) && is_numeric($qs['page'])) {
                            $page = (int) $qs['page'];
                            if ($page > $maxPage) {
                                $maxPage = $page;
                                $lastPageUrl = $this->absoluteUrl($href, $baseUrl);
                            }
                        }
                    }
                }
            }

            // Fallback to base URL if no pagination found
            if (!$lastPageUrl) {
                $lastPageUrl = $baseUrl;
            }

            // Fetch the last page and extract article links
            $pageResponse = $this->fetchWithRetry($lastPageUrl);
            $pageHtml = $pageResponse->getBody()->getContents();
            $pageCrawler = new Crawler($pageHtml);

            $articleLinkNodes = $pageCrawler->filter('article a[href*="/blogs/"], .post-card a[href*="/blogs/"], a[href*="/blogs/"]');
            $articleLinks = [];

            $articleLinkNodes->each(function (Crawler $node) use (&$articleLinks, $lastPageUrl) {
                $href = $node->attr('href');
                if (!$href) return;
                $articleLinks[] = $this->absoluteUrl($href, $lastPageUrl);
            });

            $articleLinks = array_values(array_unique($articleLinks));

            // Scrape each article to get content and published date when available
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

            // If we have published dates, sort ascending to get the oldest first
            usort($articles, function ($a, $b) {
                $ad = $a['published_at'] ?? null;
                $bd = $b['published_at'] ?? null;

                if ($ad && $bd) {
                    return strcmp($ad, $bd);
                }
                if ($ad) return -1;
                if ($bd) return 1;
                return 0;
            });

            // Return oldest N articles
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

            // Try to extract published date when available
            $publishedAt = null;
            try {
                $dateNode = $crawler->filter('meta[property="article:published_time"], meta[name="date"], time[datetime], .post-date, .published')->first();
                if ($dateNode->count() > 0) {
                    if ($dateNode->nodeName() === 'meta') {
                        $dateText = $dateNode->attr('content');
                    } else {
                        $dateText = $dateNode->attr('datetime') ?? trim($dateNode->text());
                    }

                    if (!empty($dateText)) {
                        try {
                            $publishedAt = Carbon::parse($dateText)->toDateTimeString();
                        } catch (\Exception $e) {
                            $publishedAt = null;
                        }
                    }
                }
            } catch (\Exception $e) {
                // ignore
            }

            return [
                'title' => trim($titleText),
                'url' => $url,
                'content' => $content,
                'slug' => Str::slug($titleText),
                'published_at' => $publishedAt,
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
     * Convert possibly relative href into absolute URL using base
     */
    private function absoluteUrl(string $href, string $base): string
    {
        // If already absolute
        if (str_starts_with($href, 'http')) {
            return $href;
        }

        // If protocol-relative
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        // Otherwise, build from base
        return rtrim($base, '/') . '/' . ltrim($href, '/');
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
            // Handle SSL certificate issues specially by attempting a non-verified request once
            $msg = $e->getMessage();
            if (str_contains($msg, 'SSL certificate') || str_contains($msg, 'cURL error 60')) {
                try {
                    \Log::warning('SSL certificate verification failed, retrying with verify=false', ['url' => $url, 'error' => $msg]);
                    return $this->client->request('GET', $url, [
                        'headers' => [
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        ],
                        'allow_redirects' => true,
                        'timeout' => $this->timeout,
                        'verify' => false,
                    ]);
                } catch (RequestException $e2) {
                    // fall through to normal retry behavior
                    \Log::warning('Retry with verify=false failed', ['url' => $url, 'error' => $e2->getMessage()]);
                }
            }

            if ($attempt < $this->maxRetries) {
                sleep(2 ** $attempt); // Exponential backoff
                return $this->fetchWithRetry($url, $attempt + 1);
            }
            throw $e;
        }
    }
} 
