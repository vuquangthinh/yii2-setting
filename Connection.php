<?php
/**
 * Created by PhpStorm.
 * User: quangthinh
 * Date: 7/20/2016
 * Time: 2:49 PM
 */

namespace quangthinh\yii\setting;


use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\caching\Cache;
use yii\db\Connection as Db;
use yii\db\Query;

class Connection extends Component implements \ArrayAccess
{
    /**
     * @var string|array|Db
     */
    public $db = 'db';

    /**
     * @var string|array|Cache
     */
    public $cache = 'cache';

    /**
     * @var string
     */
    public $cachePrefix = 's#';

    public $tableName = '{{%setting}}';

    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub

        if (is_string($this->db)) {
            $this->db = Yii::$app->get($this->db);
        } else if (is_array($this->db)) {
            $this->db = Yii::createObject($this->db);
        }

        if (!($this->db instanceof Db)) {
            throw new InvalidConfigException('Invalid Db Connection');
        }

        if (is_string($this->cache)) {
            $this->cache = Yii::$app->get($this->cache);
        } else if (is_array($this->db)) {
            $this->cache = Yii::createObject($this->cache);
        }

        if (!($this->cache instanceof Cache)) {
            throw new InvalidConfigException('Cache is not instance of Cache class');
        }
    }

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        if ($this->cache->exists($this->cachePrefix . $offset)) {
            return $this->cache->get($this->cachePrefix . $offset);
        }

        return (new Query())
            ->select('key')
            ->from($this->tableName)
            ->where(['key' => $offset])
            ->exists($this->db);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        if ($this->cache->exists($this->cachePrefix . $offset)) {
            return $this->cache->get($this->cachePrefix . $offset);
        }

        $data = (new Query())
            ->select('data')
            ->from($this->tableName)
            ->where([
                'key' => $offset,
            ])->createCommand($this->db)
            ->queryScalar();

        return unserialize($data);
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $data = serialize($value);

        if ($this->offsetExists($offset)) {
            $this->db->createCommand()
                ->update($this->tableName, [
                    'key' => $offset,
                    'data' => $data,
                ], [
                    'key' => $offset,
                ])->execute();
        } else {
            $this->db->createCommand()
                ->insert($this->tableName, [
                    'key' => $offset,
                    'data' => $data,
                ])
            ->execute();
        }

        $this->cache->delete($this->cachePrefix . $offset);
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        $this->db->createCommand()
            ->delete($this->tableName, [
                'key' => $offset,
            ])->execute();

        $this->cache->delete($this->cachePrefix . $offset);
    }
}