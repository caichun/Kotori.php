<?php
/**
 * Kotori.php
 *
 * A Tiny Model-View-Controller PHP Framework
 *
 * This content is released under the Apache 2 License
 *
 * Copyright (c) 2015-2017 Kotori Technology. All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Handle Class
 *
 * @package     Kotori
 * @subpackage  Core
 * @author      Kokororin
 * @link        https://kotori.love
 */
namespace Kotori\Core;

use Kotori\Debug\Log;
use Kotori\Http\Request;
use Kotori\Http\Response;
use Kotori\Http\Route;
use WyriHaximus\HtmlCompress\Factory as htmlParserFactory;

abstract class Handle
{
    /**
     * Error Array
     *
     * @var array
     */
    public static $errors = array();

    /**
     * General Error Page
     *
     * Takes an error message as input
     * and displays it using the specified template.
     *
     * @param string $message Error Message
     * @param int $code HTTP Header code
     *
     * @return void
     */
    public static function halt($message, $code = 404)
    {
        Response::getSoul()->setStatus($code);
        if (Config::getSoul()->APP_DEBUG == false) {
            $message = '404 Not Found.';
        }
        $tplPath = Config::getSoul()->ERROR_TPL;

        if ($tplPath == null || !Helper::isFile(Config::getSoul()->APP_FULL_PATH . '/views/' . $tplPath . '.html')) {
            $tpl = '<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <title>Kotori.php 500 Internal Error</title>
  <meta name="robots" content="NONE,NOARCHIVE">
  <style type="text/css">
    html * { padding:0; margin:0; }
    body * { padding:10px 20px; }
    body * * { padding:0; }
    body { font:small sans-serif; background:#eee; }
    body>div { border-bottom:1px solid #ddd; }
    h1 { font-weight:normal; margin-bottom:.4em; }
    h1 span { font-size:60%; color:#666; font-weight:normal; }
    table { border:none; border-collapse: collapse; width:100%; }
    td, th { vertical-align:top; padding:2px 3px; }
    th { width:12em; text-align:right; color:#666; padding-right:.5em; }
    #info { background:#f6f6f6; }
    #info p {font-size: 16px}
    #summary { background: #ffc; }
    #explanation { background:#eee; border-bottom: 0px none; }
  </style>
</head>
<body>
  <div id="summary">
    <h1>Kotori.php Internal Error <span>(500)</span></h1>
    <table class="meta">
      <tr>
        <th>Request Method:</th>
        <td>' . strtoupper($_SERVER['REQUEST_METHOD']) . '</td>
      </tr>
      <tr>
        <th>Request URL:</th>
        <td>' . Request::getSoul()->getBaseUrl() . ltrim($_SERVER['REQUEST_URI'], '/') . '</td>
      </tr>

    </table>
  </div>
  <div id="info">
      ' . $message . '
  </div>

  <div id="explanation">
    <p>
      You\'re seeing this error because you have <code>APP_DEBUG = True</code> in
      your index.php file. Change that to <code>False</code>, and Kotori.php
      will display a standard 404 page.
    </p>
  </div>
</body>
</html>';
        } else {
            $tpl = file_get_contents(Config::getSoul()->APP_FULL_PATH . '/views/' . $tplPath . '.html');
        }

        $tpl = str_replace('{$message}', $message, $tpl);
        $htmlParser = htmlParserFactory::construct();
        $tpl = $htmlParser->compress($tpl);
        exit($tpl);
    }

    /**
     * Error Handler
     *
     * This function lets us invoke the exception class and
     * display errors using the standard error template located
     * in app/views/Public/error.html
     * This function will send the error page directly to the
     * browser and exit.
     *
     * @param string $errno Error number
     * @param int $errstr Error string
     * @param string $errfile Error filepath
     * @param int $errline Error line
     * @return void
     */
    public static function error($errno, $errstr, $errfile, $errline)
    {
        $type = self::getErrorType($errno);
        $text = self::renderErrorText($type, $errstr, $errline, $errfile);
        $txt = self::renderLogBody($type, $errstr, $errline, $errfile);
        array_push(self::$errors, $text);
        Log::normal($txt);
    }

    /**
     * Exception Handler
     *
     * Sends uncaught exceptions to the logger and displays them
     * only if display_errors is On so that they don't show up in
     * production environments.
     *
     * @param Exception $exception The exception
     * @return void
     */
    public static function exception($exception)
    {
        $text = self::renderHaltBody(get_class($exception), $exception->getMessage(), $exception->getLine(), $exception->getFile());
        $txt = self::renderLogBody(get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine());
        Log::normal($txt);
        self::halt($text, 500);
    }

    /**
     * Shutdown Handler
     *
     * This is the shutdown handler that is declared in framework.
     * The main reason we use this is to simulate
     * a complete custom exception handler.
     *
     * E_STRICT is purposively neglected because such events may have
     * been caught. Duplication or none? None is preferred for now.
     *
     * @return  void
     */
    public static function end()
    {
        $last_error = error_get_last();
        if (isset($last_error) &&
            ($last_error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING))) {
            $type = self::getErrorType($last_error['type']);
            $text = self::renderHaltBody($type, $last_error['message'], $last_error['line'], $last_error['file']);

            $txt = self::renderLogBody($type, $last_error['message'], $last_error['file'], $last_error['line']);

            Log::normal($txt);
            self::halt($text, 500);
        }

    }

    /**
     * convert PHP ERROR to error detail
     *
     * @param  int $errno
     * @return string
     */
    protected static function getErrorType($errno)
    {
        switch ($errno) {
            case E_ERROR:
                $errtype = 'A fatal error that causes script termination.';
                break;
            case E_WARNING:
                $errtype = 'Run-time warning that does not cause script termination.';
                break;
            case E_PARSE:
                $errtype = 'Compile time parse error.';
                break;
            case E_NOTICE:
                $errtype = 'Run time notice caused due to error in code.';
                break;
            case E_CORE_ERROR:
                $errtype = 'Fatal errors that occur during PHP\'s initial startup (installation).';
                break;
            case E_CORE_WARNING:
                $errtype = 'Warnings that occur during PHP\'s initial startup.';
                break;
            case E_COMPILE_ERROR:
                $errtype = 'Fatal compile-time errors indication problem with script.';
                break;
            case E_COMPILE_WARNING:
                $errtype = 'Non-Fatal Run Time Warning generated by Zend Engine.';
                break;
            case E_USER_ERROR:
                $errtype = 'User-generated error message.';
                break;
            case E_USER_WARNING:
                $errtype = 'User-generated warning message.';
                break;
            case E_USER_NOTICE:
                $errtype = 'User-generated notice message.';
                break;
            case E_STRICT:
                $errtype = 'Run-time notices.';
                break;
            case E_RECOVERABLE_ERROR:
                $errtype = 'Catchable fatal error indicating a dangerous error.';
                break;
            default:
                $errtype = 'Unknown';
                break;
        }
        return $errtype;
    }

    /**
     * render Halt Body
     *
     * @param string $type Error type
     * @param int $message Error string
     * @param string $file Error filepath
     * @param int $errline Error line
     * @return string
     */
    protected static function renderHaltBody($type, $message, $line, $file)
    {
        $text = '<p><strong>Error Type: </strong>' . $type . '</p>' . '<p><strong>Info: </strong>' . $message . '</p>' . '<p><strong>Line: </strong>' . $line . '</p>' . '<p><strong>File: </strong>' . $file . '</p>';
        $source = self::getSourceCode($file, $line);

        $sourceLen = strlen(strval(count($source['source']) + $source['first']));
        $padding = 40 + ($sourceLen - 1) * 8;
        if (!empty($source)) {
            $text .= '<link href="' . Route::getSoul()->url('kotori-php-system-route/highlight-github.css') . '" rel="stylesheet" />';
            $text .= '<style>
.source-code {
    padding: 6px;
    border: 1px solid #ddd;
    background: #f9f9f9;
    overflow-x: auto;
}

.source-code pre {
    margin: 0;
}

.source-code pre ol {
    margin: 0;
    color: #4288ce;
    display: inline-block;
    min-width: 100%;
    box-sizing: border-box;
    font-size: 14px;
    font-family: Menlo,Monaco,Consolas,"Courier New",monospace;
    padding-left: ' . $padding . 'px;
}

.source-code pre li {
    border-left: 1px solid #ddd;
    height: 18px;
    line-height: 18px;
}

.source-code pre code {
    color: #333;
    height: 100%;
    display: inline-block;
    border-left: 1px solid #fff;
    font-size: 14px;
    font-family: Menlo,Monaco,Consolas,"Courier New",monospace;
}

.source-code pre li.line-error {
    background: #f8cbcb;
}

</style>';
            $text .= '<p><strong>Source Code: </strong></p><div class="source-code">
<pre id="code-block" class="lang-php">
    <ol start="' . $source['first'] . '">';
            foreach ($source['source'] as $key => $value) {
                $currentLine = $key + $source['first'];
                $extendClass = ($currentLine == $line) ? ' line-error' : '';
                $text .= '<li class="line-' . $currentLine . $extendClass . '"><code>' . htmlentities($value) . '</code></li>';
            }
            $text .= '</ol></pre></div>';
            $text .= '<script type="text/javascript" src="' . Route::getSoul()->url('kotori-php-system-route/highlight.js') . '"></script>';
            $text .= '<script type="text/javascript">
window.onload = function() {
    hljs.highlightBlock(document.getElementById("code-block"));
};
</script>';

        }
        return $text;
    }

    /**
     * render log body
     *
     * @param string $type Error type
     * @param int $message Error string
     * @param string $file Error filepath
     * @param int $line Error line
     * @return string
     */
    protected static function renderLogBody($type, $message, $line, $file)
    {
        return '[Type] ' . $type . ' [Info] ' . $message . ' [Line] ' . $line . ' [File] ' . $file;
    }

    /**
     * render errors display in trace
     *
     * @param string $type Error type
     * @param int $message Error string
     * @param string $file Error filepath
     * @param int $line Error line
     * @return string
     */
    protected static function renderErrorText($type, $message, $line, $file)
    {
        return $message . ' in ' . $file . ' on line ' . $line;
    }

    /**
     * get source code from file
     *
     * @param  string $file Error filepath
     * @param  int $line Error line
     * @return array
     */
    protected static function getSourceCode($file, $line)
    {
        $first = ($line - 9 > 0) ? $line - 9 : 1;

        try {
            $contents = file($file);
            $source = array(
                'first' => $first,
                'source' => array_slice($contents, $first - 1, 19),
            );
        } catch (\Exception $e) {
            $source = array();
        }
        return $source;
    }

}
