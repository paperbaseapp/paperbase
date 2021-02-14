<?php

use JetBrains\PhpStorm\Pure;

if (!function_exists('join_path')) {
    function join_path(string $a, string $b, bool $safe = true): string
    {
        if ($safe) {
            $a = preg_replace('/\.+/', '.', $a);
            $b = preg_replace('/\.+/', '.', $b);
        }
        return join('/', [rtrim($a, '/'), rtrim($b, '/')]);
    }
}


if (!function_exists('canonicalize_path')) {
    #[Pure]
    function canonicalize_path(string $path): string
    {
        // as per RFC 3986
        // @see http://tools.ietf.org/html/rfc3986#section-5.2.4
        // @see https://stackoverflow.com/a/21424232/3691378

        // 1.  The input buffer is initialized with the now-appended path
        //     components and the output buffer is initialized to the empty
        //     string.
        $output = '';

        // 2.  While the input buffer is not empty, loop as follows:
        while ($path !== '') {
            // A.  If the input buffer begins with a prefix of "`../`" or "`./`",
            //     then remove that prefix from the input buffer; otherwise,
            if (
                ($prefix = substr($path, 0, 3)) == '../' ||
                ($prefix = substr($path, 0, 2)) == './'
            ) {
                $path = substr($path, strlen($prefix));
            } else

                // B.  if the input buffer begins with a prefix of "`/./`" or "`/.`",
                //     where "`.`" is a complete path segment, then replace that
                //     prefix with "`/`" in the input buffer; otherwise,
                if (
                    ($prefix = substr($path, 0, 3)) == '/./' ||
                    ($prefix = $path) == '/.'
                ) {
                    $path = '/' . substr($path, strlen($prefix));
                } else

                    // C.  if the input buffer begins with a prefix of "/../" or "/..",
                    //     where "`..`" is a complete path segment, then replace that
                    //     prefix with "`/`" in the input buffer and remove the last
                    //     segment and its preceding "/" (if any) from the output
                    //     buffer; otherwise,
                    if (
                        ($prefix = substr($path, 0, 4)) == '/../' ||
                        ($prefix = $path) == '/..'
                    ) {
                        $path = '/' . substr($path, strlen($prefix));
                        $output = substr($output, 0, strrpos($output, '/'));
                    } else

                        // D.  if the input buffer consists only of "." or "..", then remove
                        //     that from the input buffer; otherwise,
                        if ($path == '.' || $path == '..') {
                            $path = '';
                        } else

                            // E.  move the first path segment in the input buffer to the end of
                            //     the output buffer, including the initial "/" character (if
                            //     any) and any subsequent characters up to, but not including,
                            //     the next "/" character or the end of the input buffer.
                        {
                            $pos = strpos($path, '/');
                            if ($pos === 0) $pos = strpos($path, '/', $pos + 1);
                            if ($pos === false) $pos = strlen($path);
                            $output .= substr($path, 0, $pos);
                            $path = (string)substr($path, $pos);
                        }
        }

        // 3.  Finally, the output buffer is returned as the result of remove_dot_segments.
        return $output;
    }
}
