<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController
{
    public function index(Request $request)
    {
        return Article::all();
    }
}
