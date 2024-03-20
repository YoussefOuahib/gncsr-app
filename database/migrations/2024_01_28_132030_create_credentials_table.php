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
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->constrained();
            $table->string('tak_url');
            $table->string('tak_login');
            $table->string('tak_password');
            $table->string('sharepoint_url');
            $table->string('sharepoint_client_id');
            $table->string('sharepoint_client_secret');
            $table->string('sharepoint_tenant_id');
            $table->string('dynamics_url');
            $table->string('dynamics_client_id');
            $table->string('dynamics_client_secret');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
