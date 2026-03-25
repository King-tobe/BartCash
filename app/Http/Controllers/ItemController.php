<?php

namespace App\Http\Controllers;

use App\Http\Requests\Item\CreateItemRequest;
use App\Http\Requests\Item\ListItemsRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Http\Requests\Item\UploadItemImagesRequest;
use App\Jobs\TriggerItemValuation;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemValuation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    // -------------------------------------------------------------------------
    // POST /items
    // -------------------------------------------------------------------------
    public function create(CreateItemRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $category = Category::where('id', $validated['category_id'])
            ->where('is_active', true)
            ->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'The selected category is invalid or inactive.',
                'errors'  => ['category_id' => ['Category not found.']],
            ], 404);
        }

        $item = Item::create([
            'user_id'      => Auth::id(),
            'category_id'  => $validated['category_id'],
            'title'        => $validated['title'],
            'description'  => $validated['description'],
            'condition'    => $validated['condition'],
            'desired_trade'=> $validated['desired_trade'] ?? null,
            'is_service'   => $validated['is_service'] ?? false,
            'location'     => $validated['location'] ?? null,
            'status'       => 'available',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item listing created.',
            'data'    => [
                'item' => [
                    'id'               => $item->id,
                    'title'            => $item->title,
                    'status'           => $item->status,
                    'valuation_status' => 'pending',
                ],
            ],
        ], 201);
    }

    // -------------------------------------------------------------------------
    // POST /items/{id}/images
    // -------------------------------------------------------------------------
    public function uploadImages(UploadItemImagesRequest $request, string $id): JsonResponse
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        if ($item->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to upload images for this item.',
            ], 403);
        }

        $existingCount = $item->images()->count();

        if ($existingCount >= 6) {
            return response()->json([
                'success' => false,
                'message' => 'This item already has the maximum of 6 images.',
            ], 400);
        }

        $files         = $request->file('images');
        $remainingSlots = 6 - $existingCount;
        $files         = array_slice($files, 0, $remainingSlots);

        $uploadedImages = [];

        foreach ($files as $index => $file) {
            $path = $file->storeAs(
                'items/' . $item->id,
                uniqid() . '_' . time() . '.' . $file->getClientOriginalExtension(),
                'r2'
            );

            $url       = Storage::disk('r2')->url($path);
            $isPrimary = ($existingCount === 0 && $index === 0);

            $image = ItemImage::create([
                'item_id'       => $item->id,
                'url'           => $url,
                'storage_path'  => $path,
                'is_primary'    => $isPrimary,
                'display_order' => $existingCount + $index,
            ]);

            $uploadedImages[] = $image;
        }

        $valuation = ItemValuation::create([
            'item_id'  => $item->id,
            'status'   => 'pending',
            'currency' => 'USD',
        ]);

        TriggerItemValuation::dispatch($item->id, $valuation->id);

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded. Valuation triggered.',
            'data'    => [
                'images'           => $uploadedImages,
                'valuation_status' => 'pending',
            ],
        ], 201);
    }

    // -------------------------------------------------------------------------
    // GET /items
    // -------------------------------------------------------------------------
    public function index(ListItemsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $limit     = $validated['limit'] ?? 20;
        $userId    = Auth::id();

        $query = Item::with(['primaryImage', 'latestValuation', 'user'])
            ->where('status', 'available')
            ->where('user_id', '!=', $userId);

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', '%' . $search . '%')
                  ->orWhere('description', 'ilike', '%' . $search . '%');
            });
        }

        if (!empty($validated['category_id'])) {
            $query->where('category_id', $validated['category_id']);
        }

        if (!empty($validated['condition'])) {
            $query->where('condition', $validated['condition']);
        }

        if (!empty($validated['is_service'])) {
            $query->where('is_service', $validated['is_service']);
        }

        if (!empty($validated['value_min']) || !empty($validated['value_max'])) {
            $query->whereHas('latestValuation', function ($q) use ($validated) {
                if (!empty($validated['value_min'])) {
                    $q->where('value_max', '>=', $validated['value_min']);
                }
                if (!empty($validated['value_max'])) {
                    $q->where('value_min', '<=', $validated['value_max']);
                }
            });
        }

        if (!empty($validated['cursor'])) {
            $query->where('id', '<', $validated['cursor']);
        }

        $items = $query->orderByDesc('created_at')
            ->limit($limit + 1)
            ->get();

        $hasMore    = $items->count() > $limit;
        $items      = $items->take($limit);
        $nextCursor = $hasMore ? $items->last()->id : null;

        $formatted = $items->map(function ($item) {
            return [
                'id'            => $item->id,
                'title'         => $item->title,
                'condition'     => $item->condition,
                'is_service'    => $item->is_service,
                'location'      => $item->location,
                'primary_image' => $item->primaryImage?->url,
                'valuation'     => $item->latestValuation ? [
                    'value_min' => $item->latestValuation->value_min,
                    'value_max' => $item->latestValuation->value_max,
                    'status'    => $item->latestValuation->status,
                ] : null,
                'owner' => [
                    'id'             => $item->user->id,
                    'first_name'     => $item->user->first_name,
                    'last_name'      => $item->user->last_name,
                    'profile_photo'  => $item->user->profile_photo,
                    'average_rating' => $item->user->average_rating,
                ],
                'created_at' => $item->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Listings retrieved.',
            'data'    => [
                'items'       => $formatted,
                'next_cursor' => $nextCursor,
                'total'       => $formatted->count(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /items/mine
    // -------------------------------------------------------------------------
    public function mine(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $limit  = (int) ($request->query('limit', 20));
        $cursor = $request->query('cursor');

        $query = Item::with(['primaryImage', 'latestValuation'])
            ->where('user_id', Auth::id());

        $allowedStatuses = ['available', 'in_trade', 'traded', 'inactive'];

        if ($status && in_array($status, $allowedStatuses)) {
            $query->where('status', $status);
        }

        if ($cursor) {
            $query->where('id', '<', $cursor);
        }

        $items = $query->orderByDesc('created_at')
            ->limit($limit + 1)
            ->get();

        $hasMore    = $items->count() > $limit;
        $items      = $items->take($limit);
        $nextCursor = $hasMore ? $items->last()->id : null;

        $formatted = $items->map(function ($item) {
            return [
                'id'            => $item->id,
                'title'         => $item->title,
                'condition'     => $item->condition,
                'status'        => $item->status,
                'is_service'    => $item->is_service,
                'location'      => $item->location,
                'primary_image' => $item->primaryImage?->url,
                'valuation'     => $item->latestValuation ? [
                    'value_min' => $item->latestValuation->value_min,
                    'value_max' => $item->latestValuation->value_max,
                    'status'    => $item->latestValuation->status,
                ] : null,
                'created_at' => $item->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Your listings retrieved.',
            'data'    => [
                'items'       => $formatted,
                'next_cursor' => $nextCursor,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /items/{id}
    // -------------------------------------------------------------------------
    public function show(string $id): JsonResponse
    {
        $item = Item::with([
            'images',
            'latestValuation',
            'user',
            'category',
        ])->find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item retrieved.',
            'data'    => [
                'item' => [
                    'id'            => $item->id,
                    'title'         => $item->title,
                    'description'   => $item->description,
                    'condition'     => $item->condition,
                    'desired_trade' => $item->desired_trade,
                    'is_service'    => $item->is_service,
                    'status'        => $item->status,
                    'location'      => $item->location,
                    'category'      => [
                        'id'   => $item->category->id,
                        'name' => $item->category->name,
                        'slug' => $item->category->slug,
                    ],
                    'images'     => $item->images->map(fn($img) => [
                        'id'            => $img->id,
                        'url'           => $img->url,
                        'is_primary'    => $img->is_primary,
                        'display_order' => $img->display_order,
                    ]),
                    'valuation' => $item->latestValuation ? [
                        'id'            => $item->latestValuation->id,
                        'value_min'     => $item->latestValuation->value_min,
                        'value_max'     => $item->latestValuation->value_max,
                        'currency'      => $item->latestValuation->currency,
                        'confidence'    => $item->latestValuation->confidence,
                        'status'        => $item->latestValuation->status,
                        'failed_reason' => $item->latestValuation->failed_reason,
                    ] : null,
                    'owner' => [
                        'id'             => $item->user->id,
                        'first_name'     => $item->user->first_name,
                        'last_name'      => $item->user->last_name,
                        'profile_photo'  => $item->user->profile_photo,
                        'average_rating' => $item->user->average_rating,
                        'total_trades'   => $item->user->total_trades,
                    ],
                    'created_at' => $item->created_at,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // PUT /items/{id}
    // -------------------------------------------------------------------------
    public function update(UpdateItemRequest $request, string $id): JsonResponse
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        if ($item->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit this item.',
            ], 403);
        }

        if (in_array($item->status, ['in_trade', 'traded'])) {
            return response()->json([
                'success' => false,
                'message' => 'This item cannot be edited while in an active trade.',
            ], 422);
        }

        $validated = $request->validated();

        $descriptionChanged = isset($validated['description']) &&
            $validated['description'] !== $item->description;

        $conditionChanged = isset($validated['condition']) &&
            $validated['condition'] !== $item->condition;

        $item->update($validated);

        if ($descriptionChanged || $conditionChanged) {
            $valuation = ItemValuation::create([
                'item_id'  => $item->id,
                'status'   => 'pending',
                'currency' => 'USD',
            ]);

            TriggerItemValuation::dispatch($item->id, $valuation->id);
        }

        $item->load(['images', 'latestValuation', 'category']);

        return response()->json([
            'success' => true,
            'message' => 'Listing updated.',
            'data'    => [
                'item' => $item,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // PATCH /items/{id}/deactivate
    // -------------------------------------------------------------------------
    public function deactivate(string $id): JsonResponse
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        if ($item->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to deactivate this item.',
            ], 403);
        }

        if ($item->status === 'in_trade') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate an item that is currently in a trade.',
            ], 422);
        }

        $item->update(['status' => 'inactive']);

        return response()->json([
            'success' => true,
            'message' => 'Listing deactivated.',
            'data'    => [
                'item' => [
                    'id'     => $item->id,
                    'status' => $item->status,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // DELETE /items/{id}
    // -------------------------------------------------------------------------
    public function destroy(string $id): JsonResponse
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        if ($item->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this item.',
            ], 403);
        }

        if (in_array($item->status, ['in_trade', 'traded'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an item that is in an active trade or has been traded.',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Listing deleted.',
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /items/{id}/valuation
    // -------------------------------------------------------------------------
    public function valuation(string $id): JsonResponse
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        $valuation = ItemValuation::where('item_id', $id)
            ->latest()
            ->first();

        if (!$valuation) {
            return response()->json([
                'success' => false,
                'message' => 'No valuation found for this item.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Valuation retrieved.',
            'data'    => [
                'valuation' => [
                    'id'            => $valuation->id,
                    'value_min'     => $valuation->value_min,
                    'value_max'     => $valuation->value_max,
                    'currency'      => $valuation->currency,
                    'confidence'    => $valuation->confidence,
                    'status'        => $valuation->status,
                    'failed_reason' => $valuation->failed_reason,
                ],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /items/{id}/valuation/retry
    // -------------------------------------------------------------------------
    public function retryValuation(string $id): JsonResponse
    {
        $item = Item::with('images')->find($id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found.',
            ], 404);
        }

        if ($item->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to retry valuation for this item.',
            ], 403);
        }

        if ($item->images->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Please upload at least one image before retrying valuation.',
            ], 422);
        }

        $valuation = ItemValuation::where('item_id', $id)->latest()->first();

        if (!$valuation || $valuation->status !== 'failed') {
            return response()->json([
                'success' => false,
                'message' => 'Valuation retry is only available for failed valuations.',
            ], 422);
        }

        $valuation->update([
            'status'        => 'pending',
            'failed_reason' => null,
            'value_min'     => null,
            'value_max'     => null,
            'confidence'    => null,
            'raw_response'  => null,
        ]);

        TriggerItemValuation::dispatch($item->id, $valuation->id);

        return response()->json([
            'success' => true,
            'message' => 'Valuation retry triggered.',
            'data'    => [
                'valuation' => [
                    'id'     => $valuation->id,
                    'status' => 'pending',
                ],
            ],
        ]);
    }
}