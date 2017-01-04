<?php
/***********************************************
* File      :   KLogUtil.php
* Project   :   KopanoDAV
* Descr     :   Utility class for logging.
*
* Created   :   27.12.2016
*
* Copyright 2016 Kopano b.v.
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License, version 3,
* as published by the Free Software Foundation.
*
* This software uses SabreDAV, an open source software distributed
* under three-clause BSD-license. Please see <http://sabre.io/dav/>
* for more information about SabreDAV.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* Consult LICENSE file for details
************************************************/

namespace Kopano\DAV;

class KLogUtil {

    /**
     * Logging Utility class
     *
     * @param Logger $logger
     * @return void
     */
    public function __construct($logger) {
        $this->logger = $logger;

        // keep an output puffer in case we do debug logging
        if ($this->logger->isDebugEnabled()) {
            ob_start();
        }

        // let KLogUtil handle error messages
        set_error_handler('\\Kopano\\DAV\\KLogUtil::ErrorHandler');
    }

    /**
     * The KopanoDav error handler.
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @param mixed $errcontext
     */
    function ErrorHandler($errno, $errstr, $errfile, $errline, $errcontext) {
        // this is from Z-Push but might be helpful in the future: https://wiki.z-hub.io/x/sIEa
        if (defined('LOG_ERROR_MASK')) $errno &= LOG_ERROR_MASK;

        switch ($errno) {
            case 0:
                // logging disabled by LOG_ERROR_MASK
                break;

            case E_DEPRECATED:
                // do not handle this message
                break;

            case E_NOTICE:
            case E_WARNING:
                $logger = \Logger::getLogger('error');
                $logger->warn("$errfile:$errline $errstr ($errno)");
                break;

            default:
                $bt = debug_backtrace();
                $logger = \Logger::getLogger('error');
                $logger->error("trace error: $errfile:$errline $errstr ($errno) - backtrace: ". (count($bt)-1) . " steps");
                for($i = 1, $bt_length = count($bt); $i < $bt_length; $i++) {
                    $file = $line = "unknown";
                    if (isset($bt[$i]['file'])) $file = $bt[$i]['file'];
                    if (isset($bt[$i]['line'])) $line = $bt[$i]['line'];
                    $logger->error("trace: $i:". $file . ":" . $line. " - " . ((isset($bt[$i]['class']))? $bt[$i]['class'] . $bt[$i]['type']:""). $bt[$i]['function']. "()");
                }
                break;
        }
    }

    /**
     * Returns a PSR-3 compatible logger.
     *
     * @access public
     * @return \Kopano\DAV\PSR3WrapperLogger
     */
    public function GetPSRLoggerInterface() {
        // TODO when using PHP 7 this could become an anonymous class
        return new PSR3LoggerWrapper($this->logger);
    }
}


/**
 * Wrapper to get a PSR-3 compatible interface out of an php4log logger.
 */
class PSR3LoggerWrapper implements \Psr\Log\LoggerInterface {
    /**
     * log4php
     *
     * @var Logger
     */
    private $logger;

    /**
     * Wraps a log4php logger into a PSR-3 compatible interface.
     *
     * @param Logger $logger
     * @return void
     */
    public function __construct($logger) {
        $this->logger = $logger;
    }

    /**
     * Emergency message, like system down.
     *
     * @param string $message
     * @param array $context
     *
     * @access public
     * @return null
     */
    public function emergency($message, array $context = array()) {
        $this->logger->fatal($this->interpret($message, $context));
    }

    /**
     * Immediate Action required.
     *
     * @param string $message
     * @param array $context
     *
     * @access public
     * @return null
     */
    public function alert($message, array $context = array()) {
        $this->logger->fatal($this->interpret($message, $context));
    }

    /**
     * Critical messages.
     *
     * @param string $message
     * @param array $context
     *
     * @access public
     * @return null
     */
    public function critical($message, array $context = array()) {
        $this->logger->fatal($this->interpret($message, $context));
    }

    /**
     * Errors happening on runtime that need to be logged.
     *
     * @param string $message
     * @param array $context
     *
     * @access public
     * @return null
     */
    public function error($message, array $context = array()) {
        $this->logger->error($this->interpret($message, $context));
    }

    /**
     * Warnings (not necesserily errors).
     *
     * @param string $message
     * @param array $context
     *
     * @access public
     * @return null
     */
    public function warning($message, array $context = array()) {
        $this->logger->warn($this->interpret($message, $context));
    }
    /**
     * Significant events (still normal).
     *
     * @param string $message
     * @param array $context
     *
     * @access public
     * @return null
     */
    public function notice($message, array $context = array()) {
        $this->logger->info($this->interpret($message, $context));
    }

    /**
     * Events with informational value.
     *
     * @param string $message
     * @param array $context
     *
     * @access public
     * @return null
     */
    public function info($message, array $context = array()) {
        $this->logger->info($this->interpret($message, $context));
    }

    /**
     * Debug data.
     *
     * @param string $message
     * @param array $context
     *
     * @access public
     * @return null
     */
    public function debug($message, array $context = array()) {
        $this->logger->debug($this->interpret($message, $context));
    }

    /**
     * Logs at a loglevel.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @access public
     * @return null
     */
    public function log($level, $message, array $context = array()) {
        throw new \Exception('Please call specific logging message');
    }

    /**
     * Interprets context values as string like in the PSR-3 example implementation.
     *
     * @param string $message
     * @param array $context
     *
     * @access protected
     * @return string
     */
    protected function interpret($message, array $context = array()) {
        $replace = array();
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }

        return strtr($message, $replace);
    }
}