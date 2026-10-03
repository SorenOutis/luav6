<?php

namespace App\Models;

use App\Support\PublicFileUrl;
use Database\Factories\MerchFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merch extends Model
{
    /** @use HasFactory<MerchFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'stock',
        'is_out_of_stock',
        'image_path',
        'variants',
        'button_url',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'is_out_of_stock' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'variants' => 'array',
        ];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! empty($this->image_path)) {
                    return PublicFileUrl::resolve($this->image_path);
                }

                $variants = $this->formatted_variants;
                if (! empty($variants[0]['image_url'])) {
                    return $variants[0]['image_url'];
                }

                return null;
            },
        );
    }

    /**
     * @return Attribute<array<int, array{name: string, image_path: string|null, image_url: string|null}>, never>
     */
    protected function formattedVariants(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                if (empty($this->variants) || ! is_array($this->variants)) {
                    return [];
                }

                $formatted = [];
                foreach ($this->variants as $variant) {
                    if (! is_array($variant)) {
                        continue;
                    }

                    $name = trim((string) ($variant['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $path = $variant['image_path'] ?? $variant['image_url'] ?? null;

                    $formatted[] = [
                        'name' => $name,
                        'image_path' => $path,
                        'image_url' => PublicFileUrl::resolve($path),
                    ];
                }

                return $formatted;
            },
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedPrice(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $symbol = match ($this->currency) {
                    'PHP', '₱' => '₱',
                    'USD', '$' => '$',
                    'EUR', '€' => '€',
                    'GBP', '£' => '£',
                    'JPY', '¥' => '¥',
                    default => ($this->currency ?? '₱').' ',
                };

                return $symbol.number_format((float) $this->price, 2);
            },
        );
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function effectiveOutOfStock(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => (bool) ($this->is_out_of_stock || $this->stock <= 0),
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function stockStatusLabel(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->is_out_of_stock || $this->stock <= 0) {
                    return 'Out of Stock';
                }

                if ($this->stock <= 5) {
                    return "Only {$this->stock} left";
                }

                return "{$this->stock} in stock";
            },
        );
    }

    /**
     * @return Attribute<string, never>
     */
    protected function redirectUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): string => ! empty($this->button_url) ? $this->button_url : 'https://koamishin.com/',
        );
    }
}
