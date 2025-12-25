import axios from 'axios';
import { logger } from '../utils/logger.js';

const API_URL = process.env.LARAVEL_API_URL || 'http://localhost:8000/api';

const client = axios.create({
  baseURL: API_URL,
  timeout: parseInt(process.env.TIMEOUT_MS) || 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

/** Fetch the latest article that hasn't been generated yet */
export async function fetchLatestArticle(retries = 5) {
  let attempt = 0;

  while (attempt < retries) {
    try {
      const response = await client.get('/articles/latest/ungenerated');

      if (response.data?.success) {
        return response.data.data;
      }

      logger.warn('Laravel returned success:false');
      return null;

    } catch (error) {
      attempt++;

      // Detailed logging for connectivity and server issues
      const errInfo = {
        message: error.message,
        code: error.code || null,
        status: error.response?.status || null,
        responseData: error.response?.data || null,
        request: error.request ? { method: error.request.method, path: error.request.path || error.request._parsedUrl?.path } : null,
      };

      if (errInfo.status === 404) {
        logger.warn('No ungenerated articles available');
        return null;
      }

      logger.error(`Failed to fetch article (attempt ${attempt}/${retries})`, errInfo);

      // If connection refused (server not up yet), wait and retry
      if (errInfo.code === 'ECONNREFUSED' && attempt < retries) {
        const waitMs = 1000 * attempt;
        logger.info(`Connection refused, retrying in ${waitMs}ms...`);
        await new Promise((res) => setTimeout(res, waitMs));
        continue;
      }

      throw new Error(`Failed to fetch article: ${error.message}`);
    }
  }

  throw new Error('Failed to fetch article: max retries exceeded');
}

export async function fetchAllArticles() {
  try {
    const response = await client.get('/articles');
    return response.data.data || [];
  } catch (error) {
    throw new Error(`Failed to fetch articles: ${error.message}`);
  }
}

export async function publishGeneratedArticle(articleData) {
  try {
    const response = await client.post('/articles', articleData);

    if (response.data?.success) {
      return response.data.data;
    }

    throw new Error('Laravel returned success:false');

  } catch (error) {
    throw new Error(`Failed to publish article: ${error.message}`);
  }
}

export async function updateArticle(id, updates) {
  try {
    const response = await client.put(`/articles/${id}`, updates);

    if (response.data?.success) {
      return response.data.data;
    }

    throw new Error('Laravel returned success:false');

  } catch (error) {
    throw new Error(`Failed to update article: ${error.message}`);
  }
}

export default { fetchLatestArticle, fetchAllArticles, publishGeneratedArticle, updateArticle };
