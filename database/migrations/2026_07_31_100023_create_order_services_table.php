<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_services', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('order_id', 26);
            $table->char('service_id', 26);
            $table->char('address_id', 26);
            $table->string('service_number');
            $table->unsignedInteger('sequence');
            $table->date('requested_date');
            $table->dateTime('requested_from')->nullable();
            $table->dateTime('requested_to')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 32);
            $table->unsignedInteger('required_time_minutes');
            $table->unsignedInteger('remaining_time_minutes');
            $table->decimal('weight', 12, 3);
            $table->decimal('volume', 12, 4);
            $table->unsignedInteger('package_count');
            $table->decimal('customer_unit_price', 12, 2);
            $table->decimal('customer_total_price', 12, 2);
            $table->decimal('provider_unit_cost', 12, 2);
            $table->decimal('provider_total_cost', 12, 2);
            $table->text('instructions')->nullable();
            $table->string('status', 32);
            $table->timestamps();
            $table->unique(['order_id', 'service_number']);
            $table->unique(['order_id', 'sequence']);
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->restrictOnDelete();
            $table->foreign('address_id')->references('id')->on('addresses')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_services');
    }
};
