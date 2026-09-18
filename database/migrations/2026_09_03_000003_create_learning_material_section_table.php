<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Learning materials are targeted at sections (many-to-many). A material with
     * no rows here is unassigned and is visible to nobody on the student side.
     */
    public function up(): void
    {
        Schema::create('learning_material_section', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['learning_material_id', 'section_id']);
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_material_section');
    }
};
