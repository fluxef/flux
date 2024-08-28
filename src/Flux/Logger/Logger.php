<?php
declare(strict_types=1);

namespace Flux\Logger;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;
use function date_default_timezone_get;
use function is_bool;
use function is_null;

class Logger implements LoggerInterface
{

    protected string $host = '';
    protected int $userid = 0;
    protected string $uidstring = '';
    protected bool $withtrace = true;
    protected array $loglevels = array();
    protected array $filenames = array();
    protected string $clientip = '';
    protected int $logoptions = LOG_NDELAY;

    public function __construct(protected string $app = 'ins-cmf', protected $facility = LOG_USER)
    {

        $this->loglevels = array(
            LogLevel::EMERGENCY => LOG_EMERG,
            LogLevel::ALERT => LOG_ALERT,
            LogLevel::CRITICAL => LOG_CRIT,
            LogLevel::ERROR => LOG_ERR,
            LogLevel::WARNING => LOG_WARNING,
            LogLevel::NOTICE => LOG_NOTICE,
            LogLevel::INFO => LOG_INFO,
            LogLevel::DEBUG => LOG_DEBUG
        );

        // openlog($this->app, LOG_NDELAY, $this->facility);
    }

    public function setLogPath(string $path): void
    {
        $this->filenames = array(
            LogLevel::EMERGENCY => $path . 'cms.emergency',
            LogLevel::ALERT => $path . 'cms.alert',
            LogLevel::CRITICAL => $path . 'cms.critical',
            LogLevel::ERROR => $path . 'cms.error',
            LogLevel::WARNING => $path . 'cms.warning',
            LogLevel::NOTICE => $path . 'cms.notice',
            LogLevel::INFO => $path . 'cms.info',
            LogLevel::DEBUG => $path . 'cms.debug'
        );

    }

    public function setLogLevelPath(string $level, string $path): void
    {
        // if no valid laglevel =>  throw exception

        switch ($level) {
            case LogLevel::EMERGENCY:
            case LogLevel::ALERT:
            case LogLevel::CRITICAL:
            case LogLevel::ERROR:
            case LogLevel::WARNING:
            case LogLevel::NOTICE:
            case LogLevel::INFO:
            case LogLevel::DEBUG:
                break;
            default:
                throw new InvalidArgumentException('unknown level ' . $level);
        }

        if (!empty($path)) {
            $this->filenames[$level] = $path;
            return;
        }

        if (isset($this->filenames[$level]))
            unset($this->filenames[$level]);

    }

    protected function write(array $data): void
    {
        if (isset($this->filenames[$data['level']])) {
            $path = $this->filenames[$data['level']];
            if (empty($this->host))
                $host = '-';
            else
                $host = $this->host;

            $tz = new DateTimeZone(date_default_timezone_get());
            $dt = new DateTime('now', $tz);
            $pre = $dt->format(DateTimeInterface::RFC3339);
            $pre .= ' ' . $host . ' ' . $this->app . '[0]: ';
            file_put_contents($path, $pre . ((string)$data['formatted']) . "\n", FILE_APPEND | LOCK_EX);
        } else {
            openlog($this->app, $this->logoptions, $this->facility);
            $prio = $this->loglevels[$data['level']];
            syslog($prio, (string)$data['formatted']);
        }
    }

    protected function escapeSDValue(mixed $inhalt = null): string
    {
        /*
         * Inside PARAM-VALUE, the characters '"' (ABNF %d34), '\' (ABNF %d92),
         * and ']' (ABNF %d93) MUST be escaped. This is necessary to avoid
         * parsing errors. Escaping ']' would not strictly be necessary but is
         * REQUIRED by this specification to avoid syslog application
         * implementation errors. Each of these three characters MUST be
         * escaped as '\"', '\\', and '\]' respectively.
         */
        $backslash = chr(92);


        if (is_bool($inhalt)) {
            if ($inhalt === true)
                return '1';
            else
                return '0';
        }

        if (empty($inhalt))
            return '';

        $inhalt = (string)$inhalt;

        // first double backslash
        $inhalt = str_replace($backslash, $backslash . $backslash, $inhalt);

        // then the other two
        $inhalt = str_replace('"', $backslash . '"', $inhalt);
        $inhalt = str_replace(']', $backslash . ']', $inhalt);

        return $inhalt;
    }

