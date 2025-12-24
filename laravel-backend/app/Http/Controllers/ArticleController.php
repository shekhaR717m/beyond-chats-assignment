<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    /**
     * GET /api/articles
     * List all articles with optional filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by whether generated or not
        if ($request->query('generated') === 'true') {
            $query->where('status', 'generated');
        } elseif ($request->query('generated') === 'false') {
            $query->where('status', 'original');
        }

        $articles = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $articles,
            'count' => $articles->count(),
        ]);
    }

    /**
     * GET /api/articles/{id}
     * Get single article by ID
     */
    public function show($id): JsonResponse
    {
        $article = Article::find($id);

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $article,
        ]);
    }

    /**
     * POST /api/articles
     * Create a new article
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:articles,slug',
            'original_url' => 'required|url',
            'original_content' => 'required|string',
            'generated_content' => 'nullable|string',
            'status' => 'required|in:original,generated',
            'generated_from_id' => 'nullable|integer|exists:articles,id',
        ]);

        try {
            $article = Article::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Article created successfully.',
                'data' => $article,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create article: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/articles/{id}
     * Update an article
     */
    public function update(Request $request, $id): JsonResponse
    {
        $article = Article::find($id);

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'original_content' => 'sometimes|string',
            'generated_content' => 'sometimes|nullable|string',
            'status' => 'sometimes|in:original,generated',
            'generated_from_id' => 'sometimes|nullable|integer|exists:articles,id',
        ]);

        try {
            $article->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Article updated successfully.',
                'data' => $article,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update article: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/articles/{id}
     * Delete an article
     */
    public function destroy($id): JsonResponse
    {
        $article = Article::find($id);

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found.',
            ], 404);
        }

        try {
            $article->delete();

            return response()->json([
                'success' => true,
                'message' => 'Article deleted successfully.',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete article: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/articles/latest/ungenerated
     * Get the latest article that hasn't been processed by AI
     */
    public function getLatestUngenerated(): JsonResponse
    {
        $article = Article::where('status', 'original')
            ->whereDoesntHave('generatedVersions')
            ->orderBy('created_at', 'asc')
            ->first();

        if (!$article) {
            return response()->json([
                'success' => false,
                'message' => 'No ungenerated articles found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $article,
        ]);
    }
} 
