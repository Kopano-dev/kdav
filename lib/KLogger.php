<?php
/***********************************************
* File      :   KLogger.php
* Project   :   KopanoDAV
* Descr     :   A wrapper for log4php Logger.
*
* Created   :   29.12.2016
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

/**
 * KLogger: wraps the php4log logger to log with better performance.
 *
 * We tried to extend \Logger but this isn't possible due to
 * the static methods depending on Logger::getHierarchy() and
 * that it cannot be overwritten (due to the use of self in Logger).
 *
 * If you want other methods of Logger please add a wrapper method to this class.
 *
 */
class KLogger {
    static protected $listOfLoggers = array();
    protected $logger;

    /**
     * Configures log4php.
     *
     * This method needs to be called before the first logging event has
     * occured. If this method is not called before then the default
     * configuration will be used.
     *
     * @param string|array $configuration Either a path to the configuration
     *   file, or a configuration array.
     *
     * @param string|LoggerConfigurator $configurator A custom
     * configurator class: either a class name (string), or an object which
     * implements the LoggerConfigurator interface. If left empty, the default
     * configurator implementation will be used.
     */
    public static function configure($configuration = null, $configurator = null) {
        \Logger::configure($configuration, $configurator);
    }

    /**
     * Returns a KLogger by name. If it does not exist, it will be created.
     *
     * @param string $name The logger name
     * @return Logger
     */
    public static function GetLogger($class) {
        if (!isset($listOfLoggers[$class])) {
            $listOfLoggers[$class] = new KLogger(static::GetClassnameOnly($class));
        }
        return $listOfLoggers[$class];
    }

    /**
     * Cuts of the namespace and returns just the classname.
     *
     * @param string $namespaceWithClass
     * @return string
     */
    protected static function GetClassnameOnly($namespaceWithClass) {
        if (strpos($namespaceWithClass, '\\') == false) {
            return $namespaceWithClass;
        }
        return substr(strrchr($namespaceWithClass, '\\'), 1);
    }

    /**
     * Constructor
     */
    public function __construct($name) {
        $this->logger = \Logger::getLogger($name);
    }

