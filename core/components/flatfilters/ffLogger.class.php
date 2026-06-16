<?php
/**
 * @author Arthur Shevchenko (shev.art.v@yandex.ru)
 * @description Логирование FlatFilters через mxLogger — единственный приёмник.
 * Логирование включено из коробки (ff_debug=Да по умолчанию), минимальный уровень —
 * error (ff_log_level). Стандартный журнал MODX не используется; если mxLogger не
 * установлен — один раз предупреждаем в журнале MODX и больше не логируем.
 * Компонент классический (без namespace) — класс глобальный.
 * @example
 *      require_once MODX_CORE_PATH . 'components/flatfilters/ffLogger.class.php';
 *      $logger = new ffLogger($modx);
 *      $logger->write('Не указан тип объекта', ['configId' => 5], 'error', 'config');
 */
class ffLogger
{
    /** Базовые тэги: имя пакета + цепочка (каталог). */
    const TAG = 'flatfilters';
    const CHAIN_TAG = 'catalog';

    const LEVELS = array(
        'debug'   => 10,
        'info'    => 20,
        'warning' => 30,
        'error'   => 40,
    );

    /** @var modX */
    private $modx;
    /** @var bool */
    private $debug;
    /** @var string */
    private $minLevel;
    /** @var string|null */
    private $processUid = null;
    /** @var mxLogger|null|false */
    private $mxl = false;

    /**
     * @param modX        $modx
     * @param bool|null   $debug    Если null — из настройки ff_debug (по умолчанию вкл.).
     * @param string|null $minLevel Если null — из настройки ff_log_level (по умолчанию error).
     */
    public function __construct(modX $modx, $debug = null, $minLevel = null)
    {
        $this->modx = $modx;
        $this->debug = $debug === null ? (bool) $modx->getOption('ff_debug', null, true) : (bool) $debug;
        $this->minLevel = $this->normalizeLevel(
            $minLevel === null ? $modx->getOption('ff_log_level', null, 'error') : $minLevel
        );
    }

    private function normalizeLevel($level)
    {
        $level = strtolower((string) $level);
        return isset(self::LEVELS[$level]) ? $level : 'debug';
    }

    private function levelEnabled($level)
    {
        return self::LEVELS[$this->normalizeLevel($level)] >= self::LEVELS[$this->minLevel];
    }

    public function setProcess($uid)
    {
        $this->processUid = ($uid !== null && $uid !== '') ? (string) $uid : null;
    }

    /**
     * @param string       $msg
     * @param array        $data
     * @param string       $level debug|info|warning|error
     * @param string|array $tags  доп. тэг(и) к базовым «flatfilters»/«catalog»
     * @return void
     */
    public function write($msg, array $data = array(), $level = 'info', $tags = array())
    {
        if (!$this->debug) {
            return;
        }
        $level = $this->normalizeLevel($level);
        if (!$this->levelEnabled($level)) {
            return;
        }

        $mxl = $this->resolveMxLogger();
        if ($mxl === null) {
            return;
        }

        $allTags = array(self::TAG, self::CHAIN_TAG);
        foreach ((array) $tags as $tag) {
            $tag = (string) $tag;
            if ($tag !== '' && !in_array($tag, $allTags, true)) {
                $allTags[] = $tag;
            }
        }

        $options = array('skip_classes' => array(__CLASS__));
        if ($this->processUid !== null && $this->processUid !== '') {
            $options['process_uid'] = $this->processUid;
        }
        $mxl->log($allTags, $level, (string) $msg, $data, $options);
    }

    /**
     * @return mxLogger|null
     */
    private function resolveMxLogger()
    {
        if ($this->mxl !== false) {
            return $this->mxl;
        }
        if (isset($this->modx->mxlogger) && $this->modx->mxlogger instanceof mxLogger) {
            return $this->mxl = $this->modx->mxlogger;
        }
        $corePath = $this->modx->getOption(
            'mxlogger.core_path',
            null,
            $this->modx->getOption('core_path') . 'components/mxlogger/'
        );
        if (is_file($corePath . 'model/mxlogger/mxlogger.class.php')) {
            $svc = $this->modx->getService('mxlogger', 'mxLogger', $corePath . 'model/mxlogger/');
            if ($svc instanceof mxLogger) {
                return $this->mxl = $svc;
            }
        }

        $this->modx->log(
            modX::LOG_LEVEL_ERROR,
            '[FlatFilters] Установите mxLogger или отключите логирование (настройка ff_debug).'
        );

        return $this->mxl = null;
    }
}
