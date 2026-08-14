<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['title', 'description', 'link', 'is_active', 'section_id', 'admin_id'];

    /**
     * The section this announcement is targeted to.
     * Null means the announcement is shown to students in every section.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
