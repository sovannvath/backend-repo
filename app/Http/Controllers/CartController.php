<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Display the user's cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $cart = $request->user()->cart()->with('cartItems.product')->first();
        
        if (!$cart) {
            $cart = Cart::create([
                'user_id' => $request->user()->id
            ]);
        }
        
        return response()->json([
            'cart' => $cart,
            'total_amount' => $cart->getTotalAmount()
        ]);
    }

    /**
     * Add a product to the cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cart = $request->user()->cart;
        
        if (!$cart) {
            $cart = Cart::create([
                'user_id' => $request->user()->id
            ]);
        }

        // Check if product has enough stock
        $product = Product::findOrFail($request->product_id);
        if ($product->quantity < $request->quantity) {
            return response()->json([
                'message' => 'Not enough stock available'
            ], 400);
        }

        // Check if item already exists in cart
        $cartItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($cartItem) {
            // Update quantity if item exists
            $cartItem->quantity += $request->quantity;
            $cartItem->save();
        } else {
            // Create new cart item
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity
            ]);
        }

        return response()->json([
            'message' => 'Product added to cart',
            'cart' => $cart->fresh()->load('cartItems.product'),
            'total_amount' => $cart->fresh()->getTotalAmount()
        ]);
    }

    /**
     * Update cart item quantity.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateItem(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $cart = $request->user()->cart;
        $cartItem = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        
        // Check if product has enough stock
        $product = Product::findOrFail($cartItem->product_id);
        if ($product->quantity < $request->quantity) {
            return response()->json([
                'message' => 'Not enough stock available'
            ], 400);
        }

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        return response()->json([
            'message' => 'Cart item updated',
            'cart' => $cart->fresh()->load('cartItems.product'),
            'total_amount' => $cart->fresh()->getTotalAmount()
        ]);
    }

    /**
     * Remove an item from the cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeItem(Request $request, $id)
    {
        $cart = $request->user()->cart;
        $cartItem = CartItem::where('cart_id', $cart->id)->findOrFail($id);
        $cartItem->delete();

        return response()->json([
            'message' => 'Item removed from cart',
            'cart' => $cart->fresh()->load('cartItems.product'),
            'total_amount' => $cart->fresh()->getTotalAmount()
        ]);
    }

    /**
     * Clear the cart.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clear(Request $request)
    {
        $cart = $request->user()->cart;
        CartItem::where('cart_id', $cart->id)->delete();

        return response()->json([
            'message' => 'Cart cleared',
            'cart' => $cart->fresh()->load('cartItems.product'),
            'total_amount' => 0
        ]);
    }
}
