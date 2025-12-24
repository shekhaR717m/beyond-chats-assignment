# Beyond Chats Backend - Laravel Scraper

## Overview
Scrapes the 5 oldest articles from beyondchats.com/blogs and stores them in a MySQL database.
Exposes CRUD REST APIs for article management.

## Prerequisites
- PHP 8.2+
- Composer
- MySQL 5.7+
- Node.js 18+ (for running automation script)

## Setup Instructions

### 1. Install Dependencies
cd laravel-backend
composer install


### 2. Configure Environment
cp .env.example .env

Edit .env with your database credentials
php artisan key:generate


### 3. Create Database
mysql -u root -p
CREATE DATABASE beyond_chats;
exit;


### 4. Run Migrations
php artisan migrate


### 5. Scrape Articles
php artisan scrape:articles --limit=5


### 6. Start Local Server
php artisan serve

Server runs at http://localhost:8000

## API Endpoints

### List All Articles
GET http://localhost:8000/api/articles


### Get Single Article
GET http://localhost:8000/api/articles/{id}


### Get Latest Ungenerated Article
GET http://localhost:8000/api/articles/latest/ungenerated


### Create Article
POST http://localhost:8000/api/articles
Content-Type: application/json

{
"title": "Article Title",
"slug": "article-slug",
"original_url": "https://example.com/article",
"original_content": "Article content here...",
"status": "original"
}


### Update Article
PUT http://localhost:8000/api/articles/{id}
Content-Type: application/json

{
"generated_content": "AI-generated content...",
"status": "generated"
}


### Delete Article
DELETE http://localhost:8000/api/articles/{id}


## Database Schema

articles
├── id (PRIMARY KEY)
├── title (STRING)
├── slug (STRING, UNIQUE)
├── original_url (STRING)
├── original_content (TEXT)
├── generated_content (TEXT, NULLABLE)
├── generated_from_id (INT, FOREIGN KEY, NULLABLE)
├── status (ENUM: original|generated)
├── created_at (TIMESTAMP)
└── updated_at (TIMESTAMP)


## Trade-offs & Assumptions

- **No Authentication**: Intentionally omitted for time constraints
- **No Pagination**: All articles returned at once (5 articles is small)
- **Basic Scraping**: CSS selectors adjusted for BeyondChats blog structure
- **Synchronous Scraping**: Could be background job with Queue/Jobs
- **Error Handling**: Logs failures but continues processing
- **SSL Verification**: Disabled in development, enable in production

## Troubleshooting

### "SQLSTATE[HY000]: General error: 3065"
This is a MySQL 5.7 issue with generated columns. Ensure MySQL 8.0+ or adjust migrations.

### Articles not scraping?
Run with verbose logging:
php artisan scrape:articles -v

Check if CSS selectors match the actual BeyondChats blog DOM structure.
