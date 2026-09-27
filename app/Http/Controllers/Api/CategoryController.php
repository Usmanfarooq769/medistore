<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        return CategoryResource::collection(
            Category::withCount('medicines')
                ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
                ->orderBy('name')
                ->paginate($request->integer('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $category = Category::create($this->validated($request));

        return response()->json(['message' => 'Category added.', 'data' => new CategoryResource($category)], 201);
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category->id));

        return response()->json(['message' => 'Category updated.', 'data' => new CategoryResource($category)]);
    }

    public function destroy(Category $category)
    {
        abort_if($category->medicines()->exists(), 422, 'Category has medicines. Move them first.');
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->ignore($id)],
            'description' => 'nullable|string|max:255',
        ]);
    }
}
