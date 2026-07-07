<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tiny cross-runtime mailbox: the page drops a request here, the Node
     * sidecar picks it up on its next poll, runs it, and writes back a result.
     */
    public function up(): void
    {
        Schema::create('node_demo_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status')->default('pending');
            $table->json('result')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('node_demo_requests');
    }
};
