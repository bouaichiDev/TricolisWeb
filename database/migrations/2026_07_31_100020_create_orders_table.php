<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->char('customer_id', 26);
            $table->char('agency_id', 26);
            $table->char('depot_id', 26)->nullable();
            $table->char('parent_order_id', 26)->nullable();
            $table->string('order_number');
            $table->string('external_reference')->nullable();
            $table->string('customer_reference')->nullable();
            $table->string('order_type', 64)->nullable();
            $table->string('group_code')->nullable();
            $table->dateTime('order_date');
            $table->string('source', 32)->default('internal');
            $table->text('internal_remark')->nullable();
            $table->text('worker_remark')->nullable();
            $table->decimal('weight', 12, 3)->default(0);
            $table->decimal('volume', 12, 4)->default(0);
            $table->unsignedInteger('package_count')->default(0);
            $table->char('currency_code', 3)->default('MAD');
            $table->string('status', 32)->default('draft');
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'order_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['customer_id', 'order_date']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('agency_id')->references('id')->on('agencies')->restrictOnDelete();
            $table->foreign('depot_id')->references('id')->on('depots')->nullOnDelete();
            $table->foreign('parent_order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
