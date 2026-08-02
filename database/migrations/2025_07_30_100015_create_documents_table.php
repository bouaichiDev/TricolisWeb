<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('organization_id', 26);
            $table->string('reference_number')->nullable();
            $table->string('document_type', 64);
            $table->string('status', 20)->default('active');
            $table->string('file_name');
            $table->string('storage_path');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->timestamp('received_at')->nullable();
            $table->char('created_by', 26)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'document_type']);
            $table->index('status');
            $table->index('created_by');

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
