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
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
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
    public function store(StoreCategoryRequest $request)
    {
        $validated = $request->validated();

        try {
            $createdCategories = DB::transaction(function () use ($validated) {

                $parentCatName = $validated['name'] ?? null;
                $parentId      = $validated['parent_id'] ?? null;
                $subCategories = $validated['subcategories'] ?? [];

                $created = collect();

                // Case:01 - Create parent category + subCategories categories if provided
                if (!empty($parentCatName) && !empty($subCategories)) {

                    $parentCategory = Category::create([
                        'name'      => $parentCatName,
                        'parent_id' => $parentId
                    ]);

                    $created->push($parentCategory);

                    foreach ($subCategories as $key => $subcategory) {
                        $childCategories = Category::create([
                            'name' => $subcategory['name'] ?? $subcategory,
                            'parent_id' => $parentCategory->id,
                        ]);

                        $created->push($childCategories);
                    }

                    return $created;
                }


                // Case:02 -  Create subcategories under existing parent
                if ($parentId && !empty($subCategories)) {
                    foreach ($subCategories as $key => $subcategory) {

                        $childCategories = Category::create([
                            'name' => $subcategory['name'] ?? $subcategory,
                            'parent_id' => $parentId,
                        ]);

                        $created->push($childCategories);
                    }

                    return $created;
                }

                // Case:03 - Invalid input → rollback automatically
                throw new Exception('Invalid input. Provide either a category name or subcategories.');
            });

            // Load children for each category safely
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
    public function show(Category $category)
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
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $validated = $request->validated();

        try {
            $updatedCategories = DB::transaction(function () use ($validated, $category) {

                // Initialize collection to track updated categories
                $updated = collect([$category]);

                if (array_key_exists('parent_id', $validated)) {

                    $parentId = $validated['parent_id'];

                    /*
                    |-------------------------------------------------------------------
                    | Validate parent assignment
                    |-------------------------------------------------------------------
                    | Prevent the category from being its own parent or creating a cyclic tree.
                    */
                    if ($parentId === $category->id) {

                        // Throw exception instead
                        throw new \InvalidArgumentException(
                            'A category cannot be its own parent; here: ' . json_encode([
                                'input_parent_id' => $parentId,
                                'category_id'     => $category->id,
                            ])
                        );
                    }

                    if ($parentId && $this->isDescendantOf($category->id, $parentId)) {
                        throw new \InvalidArgumentException(
                            'Cannot assign a descendant category as parent; here: ' . json_encode([
                                'input_parent_id' => $parentId,
                                'category_id'     => $category->id,
                            ])
                        );
                    }

                    // -------------------------------
                    // Update main category
                    // -------------------------------
                    if (array_key_exists('name', $validated)) {
                        $category->name = $validated['name'];
                    }

                    $category->parent_id = $parentId;
                    $category->save();

                    // -------------------------------
                    // Handle subcategories (create/update/sync)
                    // -------------------------------
                    if (!empty($validated['subcategories'])) {
                        foreach ($validated['subcategories'] as $sub) {
                            $subData = is_string($sub) ? ['name' => $sub] : $sub;

                            if (!empty($subData['id'])) {
                                $existingSub = Category::where('parent_id', $parentId)
                                    ->where('id', $subData['id'])
                                    ->first();

                                if ($existingSub) {
                                    // Update existing subcategories
                                    $existingSub->update([
                                        'name' => $subData['name'] ?? $existingSub->name
                                    ]);

                                    $updated->push($existingSub);
                                }
                            } else {
                                // Create new subcategory under this category
                                $newSub = Category::create([
                                    'name'      => $subData['name'],
                                    'parent_id' => $category->id
                                ]);

                                $updated->push($newSub);
                            }
                        }
                    }
                }

                // Load relationship for response
                $updated->each->load(['parent', 'children']);

                return $updated;
            });

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully.',
                'categories' => CategoryResource::collection($updatedCategories),
            ]);
        } catch (\Exception $e) {
            // For unexpected server/database errors
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
     * Used to prevent cyclic parent assignments.
     */
    private function isDescendantOf(int $categoryId, int $targetId): bool
    {
        $target = Category::find($targetId);
        if (!$target) return false;

        // Climb up the tree to see if the target's ancestor = categoryId
        while ($target->parent_id) {
            if ($target->parent_id === $categoryId) {
                return true;
            }
            $target = $target->parent;
        }

        return false;
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        try {
            // Optionally load children if want to return deleted hierarchy
            $category->load('children');

            // Wrap in resource before deletion if you want the API to return it
            $deletedCategory = new \App\Http\Resources\CategoryResource($category);

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
