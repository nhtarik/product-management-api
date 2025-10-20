<?php

namespace App\Http\Controllers\Product;

use Exception;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductStoreRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::all();

        return response()->json([
            'success' => true,
            'products' => $products
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductStoreRequest $request): JsonResponse
    {
        try {
            $product = Product::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'product' => $product
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
            $product = Product::findOrFail($id);

            return response()->json([
                'success' => true,
                'product' => $product
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
    public function update(ProductStoreRequest $request, string $id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);
            $product->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'product' => $product
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
            $product = Product::findOrFail($id);
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
                'product' => $product
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
            'message' => 'Product not found'
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