    /**
     * Runs the arguments through sprintf() and sends it to the logger.
     *
     * @param \LoggerLevel  $level
     * @param array         $args
     * @param string        $suffix    an optional suffix that is appended to the message
     */
    protected function writeLog($level, $args, $suffix = '') {
        $outArgs = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $args[] = print_r($arg, true);
            }
            $args[] = $arg;
        }
        // call sprintf() with the arguments
        $message = call_user_func_array('sprintf', $args);
        // prepend class+method and log the message
        $this->logger->log($level, $this->getCaller(2) . $message . $suffix, null);
    }

    /**
     * Verifies if the dynamic amount of logging arguments matches the amount of variables (%) in the message.
     *
     * @param array $arguments
     * @return boolean
     */
    protected function verifyLogSyntax($arguments) {
        $count = count($arguments);
        $quoted_procent = substr_count($arguments[0], "%%");
        $t = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        if ($count == 0) {
            $this->logger->error(sprintf("No arguments in %s->%s() logging to '%s' in %s:%d", static::GetClassnameOnly($t[2]['class']), $t[2]['function'], $t[1]['function'], $t[2]['file'], $t[2]['line']));
            return false;
        }
        if ((substr_count($arguments[0], "%") - $quoted_procent*2) !== $count-1) {
            $this->logger->error(sprintf("Wrong number of arguments in %s->%s() logging to '%s' in %s:%d", static::GetClassnameOnly($t[2]['class']), $t[2]['function'], $t[1]['function'], $t[2]['file'], $t[2]['line']));
            return false;
        }
        return true;
    }

    /**
     * Returns a string in the form of "Class->Method(): " or "file:line" if requested.
     *
     * @param number    $level      the level you want the info from, default 1
     * @param boolean   $fileline   returns "file:line" if set to true.
     * @return string
     */
    protected function getCaller($level = 1, $fileline = false) {
        $wlevel = $level+1;
        $t = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $wlevel+1);
        if (isset( $t[$wlevel]['function'])) {
            if ($fileline) {
                return $t[$wlevel]['file']. ":" . $t[$wlevel]['line'];
            }
            return $t[$wlevel]['function'] .'->'. $t[$wlevel]['function']. '(): ';
        }
        return '';
    }

    /**
     * Wrapper of the \Logger class
     */

    /**
     * Log a message object with the TRACE level.
     * It has the same footprint as sprintf(), but arguments are only processed
     * if the loglevel is activated.
     *
     * @param mixed $message message
     * @param mixed ...params
     *
     * @access public
     * @return void
     */
    public function trace() {
        if (DEVELOPER_MODE) {
            if (!$this->verifyLogSyntax(func_get_args())) {
                return;
            }
        }
        if ($this->isTraceEnabled()) {
            $this->writeLog(\LoggerLevel::getLevelTrace(), func_get_args());
        }
    }

    /**
     * Log a message object with the DEBUG level.
     * It has the same footprint as sprintf(), but arguments are only processed
     * if the loglevel is activated.
     *
     * @param mixed $message message
     * @param mixed ...params
     *
     * @access public
     * @return void
     */
    public function debug() {
        if (DEVELOPER_MODE) {
            if (!$this->verifyLogSyntax(func_get_args())) {
                return;
            }
        }
        if ($this->isDebugEnabled()) {
            $this->writeLog(\LoggerLevel::getLevelDebug(), func_get_args());
        }
    }

    /**
     * Log a message object with the INFO level.
     * It has the same footprint as sprintf(), but arguments are only processed
     * if the loglevel is activated.
     *
     * @param mixed $message message
     * @param mixed ...params
     *
     * @access public
     * @return void
     */
    public function info() {
        if (DEVELOPER_MODE) {
            if (!$this->verifyLogSyntax(func_get_args())) {
                return;
            }
        }
        if ($this->isInfoEnabled()) {
            $this->writeLog(\LoggerLevel::getLevelInfo(), func_get_args());
        }
    }

    /**
     * Log a message object with the WARN level.
     * It has the same footprint as sprintf(), but arguments are only processed
     * if the loglevel is activated.
     *
     * @param mixed $message message
     * @param mixed ...params
     *
     * @access public
     * @return void
     */
    public function warn() {
        if (DEVELOPER_MODE) {
            if (!$this->verifyLogSyntax(func_get_args())) {
                return;
            }
        }
        if ($this->isWarnEnabled()) {
            $this->writeLog(\LoggerLevel::getLevelWarn(), func_get_args(), ' - '. $this->getCaller(1, true));
        }
    }

    /**
     * Log a message object with the ERROR level.
     * It has the same footprint as sprintf(), but arguments are only processed
     * if the loglevel is activated.
     *
     * @param mixed $message message
     * @param mixed ...params
     *
     * @access public
     * @return void
     */
    public function error() {
        if (DEVELOPER_MODE) {
            if (!$this->verifyLogSyntax(func_get_args())) {
                return;
            }
        }
        if ($this->isErrorEnabled()) {
            $this->writeLog(\LoggerLevel::getLevelError(), func_get_args(), ' - '. $this->getCaller(1, true));
        }
    }

    /**
     * Log a message object with the WARN level.
     * It has the same footprint as sprintf(), but arguments are only processed
     * if the loglevel is activated.
     *
     * @param mixed $message message
     * @param mixed ...params
     *
     * @access public
     * @return void
     */
    public function fatal() {
        if (DEVELOPER_MODE) {
            if (!$this->verifyLogSyntax(func_get_args())) {
                return;
            }
        }
        if ($this->isFatalEnabled()) {
            $this->writeLog(\LoggerLevel::getLevelFatal(), func_get_args(), ' - '. $this->getCaller(1, true));
        }
    }

    /**
     * Check whether this Logger is enabled for the TRACE Level.
     * @return boolean
     */
    public function isTraceEnabled() {
        return $this->logger->isEnabledFor(\LoggerLevel::getLevelTrace());
    }

    /**
     * Check whether this Logger is enabled for the DEBUG Level.
     * @return boolean
     */
    public function isDebugEnabled() {
        return $this->logger->isEnabledFor(\LoggerLevel::getLevelDebug());
    }

    /**
     * Check whether this Logger is enabled for the INFO Level.
     * @return boolean
     */
    public function isInfoEnabled() {
        return $this->logger->isEnabledFor(\LoggerLevel::getLevelInfo());
    }

    /**
     * Check whether this Logger is enabled for the WARN Level.
     * @return boolean
     */
    public function isWarnEnabled() {
        return $this->logger->isEnabledFor(\LoggerLevel::getLevelWarn());
    }

    /**
     * Check whether this Logger is enabled for the ERROR Level.
     * @return boolean
     */
    public function isErrorEnabled() {
        return $this->logger->isEnabledFor(\LoggerLevel::getLevelError());
    }

    /**
     * Check whether this Logger is enabled for the FATAL Level.
     * @return boolean
     */
    public function isFatalEnabled() {
        return $this->logger->isEnabledFor(\LoggerLevel::getLevelFatal());
    }
}