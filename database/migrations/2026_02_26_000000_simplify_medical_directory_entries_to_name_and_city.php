<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurer_medical_directory_entries', function (Blueprint $table) {
            $table->dropColumn(['address', 'website', 'email', 'phone_numbers', 'specialty']);
        });
    }

    public function down(): void
    {
        Schema::table('insurer_medical_directory_entries', function (Blueprint $table) {
            $table->string('address')->nullable()->after('name');
            $table->string('website')->nullable()->after('address');
            $table->string('email')->nullable()->after('website');
            $table->json('phone_numbers')->nullable()->after('email');
            $table->string('specialty')->nullable()->after('city');
        });
    }
};
