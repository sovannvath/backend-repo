<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * Display the user's wishlist
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $wishlistItems = Wishlist::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'wishlist' => $wishlistItems
        ]);
    }

    /**
     * Add product to wishlist
     *
     * @param Request $request
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        // Check if product is already in wishlist
        $existingItem = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if ($existingItem) {
            return response()->json([
                'message' => 'Product is already in your wishlist'
            ], 400);
        }

        $wishlistItem = Wishlist::create([
            'user_id' => $request->user()->id,
            'product_id' => $productId
        ]);

        return response()->json([
            'message' => 'Product added to wishlist successfully',
            'wishlist_item' => $wishlistItem->load('product')
        ], 201);
    }

    /**
     * Remove product from wishlist
     *
     * @param Request $request
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $productId)
    {
        $wishlistItem = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->first();

        if (!$wishlistItem) {
            return response()->json([
                'message' => 'Product not found in wishlist'
            ], 404);
        }

        $wishlistItem->delete();

        return response()->json([
            'message' => 'Product removed from wishlist successfully'
        ]);
    }
}