    protected function formatter(array $data): string
    {
        /**
         * RFC 3164: <priority>VERSION TIMESTAMP HOSTNAME APPLICATION[PID]: MSG
         * RFC 5424: <priority>VERSION ISOTIMESTAMP HOSTNAME APPLICATION PID MESSAGEID STRUCTURED-DATA MSG
         *
         * openlog() always creates the ':' after the APPLICATION, so it is more RFC 3164 compliant than RFC 5424 compliant.
         * syslog(int $priority, string $message) therefore only sets the RFC 3164 MSG, but we now set PID MESSAGEID STRUCTURED-DATA MSG in it
         *
         * bom deactivated until further notice $bom = "\xEF\xBB\xBF";
         */


        $bom = '';

        // procid
        if ($this->userid > 0)
            $ret = $this->userid . ' ';
        else
            $ret = '- ';

        // msgid
        if (isset($data['msgid']))
            $ret .= $data['msgid'] . ' ';
        else
            $ret .= '- ';

        // sd-element comes from context, other context parameters we have to delete
        $sd = $data;
        unset($sd['msgid']);
        unset($sd['backtrace']);
        unset($sd['trace']);
        unset($sd['notrace']);
        unset($sd['message']);
        unset($sd['level']);

        if ((!empty($this->host)))
            $sd['host'] = $this->host;

        if (isset($data['backtrace'])) {
            $trace = Backtrace::Extract($data['backtrace']);
            foreach ($trace as $feld => $inhalt)
                $sd[$feld] = $inhalt;
        }

        if (empty($sd))
            $ret .= '-'; // Structured Data
        else {
            $ret .= '[';
            $sp = ' '; // always, because the sd name is always at the beginning

            // first private enterprise number and sd-name, see https://tools.ietf.org/html/rfc5424#page-15
            $ret .= 'cmsv1@1596';

            if (isset($sd['host'])) {
                $ret .= $sp . 'host=' . '"' . $this->escapeSDValue($sd['host']) . '"';
                unset($sd['host']);
            }

            if (isset($sd['ip'])) {
                $ret .= $sp . 'ip=' . '"' . $this->escapeSDValue($sd['ip']) . '"';
                unset($sd['ip']);
            }

            if (!empty($sd)) {
                foreach ($sd as $feld => $inhalt)
                    $ret .= $sp . $feld . '=' . '"' . $this->escapeSDValue($inhalt) . '"';
            }
            $ret .= ']';
        }

        // message always starts with the BOM
        if (!empty($data['message']))
            $ret .= ' ' . $bom . $data['message'];

        return $ret;
    }


    public function getClientIP(): string
    {
        return $this->clientip;
    }

    public function setClientIP(string $ip): self
    {
        $this->clientip = $ip;
        return $this;
    }

    public function setUserID(int $uid = 0): self
    {
        $this->userid = $uid;

        if ($this->userid > 0)
            $this->uidstring = '[' . $this->userid . ']';

        return $this;

    }

    public function setHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function setLogOptions(?int $options = null): self
    {
        if (!is_null($options))
            $this->logoptions = $options;

        return $this;

    }

    public function disableBacktrace(): self
    {
        $this->withtrace = false;
        return $this;
    }

    public function emergency($message = '', array $context = array()): void
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = array()): void
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = array()): void
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = array()): void
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = array()): void
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::WARNING, $message, $context);
    }


    public function notice($message, array $context = array()): void
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = array()): void
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = array()): void
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::DEBUG, $message, $context);
    }


    public function log($level, $message, array $context = array()): void
    {

        // if no valid laglevel =>  throw exception
        switch ($level) {
            case LogLevel::EMERGENCY:
            case LogLevel::ALERT:
            case LogLevel::CRITICAL:
            case LogLevel::ERROR:
            case LogLevel::WARNING:
            case LogLevel::NOTICE:
            case LogLevel::INFO:
            case LogLevel::DEBUG:
                break;
            default:
                throw new InvalidArgumentException('unknown level ' . $level);
        }

        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $context['message'] = $message;
        $context['level'] = $level;

        $context['formatted'] = $this->formatter($context);

        $this->write($context);

    }

}
