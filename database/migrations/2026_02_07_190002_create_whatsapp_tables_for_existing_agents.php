<?php

use App\Models\Agent;
use App\Observers\AgentObserver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Agent::all()->each(function (Agent $agent) {
            $tableName = AgentObserver::getTableName($agent->id);

            if (Schema::hasTable($tableName)) {
                return;
            }

            Schema::create($tableName, function ($table) {
                $table->id();
                $table->string('session_id', 255);
                $table->json('message');
                $table->timestamps();
            });
        });
    }

    public function down(): void
    {
        Agent::all()->each(function (Agent $agent) {
            Schema::dropIfExists(AgentObserver::getTableName($agent->id));
        });
    }
};
