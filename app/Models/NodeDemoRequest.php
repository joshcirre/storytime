<?php

namespace App\Models;

use Database\Factories\NodeDemoRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single request handed from the PHP app to the Node sidecar and back.
 *
 * @property string $id
 * @property string $status
 * @property array<string, mixed>|null $result
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['status', 'result', 'completed_at'])]
class NodeDemoRequest extends Model
{
    /** @use HasFactory<NodeDemoRequestFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
