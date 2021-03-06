<?php

namespace Alish\Telegram\API;

class ForceReply extends BaseTelegram
{

    /**
     * @var true $force_reply
     * Shows reply interface to the user, as if they manually selected the bot‘s message and tapped ’Reply'
     */
    protected $force_reply;

    /**
     * @var boolean|null $selective
     * Optional. Use this parameter if you want to force reply from specific users only.
     * Targets: 1) users that are mentioned in the text of the Message object; 2) if the bot's message is a reply (has reply_to_message_id), sender of the original message.
     */
    protected $selective;
}
