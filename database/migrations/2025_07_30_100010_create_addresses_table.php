<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('address_line_3')->nullable();
            $table->string('floor')->nullable();
            $table->string('address_number')->nullable();
            $table->string('route')->nullable();
            $table->string('sublocality')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('town')->nullable();
            $table->string('country', 2)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->text('instructions')->nullable();
            $table->time('time_window_from')->nullable();
            $table->time('time_window_to')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
            $table->index(['country', 'city']);
            $table->index('postal_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
