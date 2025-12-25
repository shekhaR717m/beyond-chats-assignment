# Beyond Chats — Assignment

Welcome! This repository contains a small project that scrapes articles from BeyondChats (https://beyondchats.com/blogs/), stores them in a Laravel backend, and includes a Node-based automation that fetches the latest unprocessed article and creates an AI-generated version. A minimal React frontend is included to display articles.

This README explains how to set up and run the entire stack locally, what I changed, and important safety notes about secrets and files you should not push to GitHub.

---

## What this project does

- Scrapes the 5 oldest articles from the last page of the BeyondChats blog and stores them in a Laravel database.
- Exposes a set of CRUD APIs to manage articles.
- Provides a Node automation pipeline that fetches the latest unprocessed article, optionally scrapes reference materials, rewrites it (AI stub), and publishes the rewritten article back to the Laravel backend.
- Includes a small React frontend to view and filter articles.

---

## Quick start (local development)

Prerequisites:
- PHP 8.2+ and Composer
- Node.js 18+
- Git

1) Clone the repository

   git clone https://github.com/shekhaR717m/beyond-chats-assignment.git
   cd beyond-chats-assignment

2) Laravel backend setup

   cd laravel-backend
   composer install

   Copy environment file and create the SQLite DB (default):

   cp .env.example .env
   mkdir -p database && touch database/database.sqlite

   Update `.env` if you want to use a different DB (MySQL, Postgres).

   Migrate the database:

   php artisan migrate

   Start the dev server:

   php artisan serve --host=127.0.0.1 --port=8000

3) Scraper

   Use the artisan command to scrape and save the 5 oldest articles:

   php artisan scrape:articles --limit=5

   Or call the API endpoint:

   POST http://127.0.0.1:8000/api/articles/scrape (body: { limit: 5 })

4) Node automation

   cd ../node-automation
   npm install

   The script expects `LARAVEL_API_URL` (default: `http://127.0.0.1:8000/api`). Start it like:

   $env:LARAVEL_API_URL='http://127.0.0.1:8000/api'; npm start   # PowerShell

   The automation will fetch the latest ungenerated article and publish the generated article back to Laravel.

5) React frontend

   cd ../react-frontend
   npm install
   Set `REACT_APP_API_URL` in `.env` if the API runs on a different host:port
   npm start

   The front-end loads at http://localhost:3000

---

## API endpoints overview

- GET `/api/articles` — list all articles
- GET `/api/articles/{id}` — single article
- POST `/api/articles` — create article
- PUT `/api/articles/{id}` — update article
- DELETE `/api/articles/{id}` — delete article
- POST `/api/articles/scrape` — trigger scraping and store the oldest N articles (body or query param `limit`)
- GET `/api/articles/latest/ungenerated` — get the earliest original article that hasn't been generated

---

## Safety & secrets

- **Do not commit `.env` files or secret files.** The root `.gitignore` already includes `.env`.
- You asked specifically that `.env` and `api` must not be pushed — this repository respects that. I added a rule to ignore a top-level `/api` file (if you keep an API key in a file named `api` please keep it locally).

---

## Notes about what I implemented

- Robust scraper (looks for pagination, scrapes the last page, extracts published date when present, cleans content).
- SSL fallback (development-only): if the HTTP client fails due to missing CA roots, it retries with verification disabled — **do not do this in production**.
- Node automation improved logging and detection of the local Laravel server; it also includes retries when the server is not immediately ready.
- `process:latest` artisan command was added to simulate creating a generated (AI) article without running the Node pipeline — useful for testing.

---

If you'd like, I can push this repository to the GitHub URL you provided and ensure `.env` and sensitive API files are not pushed. Would you like me to proceed with the push now?

Thanks — and if you want the README changed or shortened, tell me how you'd like it phrased.