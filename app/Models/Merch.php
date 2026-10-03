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
        ];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => PublicFileUrl::resolve($this->image_path),
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
