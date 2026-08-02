<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('depots', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('agency_id', 26);
            $table->string('code');
            $table->string('name');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['agency_id', 'code']);
            $table->index('status');

            $table->foreign('agency_id')->references('id')->on('agencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depots');
    }
};
