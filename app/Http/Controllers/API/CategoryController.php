<?php

namespace App\Http\Controllers\API;

use Exception;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\ImageUploadTrait;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ImageUploadTrait;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Category::with('parent');

        // Search by Name
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        // Filter by parent_id
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Sorting (optional: by created_at desc)
        $query->latest();

        // Paginate (default 10 per page, or dynamic ?per_page=20)
        $perPage = $request->get('per_page', 10);
        $categories = $query->paginate($perPage);

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $createdCategories = DB::transaction(function () use ($validated) {

                $parentCatName = $validated['name'] ?? null;
                $parentId      = $validated['parent_id'] ?? null;
                $subCategories = $validated['subcategories'] ?? [];

                $created = collect();

                // Helper closure to create a category
                $createCategory = fn(string $name, ?int $parent = null) => Category::create([
                    'name'      => $name,
                    'parent_id' => $parent,
                ]);

                // Case 1 & 2: Create parent category if name is provided
                $parentCategory = null;
                if (!empty($parentCatName)) {

                    // Handle image upload
                    $validated['image'] = $this->uploadFile($validated['image'] ?? null, 'assets/images/categories');

                    $parentCategory = $createCategory($parentCatName, $parentId);
                    $created->push($parentCategory);
                }

                // Case 1 & 3: Create subcategories if provided
                if (!empty($subCategories)) {
                    $parentForSub = $parentCategory->id ?? $parentId;
                    if (!$parentForSub) {
                        throw new Exception('Cannot create subcategories without a parent ID.');
                    }

                    foreach ($subCategories as $subcategory) {
                        $childCategory = $createCategory($subcategory['name'] ?? $subcategory, $parentForSub);
                        $created->push($childCategory);
                    }
                }

                // If nothing created → invalid input
                if ($created->isEmpty()) {
                    throw new Exception('Invalid input. Provide a category name or subcategories.');
                }

                return $created;
            });

            // Load children for each created category
            $createdCategories->each->load('children');

            return response()->json([
                'success'    => true,
                'message'    => 'Categories created successfully.',
                'categories' => CategoryResource::collection($createdCategories),
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong when creating categories.',
                'error'   => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): JsonResponse
    {
        $category->load(['parent', 'children']);

        return response()->json([
            'success' => true,
            'category' => new CategoryResource($category),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $validated = $request->validated();

        try {
            $updatedCategories = DB::transaction(function () use ($validated, $category) {

                $updated = collect([$category]);

                $newParentId = array_key_exists('parent_id', $validated)
                    ? $validated['parent_id']
                    : $category->parent_id;

                $newName        = $validated['name'] ?? $category->name;
                $subCategories  = $validated['subcategories'] ?? [];


                // Prevent cyclic or invalid parent assignment
                if ($newParentId === $category->id) {
                    throw new \InvalidArgumentException('A category cannot be its own parent.');
                }

                if ($newParentId && $this->isDescendantOf($category->id, $newParentId)) {
                    throw new \InvalidArgumentException('Cannot assign a descendant category as parent.');
                }

                // Handle image upload
                $validated['image'] = $this->uploadFile($validated['image'] ?? null, 'assets/images/categories', $category->image);

                // Update main category
                $category->update([
                    'name'      => $newName,
                    'parent_id' => $newParentId,
                ]);

                // Handle subcategories
                foreach ($subCategories as $sub) {
                    $subData = is_string($sub) ? ['name' => $sub] : $sub;

                    if (!empty($subData['id'])) {
                        // Update existing subcategory
                        $existingSub = Category::where('parent_id', $category->id)
                            ->where('id', $subData['id'])
                            ->first();

                        if ($existingSub) {
                            $existingSub->update([
                                'name' => $subData['name'] ?? $existingSub->name
                            ]);
                            $updated->push($existingSub);
                        }
                    } else {
                        // Create new subcategory
                        $newSub = Category::create([
                            'name'      => $subData['name'],
                            'parent_id' => $category->id,
                        ]);
                        $updated->push($newSub);
                    }
                }

                // Load relationships for response
                $updated->each->load(['parent', 'children']);

                return $updated;
            });

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully.',
                'categories' => CategoryResource::collection($updatedCategories),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category.',
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    /**
     * Check if $targetId is a descendant of $categoryId.
     */
    private function isDescendantOf(int $categoryId, int $targetId): bool
    {
        $target = Category::find($targetId);
        if (!$target) return false;

        while ($target->parent_id) {
            if ($target->parent_id === $categoryId) return true;
            $target = $target->parent;
        }

        return false;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): JsonResponse
    {
        try {
            // Optionally load children if want to return deleted hierarchy
            $category->load('children');

            // Wrap in resource before deletion if you want the API to return it
            $deletedCategory = new CategoryResource($category);

            // Delete the category (DB cascades automatically)
            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Category and all its subcategories deleted successfully.',
                'category' => $deletedCategory,
            ]);
        } catch (Exception $e) {
            // For unexpected server/database errors
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category.',
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }
}
