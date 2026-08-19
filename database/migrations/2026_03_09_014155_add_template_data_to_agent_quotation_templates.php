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
        Schema::table('agent_quotation_templates', function (Blueprint $table) {
            $table->json('template_data')->nullable()->after('template_html')
                ->comment('Páginas con contenido e imágenes posicionables: { pages: [{ content, images: [{ url, x, y, w, h }] }] }');
        });
    }

    public function down(): void
    {
        Schema::table('agent_quotation_templates', function (Blueprint $table) {
            $table->dropColumn('template_data');
        });
    }
};
