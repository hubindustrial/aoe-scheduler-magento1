<?php

declare(strict_types=1);

final class TestableOutputBufferControlsSchedule extends Aoe_Scheduler_Model_Schedule
{
    /** @var null|string $_idFieldName */
    protected $_idFieldName = 'id';

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

    public function load($id, $field = null): never
    {
        throw new RuntimeException('Not implemented!');
    }

    public function save(): self
    {
        // Impl copied from Mage_Core_Model_Abstract

        if ($this->isDeleted()) {
            return $this->delete();
        }
        if (!$this->_hasModelChanged()) {
            return $this;
        }
        $this->_beforeSave();
        // No-op save
        $this->afterCommitCallback();
        $this->_afterSave();
        return $this;
    }

    protected function _beforeSave(): self
    {
        // Skip parent method, but want to call base method
        $baseProxy = new class($this->getData()) extends Mage_Core_Model_Abstract {
            protected $_idFieldName = 'id';
        };
        $baseProxy->_beforeSave();
        return $this;
    }

    public function delete(): self
    {
        return $this;
    }

    public function saveMessages(): self
    {
        return $this->save();
    }

    protected function log($message, $level = null): void
    {
        // no-op
    }

    // --- Public wrappers for protected buffer methods ---

    public function _startBufferToMessages(): static
    {
        return parent::_startBufferToMessages();
    }

    public function _stopBufferToMessages(): static
    {
        return parent::_stopBufferToMessages();
    }

    public function isBufferingOutput(): bool
    {
        return parent::isBufferingOutput();
    }

    public function isNotBufferingOutput(): bool
    {
        return parent::isNotBufferingOutput();
    }
}