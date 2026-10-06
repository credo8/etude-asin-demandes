<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->string('numero_npi', 10);
            $table->string('type_acte', 30);
            $table->unsignedTinyInteger('nombre_copies');
            $table->string('statut', 20)->default('deposee');
            $table->text('motif_rejet')->nullable();
            $table->timestamps();
            $table->index(['numero_npi', 'created_at']);
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
