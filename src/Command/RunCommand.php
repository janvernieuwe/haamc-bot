<?php

namespace App\Command;

use App\Event\MessageReceivedEvent;
use App\Event\ReactionAddedEvent;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Models\DMChannel;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\MessageReaction;
use CharlotteDunois\Yasmin\Models\User;
use React\EventLoop\Factory;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class RunCommand
 *
 * @package App\Command
 */
class RunCommand extends ContainerAwareCommand
{
    public static $start;

    protected function configure(): void
    {
        $this
            ->setName('haamc:yasmin:run')
            ->setDescription('Run the main yasmin loop')
            ->setHelp('Interactive botness');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::$start = time();
        $container = $this->getContainer();
        $adminRole = $container->getParameter('adminRole');
        $animeModRole = $container->getParameter('modRole');
        $permissionsRole = $container->getParameter('permissionsRole');
        $dispatcher = $container->get('event_dispatcher');
        $io = new SymfonyStyle($input, $output);
        $loop = Factory::create();
        $client = new Client([], $loop);

        // Run the bot
        $io->section('Start listening');
        $client->on(
            'ready',
            function () use ($client, $io) {
                $io->writeln(
                    'Logged in as ' . $client->user->tag . ' created on ' . $client->user->createdAt->format(
                        'd.m.Y H:i:s'
                    )
                );
            }
        );

        $client->on(
            'message',
            function (Message $message) use ($io, $dispatcher, $adminRole, $permissionsRole) {
                // Don't listen to bots (and myself)
                if ($message->author->bot) {
                    return;
                }
                if ($message->channel instanceof DMChannel) {
                    $io->writeln('Ignoring DM: ' . $message->content . ' from ' . $message->author->username);

                    return;
                }
                /** @noinspection PhpUndefinedFieldInspection */
                $logMessage = 'Received Message from ' . $message->author->tag . ' in ' .
                    ($message->channel->type === 'text' ? 'channel #' . $message->channel->name : 'DM') . ' with '
                    . $message->attachments->count() . ' attachment(s) and ' . \count($message->embeds) . ' embed(s)';

                if ($io->isVerbose()) {
                    /** @noinspection PhpUndefinedFieldInspection */
                    $io->writeln($logMessage);
                }
                $event = new MessageReceivedEvent($message, $io, $adminRole, $permissionsRole);
                $event->setLogMessage($logMessage);
                $dispatcher->dispatch(MessageReceivedEvent::NAME, $event);

                if ((time() - self::$start) > (60 * 60 * 6)) {
                    exit('Max execution time reached, restarting');
                }
            }
        );

        $client->on(
            'messageReactionAdd',
            function (MessageReaction $reaction, User $user) use ($dispatcher, $io, $adminRole, $animeModRole) {
                if ($user->bot) {
                    return;
                }
                if ($reaction->message->channel instanceof DMChannel) {
                    $io->writeln('Ignoring DM Reactions');

                    return;
                }
                /** @noinspection PhpUndefinedFieldInspection */
                $logMessage = 'Received messageReactionAdd ' . $reaction->emoji->name . ' from '
                    . $reaction->users->last()->username . ' in channel #' . $reaction->message->channel->name;

                if ($io->isVerbose()) {
                    $io->writeln($logMessage);
                }
                $event = new ReactionAddedEvent($reaction, $io, $adminRole, $animeModRole);
                $event->setLogMessage($logMessage);
                $dispatcher->dispatch(ReactionAddedEvent::NAME, $event);
            }
        );

        $client->on(
            'error',
            function (\Exception $e) use ($io) {
                $io->error($e->getMessage());
                if ($io->isVeryVerbose()) {
                    $io->writeln((string)$e);
                }
                // Db con fixer
                $em = $this->getContainer()->get('doctrine')->getManager();
                if ($em->getConnection()->ping() === false) {
                    $io->warning('Lost database connectivity.');
                    $em->getConnection()->close();
                    $em->getConnection()->connect();
                    $io->success('Database connectivity restored');
                }
            }
        );

        $client->login($container->getParameter('token'));
        $loop->run();
    }
}
