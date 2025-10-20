<?php

namespace App\Http\Controllers\Category;

use Exception;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryStoreRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::all();

        return response()->json([
            'success' => true,
            'categories' => $categories
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CategoryStoreRequest $request): JsonResponse
    {
        try {
            $category = Category::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'category' => $category
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $category = Category::findOrFail($id);

            return response()->json([
                'success' => true,
                'category' => $category
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CategoryStoreRequest $request, string $id): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);
            $category->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully',
                'category' => $category
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $category = Category::findOrFail($id);
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully',
                'category' => $category
            ], 200);
        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 404 response.
     */
    protected function notFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Category not found'
        ], 404);
    }

    /**
     * Exception handler for 500 responses.
     */
    protected function handleException(Exception $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);
    }
}
