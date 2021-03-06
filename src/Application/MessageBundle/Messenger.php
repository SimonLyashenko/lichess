<?php

namespace Application\MessageBundle;

use Ornicar\MessageBundle\Model\Message;
use Ornicar\MessageBundle\Messenger as BaseMessenger;
use FOS\UserBundle\Model\User;

class Messenger extends BaseMessenger
{
    public function send(Message $message)
    {
        parent::send($message);

        $this->updateUnreadCache($message->getTo(), +1);
    }

    public function markAsRead(Message $message)
    {
        parent::markAsRead($message);

        $this->updateUnreadCache($message->getTo(), -1);
    }

    public function updateUnreadCache(User $user, $modifier = 0)
    {
        apc_store('nbm.'.$user->getUsername(), $this->messageRepository->countUnreadByUser($user) + $modifier);
    }

    public function getUnreadCacheForUsername($username)
    {
        return apc_fetch('nbm.'.$username) ?: 0;
    }

    public function countUnreadByUser(User $user)
    {
        return $this->getUnreadCacheForUsername($user->getUsername());
    }
}
