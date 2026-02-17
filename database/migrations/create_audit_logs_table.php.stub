<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('auditable_type');
            $table->string('auditable_id');

            $table->string('event');

            $table->string('actor_id')->nullable();
            $table->string('actor_type')->nullable();

            $table->json('diff')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->string('url')->nullable();
            $table->string('method')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_id', 'actor_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
