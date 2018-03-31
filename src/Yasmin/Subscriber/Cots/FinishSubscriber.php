<?php

namespace App\Yasmin\Subscriber\Cots;

use App\Channel\CotsChannel;
use App\Yasmin\Event\MessageReceivedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Lets admins run symfony commands
 * Class ValidateSubscriber
 * @package App\Yasmin\Subscriber
 */
class FinishSubscriber implements EventSubscriberInterface
{
    const COMMAND = '!haamc cots finish';

    /**
     * @var int
     */
    private $adminRole;

    /**
     * @var CotsChannel
     */
    private $cots;

    /**
     * @var string
     */
    private $season;

    /**
     * ValidateSubscriber constructor.
     * @param int|string $adminRole
     * @param CotsChannel $cots
     * @param string $season
     * @internal param RewatchChannel $rewatch
     */
    public function __construct(
        int $adminRole,
        CotsChannel $cots,
        string $season
    ) {
        $this->adminRole = $adminRole;
        $this->cots = $cots;
        $this->season = $season;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents(): array
    {
        return [MessageReceivedEvent::NAME => 'onCommand'];
    }

    /**
     * @param MessageReceivedEvent $event
     */
    public function onCommand(MessageReceivedEvent $event): void
    {
        $message = $event->getMessage();
        if (strpos($message->content, self::COMMAND) !== 0) {
            return;
        }
        if (!$message->member->roles->has((int)$this->adminRole)) {
            return;
        }
        $io = $event->getIo();
        $io->writeln(__CLASS__.' dispatched');
        $event->stopPropagation();

        $nominations = $this->cots->getLastNominations();
        $nominationCount = count($nominations);
        if ($nominationCount <= 2) {
            $message->reply(':x: Not enough nominees');

            return;
        }
        if ($nominations[0]->getVotes() === $nominations[1]->getVotes()) {
            $message->reply(':x: There is no clear winner');

            return;
        }
        $this->cots->message($this->cots->getTop10());
        $this->cots->announceWinner($nominations[0], $this->season);
    }
}