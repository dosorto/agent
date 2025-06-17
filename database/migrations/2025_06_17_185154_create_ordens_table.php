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
        Schema::create('ordens', function (Blueprint $table) {
            $table->id();
            $table->integer('cliente_id');
            $table->enum('estado', ['pendiente', 'completada', 'cancelada'])->default('pendiente');
            $table->enum('metodo_pago', ['efectivo', 'transferencia', 'tarjeta'])->nullable();
            $table->enum('tipo_entrega', ['recoger', 'envio'])->nullable();
            $table->decimal('total', 10, 2)->default(0.00);
            $table->timestamps();

            //$table->foreign('cliente_id')->references('id')->on('clientes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordens');
    }
};
