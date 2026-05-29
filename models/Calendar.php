<?php namespace JumpLink\Events\Models;

use Model;

/**
 * Calendar Model – ersetzt die Firestore-Collection customerDomains/<domain>/calendars
 */
class Calendar extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \Winter\Storm\Database\Traits\Sortable;

    public $table = 'jumplink_events_calendars';

    public $rules = [
        'name' => 'required|max:255',
    ];

    public $fillable = [
        'name', 'title', 'subtitle', 'description', 'note',
        'color', 'css_class', 'type', 'is_active', 'sort_order', 'firestore_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $hasMany = [
        'events' => [\JumpLink\Events\Models\Event::class],
    ];

    public $attachMany = [
        'images' => [\System\Models\File::class, 'order' => 'sort_order'],
    ];

    public function scopeIsActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Optionen für Dropdown-Felder (name => title).
     */
    public function getNameOptions()
    {
        return self::orderBy('name')->lists('name', 'name');
    }
}
