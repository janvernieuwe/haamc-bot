<?php

namespace App\Channel;

use App\Context\CreateMangaChannelContext;
use App\Entity\Reaction;
use App\Message\JoinableChannelMessage;
use App\Message\JoinableMangaChannelMessage;
use CharlotteDunois\Yasmin\Models\CategoryChannel;
use CharlotteDunois\Yasmin\Models\Guild;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\TextChannel;

/**
 * Class MangaChannelCreator
 *
 * @package App\Channel
 */
class MangaChannelCreator
{
    /**
     * @var CreateMangaChannelContext
     */
    private $context;

    /**
     * @param CreateMangaChannelContext $context
     *
     * @internal param MessageReceivedEvent $event
     */
    public function create(CreateMangaChannelContext $context): void
    {
        $this->context = $context;
        $this->createChannel($context->getGuild(), $context->getChannelName());
    }

    /**
     * @param Guild  $guild
     * @param string $name
     */
    protected function createChannel(Guild $guild, string $name): void
    {
        /** @var CategoryChannel $category */
        $category = $guild->channels->get($this->context->getParent());
        $permissions = $category->permissionOverwrites->all();
        $permissions = array_merge(
            $permissions,
            [
                [
                    'id'   => $this->context->getEveryoneRole(),
                    'deny' => Channel::ROLE_VIEW_MESSAGES,
                    'type' => 'role',
                ],
                [
                    'id'    => $this->context->getClient()->user->id,
                    'allow' => Channel::ROLE_VIEW_MESSAGES,
                    'type'  => 'member',
                ],
            ]
        );
        $guild->createChannel(
            [
                'name'                 => $name,
                'topic'                => $name,
                'permissionOverwrites' => $permissions,
                'parent'               => $this->context->getParent(),
                'nsfw'                 => false,
            ]
        )->done(
            function (TextChannel $channel) {
                $channel->setTopic(
                    sprintf('%s || %s', $this->context->getManga()->getTitle(), $this->context->getManga()->getUrl())
                );
                /** @var Message $announcement */
                $channel->send(
                    sprintf(
                        "%s Hoi iedereen! In dit channel lezen we **%s**.\n%s",
                        JoinableChannelMessage::TEXT_MESSAGE,
                        $this->context->getManga()->getTitle(),
                        $this->context->getManga()->getUrl()
                    )
                )->then(
                    function (Message $announcement) {
                        $announcement->pin();
                    }
                );

                $this->sendJoinMessage($channel);
            }
        );
    }

    /**
     * @param TextChannel $channel
     */
    protected function sendJoinMessage(TextChannel $channel): void
    {
        $embed = JoinableMangaChannelMessage::generateRichChannelMessage(
            $this->context->getManga(),
            (int)$channel->id,
            $this->context->getManga()->getUrl()
        );
        $this->context->getChannel()
            ->send(JoinableChannelMessage::TEXT_MESSAGE, $embed)
            ->done(
                function (Message $message) {
                    $this->addReactions($message);
                }
            );
    }

    /**
     * @param Message $message
     */
    protected function addReactions(Message $message): void
    {
        $message->react(Reaction::JOIN);
        $message->react(Reaction::LEAVE);
    }
}
