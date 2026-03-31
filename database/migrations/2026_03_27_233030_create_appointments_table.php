<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('scheduled_by')->constrained('users')->onDelete('restrict');

            // Fecha y hora
            $table->date('appointment_date');
            $table->time('appointment_time');

            // Tipo de cita
            $table->enum('type', [
                'entrega',       // Entrega de mercancía / materia prima
                'residuos',      // Recolección de residuos
                'auditoria',     // Visita de auditoría / calidad
                'calibracion',   // Calibración de equipos
                'servicio',      // Servicios generales
            ]);

            // Vehículo — catálogo o texto libre
            $table->foreignId('vehicle_id')->nullable()->constrained('provider_vehicles')->onDelete('set null');
            $table->string('vehicle_custom')->nullable(); // si no está en catálogo

            // Chofer / personal — catálogo o texto libre
            $table->foreignId('personnel_id')->nullable()->constrained('provider_personnel')->onDelete('set null');
            $table->string('driver_custom')->nullable(); // si no está en catálogo

            // Detalle
            $table->text('products')->nullable();     // productos a entregar
            $table->text('notes')->nullable();        // observaciones

            // Documento adjunto (opcional)
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();

            // Estado
            $table->enum('status', ['scheduled', 'confirmed', 'cancelled', 'completed'])
                ->default('scheduled');
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices para consultas frecuentes
            $table->index(['appointment_date', 'status']);
            $table->index(['provider_id', 'appointment_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};