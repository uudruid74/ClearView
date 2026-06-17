<?php

namespace ClearView;

require_once("utility/AnsiColors.php");

use ClearView\AnsiColors as Ansi;
use ClearView\Facet;
use ClearView\Exception;
use ClearView\Mosaic;
use ClearView\Pane;
use ProcessWire;
use ProcessWire\WireException;

class Exception extends WireException
{
    private static $tracemode = ['ERROR'];      // default

    /**
     * Constructor that processes templates and passes the message to error().
     *
     * @param string $message The error message with potential templates.
     * @param int $code The error code (optional, default 0).
     * @param \Throwable|null $previous The previous throwable (optional, default null).
     */
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        $exception = self::error($message, $this);
        parent::__construct($exception->getMessage(), $code, $previous);
    }

    /**
     * Gets/Sets the tracemode flags.
     * When you have problem code, wrap it in ..
     *      $flags = Exception::tracemode(['ALL']);
     *      ... your code here ...
     *      Exception::tracemode($flags);
     *
     * @param array $flags The new trace flags.
     * @return array of previous trace flags.
     */
    public static function tracemode(array $flags): array
    {
        $oldtracemode = self::$tracemode;
        if (isset($flags)) {
            self::$tracemode = $flags;
        }
        return $oldtracemode;
    }

    /**
     * Outputs a debug message, buffering for JS or as HTML comment.
     *
     * @param string $tag The debug tag.
     * @param string|null $msg The debug message.
     */
    public static function debug($tag, $msg = null, $depth = 1): void
    {
        if (!isset($msg)) {
            $msg = $tag;
            $tag = "TRACE";
        }
        if (Pane::inTesting()) {
            fwrite(STDERR, $msg . "\n");
            return;
        }
        $processedMsg = Facet::_($msg);
        self::output($processedMsg, $tag, $depth);
    }

    /**
     * Creates an error exception with call site information.
     *
     * @param string $msg The error message.
     * @param self|null $e Optional existing exception to extract call site info.
     * @return self The exception instance to throw (if $e is null).
     */
    public static function error($msg, ?self $e = null): self
    {
        if (Pane::inTesting()) {
            fwrite(STDERR, $msg . "\n$e\n\n");
        }
        $logMsg = Facet::_($msg);
        $file = $e ? basename($e->getFile()) : null;
        $line = $e ? $e->getLine() : null;

        if (!$file || !$line) {
            $caller = self::getCallerInfo();
            $file = $caller['file'];
            $line = $caller['line'];
        }

        self::output($logMsg, "ERROR");

        if ($e === null) {
            return new self("Fatal Error: $logMsg");
        }
        // If $e is provided, update its message and return it
        $e->message = "Fatal Error: $logMsg";
        return $e;
    }

    /**
     * Outputs a debug comment to the pane-scoped debug layer.
     *
     * @param string $msg The comment message.
     */
    public static function outputComment($msg): void
    {
        if (Pane::inTesting()) {
            fwrite(STDERR, $msg . "\n");
            return;
        }
        Mosaic::index('ClearView', 'ClearView')?->debugLayer($msg);
    }

    /**
     * Outputs a header for debugging, optionally setting tracemode.
     *
     * @param string $template we were called with
     * @param array|null $tracemodeFlags Optional trace mode flags to set.
     */
    public static function outheader(string $template, ?array $tracemodeFlags = null): void
    {
        if ($tracemodeFlags !== null) {
            self::tracemode($tracemodeFlags);
        }
        $url  = Mosaic::getVar("Input::url");
        $name = Mosaic::getVar("Page::name");
        $date = ProcessWire\datetime()->date('Y/m/d h:i:s');
        Mosaic::index('ClearView', 'ClearView')?->debugLayer("========");
        Mosaic::index('ClearView', 'ClearView')?->debugLayer("-- {$date}: {$url} [ template: $template, inlay: {$name}]");
    }

    /**
     * Should I output this tag?
     * @param string $tag The tag to test
     * @return bool if its in tracemode
     */
    public static function traceTag ($tag='ERROR')
    {
        return ($tag === 'ERROR') ||
            (in_array('ALL', self::$tracemode)) ||
            (in_array($tag, self::$tracemode));
    }

    /**
     * Output Formatter
     *
     * @param string $msg The message.
     * @param string $tag The tag.
     * @param int $depth How deep is the caller?
     * @return string The colorized message.
     */
    public static function output($msg, $tag = 'TRACE', $depth = 1)
    {
        if (!isset($msg) || !self::traceTag($tag)) {
            return;
        }
        $backtrace = debug_backtrace() ?? 'null';

        // set colors
        if (!Config::FAIL_MODE) {
            $off   = Ansi::color('off');
            $color = Ansi::color($tag);
            $error = Ansi::color('ERROR');
            $bold  = Ansi::color('bold');
        } else {
            $off   = $color = $bold = $error = '';
        }

        // format info
        if (isset($backtrace[$depth])) {
            $msg = addslashes($msg);
            $fulldepth = count($backtrace);
            $caller = $backtrace[$depth];
            $prev = $backtrace[$depth + 1];
            $functionName = $prev['function'];
            $className = addslashes($prev['class'] ?? '');
            $file = basename($caller['file'] ?? '');
            $line = $caller['line'] ?? '';
            $tagfmt = sprintf(
                "[%-24s %03d] [%-5s]",
                str_pad("{$file}:{$line}", 24, '.', STR_PAD_RIGHT),
                $fulldepth,
                str_pad($tag, 5, '-', STR_PAD_RIGHT)
            );
            if ($fulldepth < Config::STACK_LIMIT) {
                $msg = "{$color}{$bold}{$tagfmt}{$off} {$className}:{$color}{$functionName}{$off} " .
                        "{$color}{$msg}{$off}";
            } else {
                Mosaic::index('ClearView', 'ClearView')?->dumpOOBdata();
                self::backtrace();
                Mosaic::dumpEverything();
                die("Recursion Detected: Exceeded Config::STACK_LIMIT");
            }
        } else {
            $msg = "{$error}unknown caller{$off} {$color}{$msg}{$off}";
            Mosaic::dumpEverything();
            self::backtrace();
        }

        // final output
        if (Config::FAIL_MODE) {
            self::outputComment($msg);
        } else {
            Mosaic::index('ClearView', 'ClearView')?->debugLayer($msg);
        }
    }

    /**
     * Output a backtrace at any time as a string
     */
    public static function backtrace()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 13);
        array_shift($trace); // Remove the call to backtrace() itself
        $trace = array_slice($trace, -12); // Get the last 12 entries
        $output = [];
        foreach ($trace as $index => $call) {
            $file = $call['file'] ?? 'unknown';
            $line = $call['line'] ?? 'unknown';
            $function = $call['function'] ?? 'unknown';
            $class = $call['class'] ?? '';
            $type = $call['type'] ?? '';
            $args = array_map(function ($arg) {
                if (is_object($arg)) {
                    return get_class($arg);
                }
                if (is_array($arg)) {
                    return 'Array';
                }
                if (is_null($arg)) {
                    return 'null';
                }
                return (string)$arg;
            }, $call['args'] ?? []);
            $argString = implode(', ', $args);
            $output[] = "#$index $file($line): " . ($class ? "$class$type" : '') . "$function($argString)";
        }
        self::outputComment(
            "\n****** BACKTRACE ******\n" .
            implode("\n", $output) .
            "\n***********************\n"
        );
    }

    /**
     * Gets caller information from debug_backtrace.
     *
     * @return array Caller file and line.
     */
    private static function getCallerInfo(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        if (isset($trace)) {
            $trace[1]['stackdepth'] = count($trace);
            $trace[1]['file'] = basename($trace[1]['file']);
        }
        return $trace[1] ?? ['file' => 'unknown', 'line' => 0];
    }
}
