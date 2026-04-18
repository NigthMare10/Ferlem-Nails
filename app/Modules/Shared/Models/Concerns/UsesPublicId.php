<?php

namespace App\Modules\Shared\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

trait UsesPublicId
{
    use HasUlids;

    public function initializeUsesPublicId(): void
    {
        $this->fillable[] = 'public_id';
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
