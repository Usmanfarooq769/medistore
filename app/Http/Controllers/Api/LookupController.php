<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Supplier;

/** One request for all dropdowns (categories, companies, suppliers) */
class LookupController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'categories'    => Category::orderBy('name')->get(['id', 'name']),
            'manufacturers' => Manufacturer::orderBy('name')->get(['id', 'name']),
            'suppliers'     => Supplier::orderBy('name')->get(['id', 'name', 'balance']),
        ]);
    }
}
