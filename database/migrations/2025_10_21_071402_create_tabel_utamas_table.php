<?php

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
        Schema::create('tabel_utama', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')
            ->constrained('submissions')
            ->onDelete('cascade');
            $table->string('kode_tabel');
            $table->string('path');
            $table->string('matra'); // Changed from enum to string for flexibility
            $table->enum('status', ['draft', 'finalized', 'approved', 'rejected'])->default('draft');
            $table->text('catatan_admin')->nullable();

            $table->timestamps();
            $table->unique(['submission_id', 'kode_tabel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tabel_utama');
    }
};
