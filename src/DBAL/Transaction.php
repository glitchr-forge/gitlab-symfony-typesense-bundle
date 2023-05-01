<?php

declare(strict_types=1);

namespace Typesense\Bundle\DBAL;

use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Exceptions\ObjectNotFound;

/**
 *
 */
class Transaction
{
    protected TypesenseCollection $collection;

    protected array $mock;
    protected string $id;

    public const PERSIST = 'ACTION_PERSIST';
    public const UPDATE = 'ACTION_UPDATE';
    public const REMOVE = 'ACTION_REMOVE';

    protected string $action;
    protected array $options;
    protected bool $commited;

    /**
     * @param TypesenseCollection $collection
     * @param $action
     * @param string|object $objectOrId
     * @param array $options
     * @throws \Exception
     */
    public function __construct(TypesenseCollection $collection, $action, string|object $objectOrId, array $options = [])
    {
        $this->mock = [];
        if (is_string($objectOrId)) {
            $this->id = (string)$objectOrId;
        } else {
            $primaryField = $this->primaryKey($collection->metadata());

            $this->mock = $collection->transformer()->convert($objectOrId);
            $this->id = (string)$this->mock[$primaryField->name];
        }

        $this->collection = $collection;
        $this->options = $options;
        $this->action = $action;

        $this->commited = false;
    }

    /**
     * @param TypesenseMetadata $metadata
     * @return mixed
     * @throws \Exception
     */
    private function primaryKey(TypesenseMetadata $metadata)
    {
        foreach ($metadata->fields as $field) {
            if ($field->identifier) {
                return $field;
            }
        }

        throw new \Exception(sprintf('Primary key info not found for Typesense collection %s', $metadata->getName()));
    }

    public function action(): string
    {
        return $this->action;
    }

    public function commit()
    {
        if ($this->commited) {
            return;
        }

        switch ($this->action) {
            case self::PERSIST:
            case self::UPDATE:
                try {
                    $this->collection->documents()->delete($this->id);
                } catch (ObjectNotFound $e) {
                }

                $this->collection->documents()->create($this->mock, $this->options);
                break;

            case self::REMOVE:
                try {
                    $this->collection->documents()->delete($this->id);
                } catch (ObjectNotFound $e) {
                }

                break;

                throw new \Exception('Unsupported action');
        }

        $this->commited = true;
    }

    public function rollBack()
    {
        throw new \Exception('This method is not implemented yet.');
    }
}
