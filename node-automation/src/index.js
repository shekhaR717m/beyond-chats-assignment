import dotenv from 'dotenv';
import { fetchLatestArticle, publishGeneratedArticle } from './services/laravelService.js';
import { searchGoogle, scrapeArticles } from './services/scraperService.js';
import { rewriteArticleWithAI } from './services/aiService.js';
import { logger } from './utils/logger.js';

dotenv.config();

import { spawn } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';
import axios from 'axios';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

async function waitForServer(url, timeout = 15000) {
  const start = Date.now();
  while (Date.now() - start < timeout) {
    try {
      await axios.get(url, { timeout: 2000 });
      return true;
    } catch (err) {
      await new Promise((res) => setTimeout(res, 500));
    }
  }
  return false;
}

async function ensureLaravelServer() {
  const apiBase = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8000/api';
  try {
    await axios.get(apiBase.replace(/\/api\/?$/, '/health'), { timeout: 2000 });
    logger.info('Laravel API is reachable.');
    return;
  } catch (err) {
    logger.warn('Laravel API not reachable, starting local server...');
  }

  const artisanPath = path.resolve(__dirname, '..', 'laravel-backend', 'artisan');
  const cwd = path.dirname(artisanPath);

  // Try 'php' then 'php.exe' for Windows compatibility
  let proc;
  const phpPath = process.env.PHP_PATH || 'C:\\php\\php.exe';
  try {
    proc = spawn(phpPath, [artisanPath, 'serve', '--host=127.0.0.1', '--port=8000'], {
      cwd,
      detached: true,
      stdio: ['ignore', 'ignore', 'ignore'],
    });

    proc.on('error', (err) => {
      logger.warn('Failed to spawn php process to start server', { error: err.message });
    });

    proc.unref();

  } catch (e) {
    logger.warn('Could not spawn php process to start server; will poll for server health instead', { error: e.message });
  }

  // Wait for the server to become healthy whether we spawned it or not
  const healthy = await waitForServer((process.env.LARAVEL_API_URL || 'http://127.0.0.1:8000/api').replace(/\/api\/?$/, '/health'), 20000);
  if (!healthy) {
    throw new Error('Failed to start Laravel server within timeout.');
  }
  logger.info('Laravel server started and healthy.');
}

async function main() {
  try {
    logger.info(' Starting Beyond Chats Automation Script');

    // Ensure Laravel server is up
    await ensureLaravelServer();

    // STEP 1: Fetch latest ungenerated article
    logger.info(' Fetching latest ungenerated article from Laravel...');
    const article = await fetchLatestArticle();

    if (!article) {
      logger.warn('No ungenerated articles found. Exiting.');
      return;
    }

    logger.info(`Found article: "${article.title}" (ID: ${article.id})`);

    // STEP 2: Search for related articles on Google
    logger.info(' Searching for reference articles on Google...');
    let referenceUrls = [];
    try {
      referenceUrls = await searchGoogle(article.title);
      logger.info(` Found ${referenceUrls.length} reference URLs`);
    } catch (err) {
      logger.warn(` Google search failed: ${err.message}. Continuing without references.`);
    }

    // STEP 3: Scrape reference articles
    let referenceContent = [];
    if (referenceUrls.length > 0) {
      logger.info(' Scraping reference articles...');
      try {
        referenceContent = await scrapeArticles(referenceUrls.slice(0, 2));
        logger.info(`✓ Successfully scraped ${referenceContent.length} references`);
      } catch (err) {
        logger.warn(` Reference scraping failed: ${err.message}`);
      }
    }

    // STEP 4: Rewrite article with AI
    logger.info(' Rewriting article with AI...');
    const generatedContent = await rewriteArticleWithAI(
      article.original_content,
      article.title,
      referenceContent
    );

    logger.info(' Article rewritten successfully');

    // STEP 5: Publish generated article back to Laravel
    logger.info(' Publishing generated article to Laravel...');
    const generatedArticle = await publishGeneratedArticle({
      title: `${article.title} (AI Rewritten)`,
      slug: `${article.slug}-ai-generated`,
      original_url: article.original_url,
      original_content: article.original_content,
      generated_content: generatedContent,
      generated_from_id: article.id,
      status: 'generated',
    });

    logger.info(` Published generated article (ID: ${generatedArticle.id})`);
    logger.info(' Automation pipeline completed successfully!');
  } catch (error) {
    logger.error(` Pipeline failed: ${error.message}`);
    process.exit(1);
  }
}

// Run the script
main();
