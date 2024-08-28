<?php
declare(strict_types=1);

namespace Flux\Logger;

class Backtrace
{

    public static function shiftLineFunction(array $trace = null, bool $removeActual = true): array
    {
        if (empty($trace))
            return array();

        $ret = array();
        $max = count($trace);

        // be worned, 'file' means which file calls the next step. 'class' is the next step being called.
        for ($i = $max - 1; $i >= 0; $i--) {
            $ret[$i + 1]['file'] = $trace[$i]['file'];
            $ret[$i + 1]['line'] = $trace[$i]['line'];

            if (!(($i == 0) && $removeActual)) {
                if (!empty($trace[$i]['class']))
                    $ret[$i]['class'] = $trace[$i]['class'];
                if (!empty($trace[$i]['function']))
                    $ret[$i]['function'] = $trace[$i]['function'] . '()';
                if (!empty($trace[$i]['type']))
                    $ret[$i]['type'] = $trace[$i]['type'];
            }
        }

        $ret[$max]['function'] = '{main}';

        $rret = array();
        foreach ($ret as $r)
            $rret[] = $r;

        return $rret;

    }

    public static function formatCallable(array $trace = null): string
    {
        if (empty($trace))
            return '';

        if (!empty($trace['class']))
            $class = $trace['class'];
        else
            $class = '';

        if (!empty($trace['function']))
            $func = $trace['function'];
        else
            $func = '';

        if (!empty($trace['type']))
            $type = $trace['type'];
        else
            $type = '-?-';

        if (empty($class))
            $ret = $func;
        else
            $ret = $class . $type . $func;

        if (empty($ret))
            $ret = '{main}';

        return $ret;

    }

    public static function formatFile(array $trace = null): string
    {

        if (empty($trace))
            return '';

        if (!empty($trace['file']))
            $file = $trace['file'];
        else
            $file = '';

        if (!empty($trace['line']))
            $line = '(' . $trace['line'] . ')';
        else
            $line = '()';

        return $file . $line;

    }

    /**
     * Creates a trace-display-array from a trace array
     */
    public static function Extract(array $trace = null): array
    {

        if (empty($trace))
            return array();

        $tarr = array();

        $i = count($trace) - 1;
        foreach ($trace as $row) {
            $idx = 'file' . $i;
            $tarr[$idx] = self::formatFile($row);

            $idx = 'trace' . $i;
            $caller = self::formatCallable($row);
            if (!empty($caller))
                $tarr[$idx] = $caller;

            $i--;
        }

        return $tarr;
    }

    /**
     * Creates a string from a trace array
     */
    public static function asString(array $tarr = null): string
    {

        if (empty($trace))
            return '';

        if (isset($tarr['src0']))
            $file = $tarr['src0'];
        else
            $file = '';

        if (isset($tarr['call2']))
            $callercaller = $tarr['call2'];
        else
            $callercaller = '';

        if (isset($tarr['call1']))
            $func = $tarr['call1'];
        else
            $func = '';

        return $file . $callercaller . ' ' . $func;

    }
}
