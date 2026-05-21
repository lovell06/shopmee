<?php

use App\Enums\Purpose;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code_hash', 256);
            $table->enum('purpose', Purpose::values());
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);

            $table->index(['user_id', 'purpose']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
