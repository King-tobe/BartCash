<?php

namespace App\Jobs;

use App\Models\Item;
use App\Models\ItemValuation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerItemValuation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 5;

    public function __construct(
        public readonly string $itemId,
        public readonly string $valuationId,
    ) {}

    public function handle(): void
    {
        $item       = Item::with('images')->find($this->itemId);
        $valuation  = ItemValuation::find($this->valuationId);

        if (!$item || !$valuation) {
            Log::error('TriggerItemValuation: item or valuation not found', [
                'item_id'      => $this->itemId,
                'valuation_id' => $this->valuationId,
            ]);
            return;
        }

        $primaryImage = $item->images->firstWhere('is_primary', true)
            ?? $item->images->first();

        if (!$primaryImage) {
            $valuation->update([
                'status'        => 'failed',
                'failed_reason' => 'No images available for valuation.',
            ]);
            return;
        }

        try {
$imageBase64 = $this->fetchImageAsBase64($primaryImage->storage_path);
            $prompt       = $this->buildPrompt($item);
            $result       = $this->callGroq($prompt, $imageBase64);

            $valuation->update([
                'value_min'    => $result['value_min'],
                'value_max'    => $result['value_max'],
                'confidence'   => $result['confidence'],
                'status'       => 'completed',
                'raw_response' => $result['raw'],
            ]);

        } catch (\Exception $e) {
            Log::error('TriggerItemValuation: Groq API error', [
                'item_id' => $this->itemId,
                'error'   => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $valuation->update([
                    'status'        => 'failed',
                    'failed_reason' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

private function fetchImageAsBase64(string $storagePath): string
{
    $publicUrl = rtrim(config('filesystems.disks.r2.url'), '/') . '/' . ltrim($storagePath, '/');

    $response = Http::timeout(15)->get($publicUrl);

    if (!$response->successful()) {
        throw new \Exception('Failed to fetch image for valuation: ' . $publicUrl);
    }

    return base64_encode($response->body());
}

    private function buildPrompt(Item $item): string
    {
        return implode(' ', [
            'You are a fair market value estimator for a barter marketplace.',
            'Based on the provided image and item details, estimate the current',
            'fair market value of this item in USD.',
            'Item title: ' . $item->title . '.',
            'Condition: ' . $item->condition . '.',
            'Description: ' . $item->description . '.',
            'Respond ONLY with a valid JSON object in this exact format:',
            '{"value_min": number, "value_max": number, "confidence": "low|medium|high"}',
            'Do not include any other text, explanation, or markdown.',
        ]);
    }

    private function callGroq(string $prompt, string $imageBase64): array
    {
        $apiKey   = config('services.groq.api_key');
        $model    = config('services.groq.model');
        $baseUrl  = config('services.groq.base_url');

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post("{$baseUrl}/chat/completions", [
                'model'       => $model,
                'temperature' => 0.1,
                'messages'    => [
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type'      => 'image_url',
                                'image_url' => [
                                    'url' => 'data:image/jpeg;base64,' . $imageBase64,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('Groq API request failed: ' . $response->body());
        }

        $raw  = $response->json();
        $text = $raw['choices'][0]['message']['content'] ?? null;

        if (!$text) {
            throw new \Exception('Groq returned an empty response.');
        }

        return $this->parseResponse($text, $raw);
    }

    private function parseResponse(string $text, array $raw): array
    {
        $text    = trim(preg_replace('/```json|```/', '', $text));
        $parsed  = json_decode($text, true);

        if (
            !isset($parsed['value_min'], $parsed['value_max'], $parsed['confidence']) ||
            !is_numeric($parsed['value_min']) ||
            !is_numeric($parsed['value_max']) ||
            $parsed['value_min'] > $parsed['value_max']
        ) {
            throw new \Exception('Groq returned an unexpected format: ' . $text);
        }

        return [
            'value_min'  => (float) $parsed['value_min'],
            'value_max'  => (float) $parsed['value_max'],
            'confidence' => $parsed['confidence'],
            'raw'        => $raw,
        ];
    }
}