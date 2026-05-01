<?php

namespace App\Filament\Resources\MemberSubscriptions\Pages;

use App\Filament\Resources\MemberSubscriptions\MemberSubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListMemberSubscriptions extends ListRecords
{
    protected static string $resource = MemberSubscriptionResource::class;
}
