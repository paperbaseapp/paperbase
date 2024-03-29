<?php

use App\Models\Document;
use App\Models\Library;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table
                ->foreignIdFor(Library::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('path');
            $table->string('last_hash');
            $table->dateTime('last_mtime');
            $table->string('title')->nullable();
            $table->string('ocr_status')->default(Document::OCR_PENDING);
            $table->boolean('needs_sync')->default(false);
            $table->dateTime('trashed_at')->nullable();

            $table->unique(['library_id', 'path']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('documents');
    }
}
