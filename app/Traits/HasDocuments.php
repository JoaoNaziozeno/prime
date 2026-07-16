<?php

namespace App\Traits;

use App\Models\Tenant\Document;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDocuments
{
    /**
     * Get all of the entity's documents.
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
