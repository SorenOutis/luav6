<?php

namespace App\Filament\Resources\Merches\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MerchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Details')
                    ->description('Primary details and pricing for this merchandise.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Merch Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. KOAMISHIN Signature Heavyweight Tee'),

                        Grid::make(3)->schema([
                            TextInput::make('price')
                                ->label('Price')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->required()
                                ->placeholder('750.00'),

                            Select::make('currency')
                                ->label('Currency')
                                ->options([
                                    'PHP' => 'PHP (₱)',
                                    'USD' => 'USD ($)',
                                    'EUR' => 'EUR (€)',
                                    'GBP' => 'GBP (£)',
                                    'JPY' => 'JPY (¥)',
                                ])
                                ->default('PHP')
                                ->required(),

                            TextInput::make('stock')
                                ->label('Available Stock')
                                ->numeric()
                                ->integer()
                                ->minValue(0)
                                ->default(10)
                                ->required()
                                ->helperText('Displayed on the upper right of the card image.'),
                        ]),

                        Textarea::make('description')
                            ->label('Details / Description')
                            ->rows(3)
                            ->placeholder('e.g. 240 GSM combed cotton with high-density embroidered insignia.')
                            ->helperText('Displayed under the merchandise title.'),

                        TextInput::make('button_url')
                            ->label('"Shop Now" Destination URL')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://koamishin.com/')
                            ->helperText('Defaults to https://koamishin.com/ when left blank.'),

                        Grid::make(2)->schema([
                            Toggle::make('is_out_of_stock')
                                ->label('Force "Out of Stock"')
                                ->default(false)
                                ->helperText('When enabled, the card displays "Out of Stock" regardless of numerical inventory count.'),

                            Toggle::make('is_active')
                                ->label('Publish to Welcome Page Shop')
                                ->default(true)
                                ->helperText('When enabled, this merchandise item is visible on the landing page.'),
                        ]),
                    ]),

                Section::make('Media & Ordering')
                    ->description('Product imagery and display order on the landing page.')
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Product Image')
                            ->image()
                            ->disk('public')
                            ->directory('merch')
                            ->visibility('public')
                            ->maxSize(10240)
                            ->helperText('High-resolution product photo or mockup displayed on the card.'),

                        TextInput::make('sort_order')
                            ->label('Display Order')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->helperText('Items with lower numbers display first on the welcome page.'),
                    ]),
            ]);
    }
}
