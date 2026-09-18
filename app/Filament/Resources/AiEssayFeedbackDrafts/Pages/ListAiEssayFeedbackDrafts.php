<?php

namespace App\Filament\Resources\AiEssayFeedbackDrafts\Pages;

use App\Filament\Resources\AiEssayFeedbackDrafts\AiEssayFeedbackDraftResource;
use Filament\Resources\Pages\ListRecords;

class ListAiEssayFeedbackDrafts extends ListRecords
{
    protected static string $resource = AiEssayFeedbackDraftResource::class;

    public function getSubheading(): ?string
    {
        return 'AI scores and written feedback remain private drafts until a teacher explicitly approves them.';
    }
}
