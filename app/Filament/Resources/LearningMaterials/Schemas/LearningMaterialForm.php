<?php

namespace App\Filament\Resources\LearningMaterials\Schemas;

use App\Filament\Resources\LearningMaterialCategories\Schemas\LearningMaterialCategoryForm;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LearningMaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Select::make('learning_material_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Select category')
                    ->createOptionForm(fn (Schema $schema) => LearningMaterialCategoryForm::configure($schema))
                    ->columnSpan(1),

                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ])
                    ->required()
                    ->default('draft')
                    ->helperText('Draft is hidden from students. Published is visible to targeted sections.')
                    ->columnSpan(1),

                Select::make('sections')
                    ->label('Assign to sections')
                    ->relationship('sections', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required()
                    ->minItems(1)
                    ->helperText('Only students in the selected sections can see this material.')
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->maxLength(65535)
                    ->rows(3)
                    ->columnSpanFull(),

                FileUpload::make('file_path')
                    ->label('Document (PDF / DOCX / PPTX)')
                    ->required()
                    ->disk('public')
                    ->directory('library')
                    ->visibility('public')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'application/msword',
                        'application/vnd.ms-powerpoint',
                    ])
                    ->maxSize(51200)
                    ->storeFileNamesIn('file_name')
                    ->helperText('Allowed: PDF, DOCX, PPTX up to 50MB. Filename is sanitized on storage.')
                    ->columnSpanFull(),

                FileUpload::make('cover_image')
                    ->label('Cover image (optional)')
                    ->image()
                    ->disk('public')
                    ->directory('library/covers')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->helperText('Optional thumbnail for the card view.')
                    ->columnSpanFull(),

                Toggle::make('is_downloadable')
                    ->label('Allow download')
                    ->helperText('When off, students can only preview inline — download button is hidden and file route blocks attachment.')
                    ->default(true)
                    ->inline(false)
                    ->columnSpan(1),

                TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Lower numbers appear first.')
                    ->columnSpan(1),

                TextInput::make('file_name')
                    ->label('Original file name')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Auto-filled on upload')
                    ->visibleOn('edit')
                    ->columnSpan(1),
            ]);
    }
}
