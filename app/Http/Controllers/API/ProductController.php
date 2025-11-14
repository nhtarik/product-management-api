<?php

namespace App\Http\Controllers\API;

use Exception;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\ImageUploadTrait;

class ProductController extends Controller
{

    use ImageUploadTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::with('categories');

        /**
         * ----------------------------
         * Search by Product Name 
         * ----------------------------
         */
        if ($request->filled('name')) {
            $query->where('name', 'LIKE', "%{$request->name}%");
        }

        /**
         * ----------------------------
         * Search by Category Name
         * ----------------------------
         * 
         *  Example: /products?category_name=electronics
         */
        if ($request->filled('category_name')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->category_name}%");
            });
        }

        /**
         * ----------------------------
         * Filter by Category id
         * ----------------------------
         */
        if ($request->filled('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('name', $request->category_id);
            });
        }

        /**
         * ----------------------------
         * Price Filters 
         * ----------------------------
         */
        if ($request->filled('price')) {
            // Exact price
            $query->where('price', $request->price);
        } else {
            // Price range
            if ($request->filled('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->filled('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }
        }

        /**
         * ----------------------------
         * Sorting
         * ----------------------------
         */
        if ($request->filled('sort')) {
            $query->orderBy(
                $request->sort,
                $request->get('direction', 'desc')
            );
        } else {
            $query->latest();
        }

        /**
         * -----------------------------------------
         * Pagination
         * -----------------------------------------
         */
        $perPage = $request->get('per_page', 10);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();

        try {
            $product = DB::transaction(function () use ($validated) {

                // Handle image upload
                $validated['image'] = $this->uploadFile($validated['image'] ?? null, 'products');

                $product = Product::create($validated);

                // Attach categories (can include subcategories)
                if (!empty($validated['categories'])) {
                    $product->categories()->attach($validated['categories']);
                }

                return $product->load('categories.children');
            });

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully.',
                'product' => new ProductResource($product),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $product->load('categories.children');

        return response()->json([
            'success' => true,
            'product' => new ProductResource($product),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $validated = $request->validated();

        try {
            $updated = DB::transaction(function () use ($validated, $product) {

                // Handle image upload
                $validated['image'] = $this->uploadFile($validated['image'] ?? null, 'products', $product->image);

                // Update the product
                $product->update($validated);

                // Sync categories (attach new, remove old)
                if (isset($validated['categories'])) {
                    $product->categories()->sync($validated['categories']);
                }

                // Load categories and their children
                return $product->load('categories.children');
            });

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully.',
                'product' => new ProductResource($updated),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        try {

            $product->load('categories');
            $deletedProduct = new ProductResource($product);

            // Delete image if exists
            if ($product->image) {
                $this->unlinkFile($product->image);
            }

            // Delete product (pivot entries removed automatically)
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully.',
                'product' => $deletedProduct,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
