<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartAddRequest;
use App\Http\Requests\CartSetQtyRequest;
use App\Http\Resources\CartResource;
use App\Models\CakeDesign;
use App\Models\Cart;
use App\Models\CustomCakeCartItem;
use App\Models\Dessert;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private function userCart(Request $request): Cart
    {
        return Cart::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);
    }

    public function show(Request $request)
    {
        $cart = $this->userCart($request)->load(['items.dessert', 'customCakeItems']);

        return new CartResource($cart);
    }

    public function add(CartAddRequest $request)
    {
        $cart = $this->userCart($request);

        $dessert = Dessert::findOrFail($request->dessert_id);
        if (! $dessert->available || $dessert->archived) {
            return response()->json([
                'success' => false,
                'error' => 'Этот товар сейчас недоступен для заказа.',
            ], 409);
        }

        $item = $cart->items()
            ->where('dessert_id', $dessert->id)
            ->first();

        if ($item) {
            $item->qty += $request->qty;
            // цену можно НЕ менять, чтобы сохранять "цену на момент добавления"
            $item->save();
        } else {
            $cart->items()->create([
                'dessert_id' => $dessert->id,
                'qty' => $request->qty,
                'price' => $this->normalizeMoney($dessert->price), // фиксируем цену при первом добавлении
            ]);
        }

        return new CartResource($cart->load(['items.dessert', 'customCakeItems']));
    }

    public function setQty(CartSetQtyRequest $request, Dessert $dessert)
    {
        $cart = $this->userCart($request);

        if ((! $dessert->available || $dessert->archived) && $request->qty > 0) {
            return response()->json([
                'success' => false,
                'error' => 'Этот товар сейчас недоступен для заказа.',
            ], 409);
        }

        $item = $cart->items()
            ->where('dessert_id', $dessert->id)
            ->first();

        if (! $item) {
            if ($request->qty <= 0) {
                return new CartResource($cart->load(['items.dessert', 'customCakeItems']));
            }

            // если не было — можно создать
            $cart->items()->create([
                'dessert_id' => $dessert->id,
                'qty' => $request->qty,
                'price' => $this->normalizeMoney($dessert->price),
            ]);
        } else {
            if ($request->qty <= 0) {
                $item->delete();
            } else {
                $item->qty = $request->qty;
                $item->save();
            }
        }

        return new CartResource($cart->load(['items.dessert', 'customCakeItems']));
    }

    public function remove(Request $request, Dessert $dessert)
    {
        $cart = $this->userCart($request);

        $cart->items()
            ->where('dessert_id', $dessert->id)
            ->delete();

        return new CartResource($cart->load(['items.dessert', 'customCakeItems']));
    }

    public function addCustomCake(Request $request)
    {
        $data = $request->validate([
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            ...$this->customCakeRules('custom_cake'),
        ]);

        $payload = $data['custom_cake'];
        $this->validateCustomCakeWeight($payload);

        $cart = $this->userCart($request);
        $cart->customCakeItems()->create([
            'qty' => (int) ($data['qty'] ?? 1),
            'price' => $this->customCakePrice($payload),
            'payload' => $payload,
        ]);

        return new CartResource($cart->load(['items.dessert', 'customCakeItems']));
    }

    public function setCustomCakeQty(CartSetQtyRequest $request, CustomCakeCartItem $item)
    {
        $cart = $this->userCart($request);

        if ((int) $item->cart_id !== (int) $cart->id) {
            abort(404);
        }

        if ($request->qty <= 0) {
            $item->delete();
        } else {
            $item->qty = $request->qty;
            $item->save();
        }

        return new CartResource($cart->load(['items.dessert', 'customCakeItems']));
    }

    public function removeCustomCake(Request $request, CustomCakeCartItem $item)
    {
        $cart = $this->userCart($request);

        if ((int) $item->cart_id !== (int) $cart->id) {
            abort(404);
        }

        $item->delete();

        return new CartResource($cart->load(['items.dessert', 'customCakeItems']));
    }

    public function clear(Request $request)
    {
        $cart = $this->userCart($request);

        $cart->items()->delete();
        $cart->customCakeItems()->delete();

        return new CartResource($cart->load(['items.dessert', 'customCakeItems']));
    }

    private function normalizeMoney(mixed $value): int
    {
        return (int) round((float) $value);
    }

    private function customCakeRules(string $prefix): array
    {
        return [
            "{$prefix}" => ['required', 'array'],
            "{$prefix}.design_id" => ['required', 'string', 'max:255'],
            "{$prefix}.design_name" => ['required', 'string', 'max:255'],
            "{$prefix}.weight_title" => ['required', 'string', 'max:50'],
            "{$prefix}.weight_grams" => ['required', 'integer', 'min:1'],
            "{$prefix}.inscription" => ['nullable', 'string', 'max:255'],
            "{$prefix}.wishes" => ['nullable', 'string', 'max:500'],
            "{$prefix}.filling" => ['nullable', 'string', 'max:1000'],
            "{$prefix}.accent" => ['nullable', 'string', 'max:1000'],
            "{$prefix}.composition" => ['nullable', 'string', 'max:2000'],
            "{$prefix}.preview_image_base64" => ['nullable', 'string'],
        ];
    }

    private function customCakePrice(array $customCake): int
    {
        $design = CakeDesign::query()
            ->where('slug', $customCake['design_id'])
            ->where('available', true)
            ->first();

        if ($design) {
            return (int) round((float) $design->price * ((int) $customCake['weight_grams'] / 100));
        }

        return (int) round(250 * ((int) $customCake['weight_grams'] / 100));
    }

    private function validateCustomCakeWeight(array $customCake): void
    {
        $design = CakeDesign::query()
            ->where('slug', $customCake['design_id'])
            ->where('available', true)
            ->first();

        if (! $design) {
            return;
        }

        $weights = is_array($design->available_weights) ? $design->available_weights : [];
        $allowedWeights = [];

        foreach ($weights as $weight) {
            $grams = (int) ($weight['grams'] ?? 0);
            if ($grams > 0) {
                $allowedWeights[] = $grams;
            }
        }

        if ($allowedWeights === []) {
            $allowedWeights = [800, 1200, 1500];
        }

        if (! in_array((int) $customCake['weight_grams'], $allowedWeights, true)) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'error' => 'Выбранный вес торта недоступен.',
            ], 422));
        }
    }
}
