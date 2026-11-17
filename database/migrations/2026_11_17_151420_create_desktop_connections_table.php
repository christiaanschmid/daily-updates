<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A desktop connection is one "Move to cloud" from the Mac app: the app
     * registers it with a secret only it knows, the user approves it in their
     * browser, then the app uploads its logs and signs the user in once.
     */
    public function up(): void
    {
        Schema::create('desktop_connections', function (Blueprint $table) {
            $table->id();
            // Goes in the approval link, so it's not enough on its own to do anything.
            $table->string('code', 64)->unique();
            // A hash of the secret the Mac app keeps; every API call must prove it.
            $table->string('secret_hash', 64);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('login_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('desktop_connections');
    }
};
