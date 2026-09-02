<?php

namespace App\Filament\Resources\LearningMaterialCategories\Schemas;

use App\Support\WorkspaceContext;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class LearningMaterialCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set) {
                        if (filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule, $get) {
                        $workspaceId = app(WorkspaceContext::class)->id();

                        return $rule->where('workspace_id', $workspaceId);
                    })
                    ->helperText('Unique per workspace. Auto-generated from name.'),
                Textarea::make('description')
                    ->maxLength(1000)
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
