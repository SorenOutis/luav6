<?php

namespace App\Filament\Resources\AnonymousMessages\Pages;

use App\Filament\Resources\AnonymousMessages\AnonymousMessageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnonymousMessage extends CreateRecord
{
    protected static string $resource = AnonymousMessageResource::class;
}
