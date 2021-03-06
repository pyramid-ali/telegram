<?php


namespace Alish\Telegram;


use Alish\Telegram\API\Update;
use Alish\Telegram\API\User;
use Alish\Telegram\Events\FailedToParseUpdateFromTelegram;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;

trait HasUpdate
{

    /**
     * @var Update
     */
    protected $update;
    
    /**
     * @param  Update|null  $update
     * @return $this|Update
     */
    public function update(?Update $update = null)
    {
        if ($update) {
            $this->update = $update;
            return $this;
        }

        return $this->update;
    }

    /**
     * @return string|null
     */
    public function updateType() : ?string
    {
        if(!$this->update) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($this->update);

            $properties = (new Collection($reflection->getProperties()))->map(function (\ReflectionProperty $property) {
                return $property->getName();
            })->toArray();

            $properties = array_diff($properties, ['update_id']);

            foreach ($properties as $property) {
                if (isset($this->update->$property)) {
                    return $property;
                }
            }
        } catch (ReflectionException $exception) {
            $this->dispatch(new FailedToParseUpdateFromTelegram($exception));
        }

        return null;
    }

    /**
     * @return mixed|null
     */
    public function updateObject()
    {
        if (!($type = $this->updateType())) {
            return null;
        }

        $method = 'get' . Str::studly($type);
        return $this->update->$method();
    }

    /**
     * @return User|null
     */
    public function user() : ?User
    {
        if ($object = $this->updateObject()) {
            return $object->getFrom();
        }
        return null;
    }
}