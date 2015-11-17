<?php

namespace Sofa\Eloquence\Metable;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * @codeCoverageIgnore
 */
class CreateMetaAttributesTable extends Migration
{
    /**
     * Meta attributes table name.
     *
     * @todo allow table name customization via config
     *
     * @var string
     */
    protected $table = 'meta_attributes';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        \Schema::create($this->table, function (Blueprint $table) {
            $table->increments('meta_id');
            $table->string('meta_key');
            $table->longText('meta_value');
            $table->string('meta_type')->default('string');
            $table->morphs('metable');

            $table->index('meta_key');

            // Laravel doesn't handle index length, so we need raw statement for this one
            \Schema::getConnection()->statement(
                'create index meta_attributes_index_value on meta_attributes (meta_key, meta_value(20))'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        \Schema::drop($this->table);
    }
}
