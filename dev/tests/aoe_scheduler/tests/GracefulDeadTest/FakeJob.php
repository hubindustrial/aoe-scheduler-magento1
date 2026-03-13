<?php

declare(strict_types=1);

/**
 * No persistence. Allows use to set arbitrary operations as its callback
 */
final class FakeJob extends Aoe_Scheduler_Model_Job
{
    /** @var null|string $_idFieldName */
    protected $_idFieldName = 'job_code';

    private null|Closure $callback = null;

    public function _construct(): void
    {
        $this->_init(null);
    }

    protected function _getResource(): never
    {
        throw new RuntimeException('Not implemented!');
    }

    public function getResource(): never
    {
        throw new RuntimeException('Not implemented!');
    }

    public function getResourceCollection(): never
    {
        throw new RuntimeException('Not implemented!');
    }

    public function getCollection(): never
    {
        // parent::getCollection() is an alias for static::getResourceCollection()
        throw new RuntimeException('Not implemented!');
    }

    public function load($jobCode, $field = null): never
    {
        throw new RuntimeException('Not implemented!');
    }

    public function afterLoad(): self
    {
        // Impl copied from Mage_Core_Model_Abstract

        // Remove getResource()->afterLoad() call
        // $this->getResource()->afterLoad($this);
        $this->_afterLoad();
        return $this;
    }

    public function save(): never
    {
        throw new RuntimeException('Not implemented!');
    }

    public function delete(): never
    {
        throw new RuntimeException('Not implemented!');
    }

    public function getCallback(): callable
    {
        if ($this->callback === null) {
            $callback = parent::getCallback();
            $this->callback = $callback(...);
        }
        return $this->callback;
    }

    public function setCallback(callable $callback): self
    {
        $this->callback = $callback(...);
        return $this;
    }
}
