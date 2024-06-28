<?php
declare(strict_types=1);

namespace Flux\Database\Query;

use Flux\Database\DatabaseInterface;
use Flux\Logger\LoggerInterface;

/**
 * Class Builder
 * @package Flux\Database\Query
 */
class Builder
{
    const   SELECT = 1;
    const   UPDATE = 2;
    const   INSERT = 3;

    protected DatabaseInterface $db;
    protected ?LoggerInterface $logger;

    protected int $method = self::SELECT;

    protected ?string $table;

    protected array $columns = array('*');
    protected array $where = array();

    /**
     * @param DatabaseInterface $db
     * @param LoggerInterface|null $logger
     * @return Builder
     */
    public static function create(DatabaseInterface $db, ?LoggerInterface $logger): Builder
    {
        return new static($db, $logger);
    }

    /**
     * Builder constructor.
     * @param DatabaseInterface $db
     * @param LoggerInterface|null $logger
     */
    public function __construct(DatabaseInterface $db, ?LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * @param string $table
     * @return Builder
     */
    public function table(string $table): Builder
    {
        $this->table = $table;

        return $this;
    }

    /**
     * @param mixed $cols
     * @return Builder
     */
    public function select(mixed $cols = array('*')): Builder
    {

        if (is_array($cols))
            $this->columns = $cols;
        else
            $this->columns = func_get_args();

        return $this;
    }


}
