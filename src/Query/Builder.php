<?php

namespace Sofa\Eloquence\Query;

use Sofa\Eloquence\Subquery;

class Builder extends \Illuminate\Database\Query\Builder
{
    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return float|int
     */
    public function aggregate($function, $columns = ['*'])
    {
        $this->aggregate = compact('function', 'columns');

        $previousColumns = $this->columns;

        if (!$this->from instanceof Subquery) {
            // We will also back up the select bindings since the select clause will be
            // removed when performing the aggregate function. Once the query is run
            // we will add the bindings back onto this query so they can get used.
            $previousSelectBindings = $this->bindings['select'];

            $this->bindings['select'] = [];
        }

        $results = $this->get($columns);

        // Once we have executed the query, we will reset the aggregate property so
        // that more select queries can be executed against the database without
        // the aggregate value getting in the way when the grammar builds it.
        $this->aggregate = null;

        $this->columns = $previousColumns;

        if (!$this->from instanceof Subquery) {
            $this->bindings['select'] = $previousSelectBindings;
        }

        if (isset($results[0])) {
            $result = array_change_key_case((array) $results[0]);

            return $result['aggregate'];
        }
    }

    /**
     * Backup some fields for the pagination count.
     *
     * @return void
     */
    protected function backupFieldsForCount()
    {
        foreach (['orders', 'limit', 'offset', 'columns'] as $field) {
            $this->backups[$field] = $this->{$field};

            $this->{$field} = null;
        }

        $bindings = ($this->from instanceof Subquery) ? ['order'] : ['order', 'select'];

        foreach ($bindings as $key) {
            $this->bindingBackups[$key] = $this->bindings[$key];

            $this->bindings[$key] = [];
        }
    }

    /**
     * Restore some fields after the pagination count.
     *
     * @return void
     */
    protected function restoreFieldsForCount()
    {
        foreach ($this->backups as $field => $value) {
            $this->{$field} = $value;
        }

        foreach ($this->bindingBackups as $key => $value) {
            $this->bindings[$key] = $value;
        }

        $this->backups = $this->bindingBackups = [];
    }
}
