<?php declare(strict_types = 1);

namespace Caphpe\Tests\Cache;

use Caphpe\Cache\Pool;
use PHPUnit\Framework\TestCase;

/**
 * Class PoolTest
 *
 * @package Caphpe\Tests\Cache
 */
class PoolTest extends TestCase
{
    /**
     *
     */
    public function test_it_adds()
    {
        $pool = new Pool();

        $pool->add('test_key', 'test_value');

        $pool->add('test2_key', 'test2_value');
        $pool->add('test2_key', 'altered_value');

        $this->assertSame($pool->get('test_key'), 'test_value');
        $this->assertSame($pool->get('test2_key'), 'test2_value');
    }

    /**
     *
     */
    public function test_it_sets()
    {
        $pool = new Pool();

        $pool->set('test_key', 'some value');
        $pool->set('test_key', 'another_value');
        $pool->set('another_key', 'test value');

        $this->assertSame($pool->get('test_key'), 'another_value');
        $this->assertSame($pool->get('another_key'), 'test value');
    }

    /**
     *
     */
    public function test_it_replaces()
    {
        $pool = new Pool();

        $pool->replace('test_key', 'replaced val');
        $pool->add('exist_key', 'test value');
        $pool->replace('exist_key', 'repl value');

        $this->assertSame($pool->get('test_key'), null);
        $this->assertSame($pool->get('exist_key'), 'repl value');
    }

    /**
     *
     */
    public function test_it_deletes()
    {
        $pool = new Pool();

        $pool->set('to_delete', 'im not here');
        $pool->delete('to_delete');

        $this->assertNull($pool->get('to_delete'));
    }

    /**
     *
     */
    public function test_it_increments_and_decrements()
    {
        $pool = new Pool();

        $pool->set('to_incr', 5);
        $pool->set('to_decr', 10);
        $pool->set('non_incr', 'imma string this up');
        $pool->set('non_decr', 'imma string this down');

        $pool->increment('to_incr');
        $pool->increment('non_incr');
        $pool->decrement('to_decr');
        $pool->decrement('non_decr');

        $this->assertSame($pool->get('to_incr'), 6);
        $this->assertSame($pool->get('to_decr'), 9);
        $this->assertSame($pool->get('non_incr'), 'imma string this up');
        $this->assertSame($pool->get('non_decr'), 'imma string this down');
    }

    /**
     *
     */
    public function test_it_has()
    {
        $pool = new Pool();

        $pool->set('somekey1', 'hello');

        $this->assertTrue($pool->has('somekey1'));
        $this->assertFalse($pool->has('otherkey'));
    }

    /**
     *
     */
    public function test_it_flushes()
    {
        $pool = new Pool();

        $pool->set('key1', 'value1');
        $pool->set('key2', 'value2');
        $pool->set('key3', 'value3');
        $pool->set('key4', 'value4');

        $this->assertSame($pool->get('key1'), 'value1');
        $this->assertSame($pool->get('key2'), 'value2');
        $this->assertSame($pool->get('key3'), 'value3');
        $this->assertSame($pool->get('key4'), 'value4');

        $pool->flush();

        $this->assertNull($pool->get('key1'));
        $this->assertNull($pool->get('key2'));
        $this->assertNull($pool->get('key3'));
        $this->assertNull($pool->get('key4'));
    }

    /**
     *
     */
    public function test_it_clears_lru()
    {
        $pool = new Pool();

        $pool->set('key1', 'value');
        $pool->set('key2', 'value');
        $pool->set('key3', 'value');
        $pool->set('key4', 'value');
        $pool->set('key5', 'value');
        $pool->set('key6', 'value');
        $pool->set('key7', 'value');
        $pool->set('key8', 'value');
        $pool->set('key9', 'value');
        $pool->set('key10', 'value');
        $pool->set('key11', 'value');
        $pool->set('key12', 'value');

        for ($i = 1; $i <= 12; $i++) {
            usleep(500000); //FIXME a way to lessen the wait here

            $pool->replace('key'.$i, time());
        }

        $pool->clearLeastRecentlyUsed(0.5);

        $this->assertFalse($pool->has('key1'));
        $this->assertFalse($pool->has('key2'));
        $this->assertFalse($pool->has('key3'));
        $this->assertFalse($pool->has('key4'));
        $this->assertFalse($pool->has('key5'));
        $this->assertFalse($pool->has('key6'));

        $this->assertTrue($pool->has('key7'));
        $this->assertTrue($pool->has('key8'));
        $this->assertTrue($pool->has('key9'));
        $this->assertTrue($pool->has('key10'));
        $this->assertTrue($pool->has('key11'));
        $this->assertTrue($pool->has('key12'));
    }

    /**
     *
     */
    public function test_it_clears_stale_cache()
    {
        $pool = new Pool();

        $pool->set('key1', 'value', 1);
        $pool->set('key2', 'value', 1);
        $pool->set('key3', 'value', 1);

        $pool->set('key4', 'value', 3600);
        $pool->set('key5', 'value', 3600);
        $pool->set('key6', 'value', 3600);

        sleep(2);

        $pool->clearStaleCache();

        $this->assertFalse($pool->has('key1'));
        $this->assertFalse($pool->has('key2'));
        $this->assertFalse($pool->has('key3'));

        $this->assertTrue($pool->has('key4'));
        $this->assertTrue($pool->has('key5'));
        $this->assertTrue($pool->has('key6'));
    }

    /**
     *
     */
    public function test_it_calculates_timeouts()
    {
        $pool = new Pool();

        $time = time();
        $timeout = 60;

        $desiredAbsoluteTimeout = $time + $timeout;

        $generatedTimeout = $pool->calculateTimeout($timeout);

        $this->assertEquals($generatedTimeout, $desiredAbsoluteTimeout);

        $this->assertEquals($pool->calculateTimeout(0), 0);
    }

    /**
     *
     */
    public function test_it_parses_keys_and_values()
    {
        $pool = new Pool();

        $srcLong = str_repeat((string) time(), 24);

        $key = $pool->parseKey($srcLong);

        $this->assertEquals(64, strlen($key));

        $srcWrongChars = 'this_is_245_~//_a_key_with_öäå';
        $key = $pool->parseKey($srcWrongChars);

        $this->assertEquals('this_is_245__a_key_with_', $key);

        $valuesToTest = [
            [true, true],
            [false, false],
            ['false', false],
            ['true', true],
            ['0', '0'],
            ['value', 'value'],
            [12, 12],
            [null, null]
        ];

        foreach ($valuesToTest as $valuePair) {
            $this->assertEquals($valuePair[1], $pool->prepareValue($valuePair[0]));
        }
    }
}
