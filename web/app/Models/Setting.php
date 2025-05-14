<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'description'
    ];

    /**
     * Get all settings as key-value pairs for a specific group
     * 
     * @param string $group
     * @return array
     */
    public static function getGroup($group)
    {
        return self::where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Get a setting value by key
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getValue($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }
        
        // Cast the value according to its type
        switch ($setting->type) {
            case 'boolean':
                return (bool) $setting->value;
            case 'integer':
                return (int) $setting->value;
            case 'float':
                return (float) $setting->value;
            case 'json':
                return json_decode($setting->value, true);
            default:
                return $setting->value;
        }
    }

    /**
     * Set a setting value
     * 
     * @param string $key
     * @param mixed $value
     * @param string $group
     * @param string $type
     * @param string $description
     * @return Setting
     */
    public static function setValue($key, $value, $group = 'general', $type = 'string', $description = '')
    {
        // Prepare the value based on its type
        if ($type === 'json' && !is_string($value)) {
            $value = json_encode($value);
        }

        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'type' => $type,
                'description' => $description
            ]
        );
    }
}
