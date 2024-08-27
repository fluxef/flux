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

/**
 * Class Logger
 * @package Flux\Logger
 */
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

    public function setLogPath(string $path)
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

    public function setLogLevelPath(string $level, string $path)
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

    /**
     * @param array $data
     */
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

        // zuerst backslash verdoppeln
        $inhalt = str_replace($backslash, $backslash . $backslash, $inhalt);

        // dann die anderen beiden
        $inhalt = str_replace('"', $backslash . '"', $inhalt);
        $inhalt = str_replace(']', $backslash . ']', $inhalt);

        return $inhalt;
    }

    /**
     * @param array $data
     * @return string
     */
    protected function formatter(array $data): string
    {

        // RFC 3164: <priority>VERSION TIMESTAMP HOSTNAME APPLICATION[PID]: MSG
        // RFC 5424: <priority>VERSION ISOTIMESTAMP HOSTNAME APPLICATION PID MESSAGEID STRUCTURED-DATA MSG

        // openlog() erzeugt immer von sich aus den ':' nach der APPLICATION, ist also eher RFC 3164 konform, statt RFC 5424 konform.
        // syslog(int $priority, string $message) setzt daher auch nur die RFC 3164 MSG, wir setzen aber darin nun PID MESSAGEID STRUCTURED-DATA MSG

        // bom bis auf weiteres deaktiviert $bom = "\xEF\xBB\xBF";
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

        // sd-element kommt aus context, andere context parameter müssen wir löschen
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

        // if (! isset($sd['ip']))
        // $sd['ip']=$this->clientip;
        // print_r($sd);

        if (empty($sd))
            $ret .= '-'; // Structured Data
        else {
            $ret .= '[';
            $sp = ' '; // immer, da am anfang nun immer der sd-name steht

            // zuerst private enterprise number und sd-name, siehe https://tools.ietf.org/html/rfc5424#page-15
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

        // echo("\nRET=".$ret."\n");

        // message fängt immer mit der BOM an
        if (!empty($data['message']))
            $ret .= ' ' . $bom . $data['message'];

        // print_r($data);

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

    /**
     * @param int $uid
     */
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

    /**
     *
     */
    public function disableBacktrace(): self
    {
        $this->withtrace = false;
        return $this;
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function emergency($message = '', array $context = array())
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function alert($message, array $context = array())
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function critical($message, array $context = array())
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function error($message, array $context = array())
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function warning($message, array $context = array())
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function notice($message, array $context = array())
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function info($message, array $context = array())
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param string $message
     * @param array $context
     */
    public function debug($message, array $context = array())
    {
        if ((!isset($context['notrace'])) && ($this->withtrace) && empty($context['backtrace']))
            $context['backtrace'] = Backtrace::shiftLineFunction(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = array())
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
