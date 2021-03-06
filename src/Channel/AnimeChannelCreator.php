<?php


namespace App\Channel;

use App\Context\CreateAnimeChannelContext;
use App\Entity\Reaction;
use App\Message\JoinableChannelMessage;
use CharlotteDunois\Yasmin\Models\CategoryChannel;
use CharlotteDunois\Yasmin\Models\Guild;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\TextChannel;

/**
 * Class AnimeChannelCreator
 *
 * @package App\Channel
 */
class AnimeChannelCreator
{
    /**
     * @var int
     */
    private $miraiRole;

    /**
     * @var CreateAnimeChannelContext
     */
    private $context;

    /**
     * AnimeChannelCreator constructor.
     *
     * @param int $miraiRole
     */
    public function __construct(int $miraiRole)
    {
        $this->miraiRole = $miraiRole;
    }

    /**
     * @param CreateAnimeChannelContext $context
     *
     * @internal param MessageReceivedEvent $event
     */
    public function create(CreateAnimeChannelContext $context): void
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
                    sprintf('%s || %s', $this->context->getAnime()->getTitle(), $this->context->getAnime()->getUrl())
                );
                /** @var Message $announcement */
                $channel->send(
                    sprintf(
                        "%s Hoi iedereen! In dit channel kijken we naar **%s**.\n%s",
                        JoinableChannelMessage::TEXT_MESSAGE,
                        $this->context->getAnime()->getTitle(),
                        $this->context->getAnime()->getUrl()
                    )
                )->then(
                    function (Message $announcement) {
                        $announcement->pin();
                    }
                );
                $preview = $this->context->getAnime()->getTrailerUrl();
                if ($preview !== null) {
                    $preview = preg_replace('#https://www.youtube.com/embed/(.*)\?.*#', '$1', $preview);
                    $channel->send('https://www.youtube.com/watch?v='.$preview);
                }
                $channel->send(sprintf('`m.airing notify channel %s`', $this->context->getAnime()->getTitle()));

                $this->sendJoinMessage($channel);
            }
        );
    }

    /**
     * @param TextChannel $channel
     */
    protected function sendJoinMessage(TextChannel $channel): void
    {
        $embed = JoinableChannelMessage::generateRichChannelMessage(
            $this->context->getAnime(),
            (int)$channel->id,
            $this->context->getAnime()->getUrl()
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
