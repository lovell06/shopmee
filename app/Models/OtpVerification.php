<?php

namespace App\Models;

use App\Enums\Purpose;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpVerification extends Model
{
    use HasFactory;

    protected $table = 'otp_verifications';

    /**
     * The name of the "updated at" column.
     * Since the migration only has created_at, we disable updated_at.
     */
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'code_hash',
        'purpose',
        'created_at',
        'expires_at',
        'used_at',
        'attempt_count',
    ];

    protected $casts = [
        'purpose' => Purpose::class,
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'attempt_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
