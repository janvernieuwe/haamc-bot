<?php

namespace App\Subscriber\AnimeChannel;

use App\Channel\Channel;
use App\Entity\Reaction;
use App\Event\ReactionAddedEvent;
use App\Message\JoinableChannelMessage;
use Jikan\MyAnimeList\MalClient;
use Jikan\Request\Anime\AnimeRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Join a channel by Reaction
 * Class ValidateSubscriber
 *
 * @package App\Subscriber
 */
class UpdatePostSubscriber implements EventSubscriberInterface
{
    /**
     * @var MalClient
     */
    private $mal;

    /**
     * UpdatePostSubscriber constructor.
     *
     * @param MalClient $mal
     */
    public function __construct(MalClient $mal)
    {
        $this->mal = $mal;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents(): array
    {
        return [ReactionAddedEvent::NAME => 'onCommand'];
    }

    /**
     * @param ReactionAddedEvent $event
     * @throws \HttpResponseException
     * @throws \Jikan\Exception\ParserException
     */
    public function onCommand(ReactionAddedEvent $event): void
    {
        $reaction = $event->getReaction();
        if (!$event->isMod()) {
            return;
        }
        if ($reaction->emoji->name !== Reaction::REFRESH || !$event->isBotMessage()) {
            return;
        }
        if (!JoinableChannelMessage::isJoinChannelMessage($reaction->message)) {
            return;
        }
        $io = $event->getIo();
        $io->writeln(__CLASS__.' dispatched');
        $event->stopPropagation();

        // Load
        $channelMessage = new JoinableChannelMessage($reaction->message);
        $anime = $this->mal->getAnime(new AnimeRequest($channelMessage->getAnimeId()));
        $channel = Channel::getTextChannel($reaction->message);
        $subs = Channel::getUserCount($channel);
        $channelMessage->updateWatchers($anime, $channel->id, $subs);
        $reaction->message->react(Reaction::JOIN);
        $reaction->message->react(Reaction::LEAVE);
        $reaction->remove($reaction->users->last());
        $io->success(sprintf('Updated %s anime channel', $anime->getTitle()));
    }
}
