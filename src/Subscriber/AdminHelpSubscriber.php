<?php

namespace App\Subscriber;

use App\Event\MessageReceivedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Lets admins run symfony commands
 * Class ValidateSubscriber
 *
 * @package App\Subscriber
 */
class AdminHelpSubscriber implements EventSubscriberInterface
{
    public const COMMAND = '!haamc adminhelp';

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
        if (!$event->isAdmin() || strpos($message->content, self::COMMAND) !== 0) {
            return;
        }
        $io = $event->getIo();
        $io->writeln(__CLASS__.' dispatched');
        $event->stopPropagation();

        $help = <<<HELP
```        
All commands are prefixed with !haamc

### Channel commands ###
channel <channel-name> <mal-anime-link>
    This command creates an anime channel link in the channel you type it in, channel in the category it is in.
    Users can join the channel by clicking the reactions below it.
    The channel & role can be removed by admins by adding the :put_litter_in_its_place: reaction to the message
    
simplechannel <channelname> <description>
    Same as the command above but with a simple description that will be linked to the channel description.
    
mangachannel <channelname> <mal-mango-link>

--category=12345 can be added to this message    

### Other commands ###
cots ranking            (shows the character of the season ranking)
cots start              (start the next character of the season round)
cots finish             (finish and anounce winner of character of the season)
say <channelid> <msg>   (send a message to a channel, admins only)
sotw next               (start the next round of song of the week, admins only)
sotw ranking            (show the current ranking)
sotw forum              (show the current ranking in BBCode, admins only)
rewatch start           (start the next rewatch round, admins only)
rewatch finish          (finish the rewatch round, admins only)
rewatch ranking         (show the current ranking)
bikkel ranking          (Bikkel ranking)
season ranking          (Seasonal anime ranking)
export                  (Export the channel and first reaction count to a csv, select another channel with --channel=)
addreaction <emoji>     Add <emoji> as a reaction to the last 100 messages in the channel
```
HELP;
        $message->channel->send($help);
        $io->success('Displayed admin help');
    }
}
