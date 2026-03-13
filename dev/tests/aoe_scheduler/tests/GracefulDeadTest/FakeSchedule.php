<?php

declare(strict_types=1);

/**
 * Saves to a temporary file, so it can be used without a DB connection.
 */
final class FakeSchedule extends Aoe_Scheduler_Model_Schedule
{
    /** @var null|string $_idFieldName */
    protected $_idFieldName = 'id';

    private null|string $file = null;

    private static function getBackingFilePath(string $id): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aoe_schedule_model_schedule_' . $id;
    }

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

    /**
     * @param string $id
     * @param null|string $field
     * @return $this
     */
    public function load($id, $field = null): self
    {
        // Impl copied from Mage_Core_Model_Abstract

        $this->_beforeLoad($id, $field);

        // Remove _getResource() call and replace it with loading from file

        // $this->_getResource()->load($this, $id, $field);
        $file = self::getBackingFilePath($id);
        $serializedData = file_get_contents($file);
        if ($serializedData === false) {
            if (file_exists($file)) {
                throw new RuntimeException('Error reading model data file');
            }
        } elseif ($serializedData !== '') {
            $deserializedData = @unserialize($serializedData, ['allowed_classes' => []]);
            if (is_array($deserializedData)) {
                $this->setData($deserializedData);
            } else {
                throw new RuntimeException('Invalid data encountered in model data file');
            }
        }

        $this->file = $file;

        if ($this->job !== null && $this->getJobCode() !== $this->job->getId()) {
            $this->job = null;
        }

        $this->_afterLoad();
        $this->setOrigData();
        $this->_hasDataChanges = false;
        return $this;
    }

    public function afterLoad(): self
    {
        // Impl copied from Mage_Core_Model_Abstract

        // Remove getResource()->afterLoad() call
        // $this->getResource()->afterLoad($this);
        $this->_afterLoad();
        return $this;
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

        try {
            $this->_beforeSave();
            if ($this->_dataSaveAllowed) {
                if ($this->isObjectNew()) {
                    /** @var null|string $id */
                    $id = null;
                    // Retry an arbitrary number of times to generate an unused ID
                    for ($i = 0; $i < 100; $i++) {
                        $candidateId = (string) random_int(1, PHP_INT_MAX);
                        if (!file_exists(self::getBackingFilePath($candidateId))) {
                            $id = $candidateId;
                            break;
                        }
                    }
                    if ($id === null) {
                        throw new RuntimeException('Could not create a unique ID');
                    }

                    // Defaults based off database table defaults
                    $this->setId($id);
                    $this->setJobCode($this->job?->getJobCode() ?? $this->getJobCode() ?? '0');
                    $this->setStatus(Aoe_Scheduler_Model_Schedule::STATUS_PENDING);
                    $this->setCreatedAt(date(Varien_Db_Adapter_Pdo_Mysql::TIMESTAMP_FORMAT, time()));
                    $this->file = self::getBackingFilePath($this->getId());
                }

                $serializedData = serialize($this->getData());
                if (!file_put_contents($this->file, $serializedData, LOCK_EX)) {
                    throw new RuntimeException('Error writing model data file');
                }
                $this->afterCommitCallback();
                $this->_afterSave();
            }
            $this->_hasDataChanges = false;
        } catch (Throwable $e) {
            $this->_hasDataChanges = true;
            throw $e;
        }

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
        // Impl copied from Mage_Core_Model_Abstract

        $this->_beforeDelete();
        @unlink($this->file);
        $this->_afterDelete();
        $this->_afterDeleteCommit();
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

    public function setLastRunUser($user = null): self
    {
        return $this;
    }

    public function setJob(null|Aoe_Scheduler_Model_Job $job): self {
        $this->setJobCode($job->getJobCode());
        $this->job = $job;
        return $this;
    }
}
