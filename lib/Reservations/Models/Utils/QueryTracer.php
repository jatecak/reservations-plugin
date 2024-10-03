<?php

namespace Reservations\Models\Utils;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class QueryTracer implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($traces as $trace) {
            // Find the first non-vendor-dir file in the backtrace
            if (isset($trace['file']) && !str_contains($trace['file'], DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                $file = '"query.file" <> "' . $trace['file'] . '"';
                $line = '"query.line" <> "' . $trace['line'] . '"';

                return $builder->whereRaw($file)->whereRaw($line);
            }
        }
    }
}
