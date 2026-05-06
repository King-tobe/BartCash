<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /categories
    // -------------------------------------------------------------------------
    public function index(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'icon']);

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved.',
            'data'    => [
                'categories' => $categories,
            ],
        ]);
    }
}