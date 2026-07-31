<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_links', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('document_id', 26);
            $table->string('entity_type', 64);
            $table->char('entity_id', 26);
            $table->timestamps();

            $table->unique(['document_id', 'entity_type', 'entity_id']);
            $table->index(['entity_type', 'entity_id']);

            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
    }
};
