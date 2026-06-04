<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // Bổ sung thêm 
            $table->foreignId('user_address_id')->constrained('user_addresses'); // Liên kết địa chỉ nhận hàng
            $table->decimal('total_amount', 15, 2)->default(0); // Lưu tổng số tiền đơn hàng
            
            $table->string('description', 2000)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->enum('status', OrderStatus::values())->default(OrderStatus::Pending->value);
            $table->enum('payment_status', PaymentStatus::values())->default(PaymentStatus::Pending->value);
            $table->enum('payment_method', PaymentMethod::values());

            $table->index(['user_id', 'status']);
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
