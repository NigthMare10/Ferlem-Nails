<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('razon_social')->nullable();
            $table->string('rtn', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('timezone')->default('America/Tegucigalpa');
            $table->string('currency_code', 3)->default('HNL');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('configuraciones_sucursal', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('currency_code', 3)->default('HNL');
            $table->string('currency_symbol', 5)->default('L');
            $table->string('impuesto_nombre')->default('ISV');
            $table->decimal('impuesto_porcentaje', 5, 2)->default(15.00);
            $table->unsignedTinyInteger('ventana_reautenticacion_minutos')->default(15);
            $table->unsignedTinyInteger('descuento_sin_reautenticacion_porcentaje')->default(10);
            $table->boolean('permitir_precio_manual')->default(false);
            $table->timestamps();

            $table->unique('sucursal_id');
        });

        Schema::create('sucursal_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['sucursal_id', 'user_id']);
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('rtn', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('perfiles_cliente', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('alias')->nullable();
            $table->text('alertas')->nullable();
            $table->text('preferencias')->nullable();
            $table->bigInteger('saldo_a_favor')->default(0);
            $table->bigInteger('ticket_promedio')->default(0);
            $table->timestamp('ultima_visita_at')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'sucursal_id']);
        });

        Schema::create('empleados', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('role_title')->nullable();
            $table->date('hire_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('empleado_sucursal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('role_title')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['empleado_id', 'sucursal_id']);
        });

        Schema::create('categorias_servicio', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('servicios', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('categoria_servicio_id')->constrained('categorias_servicio')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->bigInteger('base_price_amount');
            $table->boolean('requires_employee')->default(true);
            $table->boolean('allow_manual_price')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('empleado_servicio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['empleado_id', 'servicio_id']);
        });

        Schema::create('precio_servicios', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['servicio_id', 'sucursal_id', 'effective_from']);
        });

        Schema::create('historial_precios_servicio', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('servicio_id')->constrained('servicios')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->bigInteger('previous_amount');
            $table->bigInteger('new_amount');
            $table->string('reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });

        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('perfil_cliente_id')->nullable()->constrained('perfiles_cliente')->nullOnDelete();
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->foreignId('servicio_id')->constrained('servicios')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('scheduled_start');
            $table->dateTime('scheduled_end');
            $table->string('status', 30)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sucursal_id', 'scheduled_start']);
        });

        Schema::create('sesiones_caja', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->index();
            $table->bigInteger('opening_amount');
            $table->bigInteger('expected_amount')->default(0);
            $table->bigInteger('counted_amount')->nullable();
            $table->bigInteger('difference_amount')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ordenes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('perfil_cliente_id')->nullable()->constrained('perfiles_cliente')->nullOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->foreignId('sesion_caja_id')->nullable()->constrained('sesiones_caja')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->index();
            $table->bigInteger('subtotal_amount')->default(0);
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->string('currency_code', 3)->default('HNL');
            $table->text('notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sucursal_id', 'status', 'created_at']);
        });

        Schema::create('detalle_ordenes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('orden_id')->constrained('ordenes')->cascadeOnDelete();
            $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->string('description');
            $table->unsignedSmallInteger('duration_minutes')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('unit_price_amount');
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('discount_amount')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->timestamps();
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('orden_id')->constrained('ordenes')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('sesion_caja_id')->nullable()->constrained('sesiones_caja')->nullOnDelete();
            $table->string('method', 30);
            $table->string('status', 30)->index();
            $table->bigInteger('amount');
            $table->string('reference')->nullable()->index();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['orden_id', 'status']);
        });

        Schema::create('secuencias_documento', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->string('prefix', 20);
            $table->unsignedBigInteger('current_number')->default(0);
            $table->unsignedTinyInteger('padding')->default(8);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['sucursal_id', 'document_type']);
        });

        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('orden_id')->constrained('ordenes')->restrictOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('secuencia_documento_id')->nullable()->constrained('secuencias_documento')->nullOnDelete();
            $table->string('number');
            $table->string('status', 30)->index();
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('total_amount');
            $table->string('currency_code', 3)->default('HNL');
            $table->timestamp('issued_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['sucursal_id', 'number']);
            $table->unique('orden_id');
        });

        Schema::create('detalle_facturas', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->foreignId('servicio_id')->nullable()->constrained('servicios')->nullOnDelete();
            $table->foreignId('empleado_id')->nullable()->constrained('empleados')->nullOnDelete();
            $table->string('description');
            $table->unsignedSmallInteger('duration_minutes')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('unit_price_amount');
            $table->bigInteger('subtotal_amount');
            $table->bigInteger('discount_amount')->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->timestamps();
        });

        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('sesion_caja_id')->constrained('sesiones_caja')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->foreignId('orden_id')->nullable()->constrained('ordenes')->nullOnDelete();
            $table->string('type', 30);
            $table->string('direction', 10);
            $table->bigInteger('amount');
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['sesion_caja_id', 'type']);
        });

        Schema::create('auditoria_eventos', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('action', 120)->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria_eventos');
        Schema::dropIfExists('movimientos_caja');
        Schema::dropIfExists('detalle_facturas');
        Schema::dropIfExists('facturas');
        Schema::dropIfExists('secuencias_documento');
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('detalle_ordenes');
        Schema::dropIfExists('ordenes');
        Schema::dropIfExists('sesiones_caja');
        Schema::dropIfExists('citas');
        Schema::dropIfExists('historial_precios_servicio');
        Schema::dropIfExists('precio_servicios');
        Schema::dropIfExists('empleado_servicio');
        Schema::dropIfExists('servicios');
        Schema::dropIfExists('categorias_servicio');
        Schema::dropIfExists('empleado_sucursal');
        Schema::dropIfExists('empleados');
        Schema::dropIfExists('perfiles_cliente');
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('sucursal_user');
        Schema::dropIfExists('configuraciones_sucursal');
        Schema::dropIfExists('sucursales');
    }
};
