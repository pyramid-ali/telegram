<?php

namespace Alish\Telegram\Http\Controllers;


use Alish\Telegram\API\Update;
use Alish\Telegram\Exception\NoTelegramHandlerFoundException;
use Alish\Telegram\Exception\TelegramException;
use Alish\Telegram\Facades\Telegram;
use Alish\Telegram\Model\TelegramUpdate;
use Alish\Telegram\Parser\DocBlockParser;
use Alish\Telegram\Parser\Parser;
use Alish\Telegram\TelegramCommand;
use Alish\Telegram\TelegramLoader;
use Alish\Telegram\TelegramUpdateHandler;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class TelegramController extends BaseController
{
    private $docBlockParser;

    /**
     * TelegramController constructor.
     */
    public function __construct()
    {
        $this->docBlockParser = new DocBlockParser();
    }

    /**
     * @param Request $request
     * @return Response
     * @throws TelegramException
     */
    public function __invoke(Request $request)
    {
        $inputs = $request->all();
        $update = Parser::parse(Update::class, $inputs);

        try {

            if($this->isNewRequest($update)) {
                $this->handleRequest($update);
            }
        }
        catch (\Exception $error) {
            $this->handleError($error);
        }
        finally {
            $this->saveRequest($update, $inputs);
        }

        return Response::create('', 200);
    }

    /**
     * check if this response is fresh or not
     * @param Update $update
     * @return bool
     */
    private function isNewRequest(Update $update)
    {
        return !TelegramUpdate::where('update_id', $update->getUpdateId())->exists();
    }

    /**
     * save request to database for avoiding duplicate response and have a log for them
     * @param Update $update
     * @param $inputs
     * @return TelegramUpdate
     */
    private function saveRequest(Update $update, $inputs)
    {
        return TelegramUpdate::create([
            'update_id' => $update->getUpdateId(),
            'result' => json_encode($inputs)
        ]);
    }

    /**
     * @param Update $update
     * @throws NoTelegramHandlerFoundException
     * @throws \ReflectionException
     */
    private function handleRequest(Update $update)
    {
        $type = $this->getUpdateType($update);
        $this->setUser($update, $type['type']);

        $this->runLoaders($update);

        $handler = $this->getHandlerInstance($type['type'], $update);

        if ($this->isCommandActive()) {
            if ($this->handleCommand($type, $update)) {
                return;
            }
        }

        if ($handler) {
            $handler->handler();
            return;
        }

        throw new NoTelegramHandlerFoundException();

    }

    /**
     * @param Update $update
     * @return array|null
     * @throws \ReflectionException
     */
    private function getUpdateType(Update $update)
    {
        $properties = (new \ReflectionClass($update))->getProperties();
        foreach ($properties as $property) {
            if ($this->docBlockParser->isNullable($property)) {
                $key = $property->getName();
                $getter = $this->docBlockParser->getGetter($key);
                if ($update->$getter()) {
                    return [
                        'key' => studly_case($key),
                        'type' => $this->docBlockParser->getTypeOfProperty($property)
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param $update
     * @param $type
     */
    private function setUser($update, $type)
    {

        $method = 'get' . studly_case($type);
        $result = $update->$method();
        Telegram::setUser($result->getFrom());
    }


    /**
     * @param Update $update
     * @return null
     */
    private function commandHandler(Update $update)
    {
        $message = $update->getMessage();
        $text = $message->getText();

        if ($entities = $message->getEntities()) {

            if (count($entities) === 1) {
                $entity = $entities[0];
                $type = $entity->getType();

                if ($type === "bot_command") {
                    $offset = $entity->getOffset();
                    $length = $entity->getLength();
                    $command = substr($text, $offset + 1, $length);
                    return config('telegram.commands.list.' . $command);
                }

            }

        }

        return null;
    }

    /**
     * @return mixed
     */
    private function isCommandActive()
    {
        return config('telegram.commands.active', false);
    }

    /**
     * @param $type
     * @param Update $update
     * @return bool
     */
    private function handleCommand($type, Update $update)
    {
        if ($type['key'] === 'Message') {
            if ($command = $this->commandHandler($update)) {
                if (class_exists($command)) {
                    $message = $update->getMessage();
                    $class = new $command($message);
                    if ($class instanceof TelegramCommand) {
                        $class->handler();
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param $type
     * @param $update
     * @return null
     */
    private function getHandlerInstance($type, $update)
    {
        $handler = $this->getHandler($type);
        if (class_exists($handler)) {
            $instance = new $handler($update);
            if ($instance instanceof TelegramUpdateHandler) {
                return $instance;
            }
        }

        return null;

    }

    /**
     * @param $type
     * @return mixed
     */
    private function getHandler($type)
    {
        return config('telegram.handlers.' . $type, null);
    }

    /**
     * @param Update $update
     */
    private function runLoaders(Update $update)
    {
        $loaders = config('telegram.loaders', []);
        foreach ($loaders as $loader) {
            if (class_exists($loader)) {
                $loaderInstance = new $loader($update);
                if ($loaderInstance instanceof TelegramLoader) {
                    $loaderInstance->process();
                }
            }
        }
    }

    /**
     * handle any errors throw during parsing the response
     * @param \Exception $error
     * @return mixed
     * @throws TelegramException
     */
    private function handleError(\Exception $error)
    {
        if ($exceptionHandler = config('telegram.ExceptionHandler')) {
            return (new $exceptionHandler($error))->handler();
        }

        throw new TelegramException($error->getMessage(), $error->getCode());
    }

}