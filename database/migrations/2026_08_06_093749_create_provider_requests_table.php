<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_requests', function (Blueprint $table) {
            $table->id();

            // Quién solicita y de qué departamento
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();

            // Datos del proveedor propuesto (para que Compras sepa a quién invitar)
            $table->string('provider_business_name');
            $table->string('provider_contact_name');
            $table->string('provider_contact_phone');
            $table->string('provider_contact_email');
            $table->foreignId('provider_type_id')->constrained('provider_types')->restrictOnDelete();

            $table->text('notes')->nullable();

            // Ciclo de vida: pending -> invited -> registered -> active
            //                                   \-> rejected
            $table->string('status')->default('pending');

            // Se enlazan conforme avanza el flujo
            $table->foreignId('provider_invitation_id')->nullable()
                ->constrained('provider_invitations')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()
                ->constrained('providers')->nullOnDelete();

            // Quién procesó la solicitud (envió invitación o rechazó) y cuándo
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_requests');
    }
};